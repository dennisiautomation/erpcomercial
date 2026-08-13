<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusPedido;
use App\Http\Controllers\Controller;
use App\Models\AgenteIaConfig;
use App\Models\Cliente;
use App\Models\IntegracaoToken;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\Unidade;
use App\Scopes\EmpresaScope;
use App\Scopes\UnidadeScope;
use App\Services\AgenteIa\EmbeddingService;
use App\Services\SaldoEstoque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * API do Agente IA — consumida pelo app.ia365 (agente WhatsApp e painel
 * de atendimento humano).
 *
 * Mesmo modelo do IntegracaoGersenController: Bearer por empresa, global
 * scopes removidos um a um (armadilha 38), 404 idêntico para recurso
 * alheio/inexistente. Diferenças: exige Agente IA ATIVO na empresa, e o
 * POST /pedidos é o único endpoint de ESCRITA da API de integração —
 * cria pedido RASCUNHO (não movimenta estoque, não fatura, não emite
 * fiscal; um humano confirma no ERP).
 *
 * Busca híbrida (receita validada na ChinaMix):
 *   textual (LIKE em descrição/códigos, todos os termos) similaridade 1.0
 *   + semântica (pgvector, função buscar_produtos) ≥ 0.3
 *   → merge com textual na frente, corta no limite.
 */
class IntegracaoAgenteController extends Controller
{
    private const PEDIDOS_POR_PAGINA = 50;

    public function __construct(private readonly EmbeddingService $embeddings)
    {
    }

    /* ---------------------------------------------------------------- */
    /*  Produtos                                                         */
    /* ---------------------------------------------------------------- */

    public function buscarProdutos(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'consulta' => ['required', 'string', 'min:2', 'max:200'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:10'],
            'unidade_id' => ['nullable', 'integer'],
            'incluir_sem_estoque' => ['nullable', 'boolean'],
        ]);

        $limite = (int) ($validated['limite'] ?? 5);
        $incluirSemEstoque = (bool) ($validated['incluir_sem_estoque'] ?? false);

        $unidadeId = null;
        if (! empty($validated['unidade_id'])) {
            $unidadeId = (int) $validated['unidade_id'];
            $existe = Unidade::withoutGlobalScope(EmpresaScope::class)
                ->where('empresa_id', $token->empresa_id)
                ->whereKey($unidadeId)
                ->exists();
            if (! $existe) {
                return response()->json(['erro' => 'Loja não encontrada.'], 404);
            }
        }

        // 1) Busca textual: todos os termos (>2 chars) precisam casar com
        //    descrição ou algum código. Match textual = prioridade máxima.
        $termos = collect(preg_split('/\s+/', mb_strtolower(trim($validated['consulta']))))
            ->filter(fn ($t) => mb_strlen($t) > 2)
            ->take(6);

        $idsTextuais = [];
        if ($termos->isNotEmpty()) {
            $query = Produto::withoutGlobalScope(EmpresaScope::class)
                ->where('empresa_id', $token->empresa_id)
                ->where('status', 'ativo');

            foreach ($termos as $termo) {
                $query->where(function ($q) use ($termo) {
                    $like = '%' . $termo . '%';
                    $q->where('descricao', 'like', $like)
                        ->orWhere('codigo_interno', 'like', $termo . '%')
                        ->orWhere('sku', 'like', $termo . '%')
                        ->orWhere('codigo_barras', $termo);
                });
            }

            $idsTextuais = $query->orderBy('descricao')->limit($limite)->pluck('id')->all();
        }

        // 2) Busca semântica no pgvector
        $similaridades = [];
        try {
            $embedding = $this->embeddings->gerar($validated['consulta']);
            $linhas = DB::connection('vector')->select(
                'SELECT produto_id, similaridade FROM buscar_produtos(?, ?::vector, ?, ?)',
                [$token->empresa_id, '[' . implode(',', $embedding) . ']', $limite * 2, 0.3]
            );
            foreach ($linhas as $linha) {
                $similaridades[(int) $linha->produto_id] = round((float) $linha->similaridade, 4);
            }
        } catch (\Throwable $e) {
            // Semântica indisponível (OpenAI/vector fora) → degrada para só textual
            Log::channel('integracao')->warning('Busca semântica indisponível — respondendo só textual', [
                'empresa_id' => $token->empresa_id,
                'erro' => $e->getMessage(),
            ]);
        }

        // 3) Merge: textual na frente (1.0), semântico completa
        $ids = collect($idsTextuais)
            ->concat(array_keys($similaridades))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['dados' => [], 'consulta' => $validated['consulta']]);
        }

        $produtos = Produto::withoutGlobalScope(EmpresaScope::class)
            ->with(['categoria:id,nome', 'precos'])
            ->where('empresa_id', $token->empresa_id)
            ->where('status', 'ativo')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $saldosEmpresa = $unidadeId ? null : SaldoEstoque::porProdutoDaEmpresa($token->empresa_id);

        $dados = $ids
            ->map(function ($id) use ($produtos, $idsTextuais, $similaridades, $unidadeId, $saldosEmpresa) {
                $produto = $produtos->get($id);
                if (! $produto) {
                    return null;
                }

                $estoque = $unidadeId
                    ? SaldoEstoque::naUnidade($unidadeId, $produto->id)
                    : (float) ($saldosEmpresa[$produto->id] ?? 0.0);

                return $this->produtoParaResposta($produto, $estoque)
                    + ['similaridade' => in_array($id, $idsTextuais, true) ? 1.0 : ($similaridades[$id] ?? 0.0)];
            })
            ->filter()
            ->when(! $incluirSemEstoque, fn ($c) => $c->filter(fn ($p) => $p['estoque'] > 0))
            ->take($limite)
            ->values();

        return response()->json(['dados' => $dados, 'consulta' => $validated['consulta']]);
    }

    public function produto(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $produto = Produto::withoutGlobalScope(EmpresaScope::class)
            ->with(['categoria:id,nome', 'precos'])
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($id)
            ->first();

        if (! $produto) {
            return response()->json(['erro' => 'Produto não encontrado.'], 404);
        }

        $saldos = SaldoEstoque::porProdutoDaEmpresa($token->empresa_id);

        return response()->json([
            'dados' => $this->produtoParaResposta($produto, (float) ($saldos[$produto->id] ?? 0.0))
                + [
                    'descricao_detalhada' => $produto->descricao_detalhada,
                    'estoque_por_loja' => $this->estoquePorLoja($token->empresa_id, $produto->id),
                ],
        ]);
    }

    public function estoqueProduto(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $existe = Produto::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($id)
            ->exists();

        if (! $existe) {
            return response()->json(['erro' => 'Produto não encontrado.'], 404);
        }

        return response()->json(['dados' => $this->estoquePorLoja($token->empresa_id, $id)]);
    }

    /* ---------------------------------------------------------------- */
    /*  Pedidos                                                          */
    /* ---------------------------------------------------------------- */

    public function pedidos(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:rascunho,confirmado,faturado,entregue,cancelado'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = (int) ($validated['pagina'] ?? 1);

        $query = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->with(['cliente:id,nome_razao_social,telefone,whatsapp', 'unidade:id,nome', 'itens' => fn ($q) => $q->with('produto:id,descricao,foto')])
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['telefone'] ?? null, function ($q, $tel) {
                $digitos = preg_replace('/\D/', '', $tel);
                $q->whereHas('cliente', function ($c) use ($digitos) {
                    $c->where('telefone', 'like', "%{$digitos}%")
                        ->orWhere('whatsapp', 'like', "%{$digitos}%");
                });
            })
            ->orderByDesc('id');

        $lista = $query
            ->skip(($pagina - 1) * self::PEDIDOS_POR_PAGINA)
            ->take(self::PEDIDOS_POR_PAGINA + 1)
            ->get();

        $temMais = $lista->count() > self::PEDIDOS_POR_PAGINA;

        return response()->json([
            'dados' => $lista->take(self::PEDIDOS_POR_PAGINA)->map(fn (Pedido $p) => $this->pedidoParaResposta($p))->values(),
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    public function pedido(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $pedido = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->with(['cliente:id,nome_razao_social,telefone,whatsapp', 'unidade:id,nome', 'itens' => fn ($q) => $q->with('produto:id,descricao,foto')])
            ->whereKey($id)
            ->first();

        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        return response()->json(['dados' => $this->pedidoParaResposta($pedido)]);
    }

    /**
     * Cria um pedido RASCUNHO em nome do cliente da conversa.
     *
     * Não movimenta estoque nem gera cobrança — o pedido cai na tela de
     * Pedidos do ERP para um humano confirmar, faturar e cobrar.
     */
    public function criarPedido(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'unidade_id' => ['required', 'integer'],
            'cliente.nome' => ['required', 'string', 'max:255'],
            'cliente.telefone' => ['required', 'string', 'max:20'],
            'cliente.cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'cliente.email' => ['nullable', 'email', 'max:255'],
            'itens' => ['required', 'array', 'min:1', 'max:30'],
            'itens.*.produto_id' => ['required', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'min:0.001', 'max:9999'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'origem' => ['nullable', 'string', 'max:50'],
        ]);

        $unidade = Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($validated['unidade_id'])
            ->first();

        if (! $unidade) {
            return response()->json(['erro' => 'Loja não encontrada.'], 404);
        }

        // Produtos precisam TODOS ser da empresa do token
        $produtoIds = collect($validated['itens'])->pluck('produto_id')->unique();
        $produtos = Produto::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->where('status', 'ativo')
            ->whereIn('id', $produtoIds)
            ->get()
            ->keyBy('id');

        if ($produtos->count() !== $produtoIds->count()) {
            return response()->json(['erro' => 'Produto não encontrado.'], 404);
        }

        $config = AgenteIaConfig::where('empresa_id', $token->empresa_id)->first();

        $pedido = DB::transaction(function () use ($validated, $token, $produtos, $config) {
            $cliente = $this->encontrarOuCriarCliente($token->empresa_id, $validated['cliente']);

            $ultimoNumero = Pedido::withoutGlobalScope(EmpresaScope::class)
                ->withoutGlobalScope(UnidadeScope::class)
                ->where('empresa_id', $token->empresa_id)
                ->lockForUpdate()
                ->max('numero');

            $origem = $validated['origem'] ?? 'Agente IA (WhatsApp)';

            $subtotal = 0.0;
            $linhas = [];
            foreach ($validated['itens'] as $item) {
                $produto = $produtos[(int) $item['produto_id']];
                $quantidade = (float) $item['quantidade'];
                $preco = (float) $produto->preco_venda;
                $totalLinha = round($quantidade * $preco, 2);
                $subtotal += $totalLinha;

                $linhas[] = [
                    'produto_id' => $produto->id,
                    'descricao' => $produto->descricao,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco,
                    'desconto_percentual' => 0,
                    'desconto_valor' => 0,
                    'total' => $totalLinha,
                ];
            }

            $pedido = Pedido::create([
                'empresa_id' => $token->empresa_id,
                'unidade_id' => $validated['unidade_id'],
                'cliente_id' => $cliente->id,
                'vendedor_id' => $config?->vendedor_padrao_id,
                'numero' => $ultimoNumero ? $ultimoNumero + 1 : 1,
                'subtotal' => round($subtotal, 2),
                'desconto_percentual' => 0,
                'desconto_valor' => 0,
                'total' => round($subtotal, 2),
                'status' => StatusPedido::Rascunho,
                'observacoes_internas' => "Criado via {$origem} — telefone {$validated['cliente']['telefone']}."
                    . (isset($validated['observacoes']) ? "\n" . $validated['observacoes'] : ''),
            ]);

            foreach ($linhas as $linha) {
                $pedido->itens()->create($linha);
            }

            return $pedido;
        });

        Log::channel('integracao')->info('Agente IA: pedido criado', [
            'empresa_id' => $token->empresa_id,
            'pedido_id' => $pedido->id,
            'numero' => $pedido->numero,
            'total' => (float) $pedido->total,
        ]);

        return response()->json([
            'dados' => [
                'id' => (string) $pedido->id,
                'numero' => (int) $pedido->numero,
                'total' => (float) $pedido->total,
                'status' => $pedido->status->value,
                'mensagem' => "Pedido #{$pedido->numero} registrado! Total R$ " . number_format((float) $pedido->total, 2, ',', '.')
                    . '. Um atendente vai confirmar com você a forma de pagamento e a entrega/retirada.',
            ],
        ], 201);
    }

    /* ---------------------------------------------------------------- */
    /*  Internos                                                         */
    /* ---------------------------------------------------------------- */

    private function token(Request $request): IntegracaoToken
    {
        return $request->attributes->get('integracao_token');
    }

    private function exigirAgenteAtivo(IntegracaoToken $token): ?JsonResponse
    {
        if (! AgenteIaConfig::ativaPara($token->empresa_id)) {
            return response()->json(['erro' => 'Agente IA não está ativo para esta empresa.'], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function produtoParaResposta(Produto $produto, float $estoque): array
    {
        $precosModalidade = $produto->precos
            ->mapWithKeys(fn ($p) => [$p->modalidade => (float) $p->valor]);

        return [
            'id' => (string) $produto->id,
            'nome' => $produto->descricao,
            'codigo' => $produto->codigo_interno,
            'categoria' => $produto->categoria?->nome,
            'preco' => (float) $produto->preco_venda,
            'precos_modalidade' => $precosModalidade->isEmpty() ? null : $precosModalidade,
            'unidade_medida' => $produto->unidade_medida,
            'estoque' => $estoque,
            'foto_url' => $produto->foto
                ? rtrim(config('app.url'), '/') . Storage::url($produto->foto)
                : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function estoquePorLoja(int $empresaId, int $produtoId): array
    {
        return Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativa') // unidades.status é FEMININO (armadilha 5)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Unidade $u) => [
                'loja_id' => (string) $u->id,
                'loja' => $u->nome,
                'estoque' => SaldoEstoque::naUnidade($u->id, $produtoId),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function pedidoParaResposta(Pedido $pedido): array
    {
        return [
            'id' => (string) $pedido->id,
            'numero' => (int) $pedido->numero,
            'data' => $pedido->created_at->format('Y-m-d H:i'),
            'status' => $pedido->status->value,
            'status_nome' => $pedido->status->label(),
            'total' => (float) $pedido->total,
            'loja' => $pedido->unidade?->nome,
            'cliente' => $pedido->cliente ? [
                'id' => (string) $pedido->cliente->id,
                'nome' => $pedido->cliente->nome_razao_social,
                'telefone' => $pedido->cliente->whatsapp ?: $pedido->cliente->telefone,
            ] : null,
            'itens' => $pedido->itens->map(fn (PedidoItem $i) => [
                'produto_id' => $i->produto_id ? (string) $i->produto_id : null,
                'descricao' => $i->descricao,
                'quantidade' => (float) $i->quantidade,
                'preco_unitario' => (float) $i->preco_unitario,
                'total' => (float) $i->total,
                'foto_url' => $i->produto?->foto
                    ? rtrim(config('app.url'), '/') . Storage::url($i->produto->foto)
                    : null,
            ])->values(),
            'observacoes' => $pedido->observacoes_internas,
        ];
    }

    /** @param array<string, mixed> $dados */
    private function encontrarOuCriarCliente(int $empresaId, array $dados): Cliente
    {
        $digitos = preg_replace('/\D/', '', $dados['telefone']);

        // Telefone BR com/sem 9º dígito: casa pelo sufixo (últimos 8)
        $sufixo = mb_substr($digitos, -8);

        $cliente = Cliente::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($sufixo) {
                $q->where('telefone', 'like', "%{$sufixo}")
                    ->orWhere('whatsapp', 'like', "%{$sufixo}");
            })
            ->orderBy('id')
            ->first();

        if ($cliente) {
            return $cliente;
        }

        return Cliente::create([
            'empresa_id' => $empresaId,
            'tipo_pessoa' => 'pf',
            'nome_razao_social' => $dados['nome'],
            'cpf_cnpj' => isset($dados['cpf_cnpj']) ? preg_replace('/[^0-9A-Za-z]/', '', $dados['cpf_cnpj']) : null,
            'telefone' => $digitos,
            'whatsapp' => $digitos,
            'email' => $dados['email'] ?? null,
            'status' => 'ativo',
            'observacoes' => 'Cadastrado automaticamente pelo Agente IA (WhatsApp).',
        ]);
    }
}
