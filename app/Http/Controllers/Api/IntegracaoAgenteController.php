<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusPedido;
use App\Http\Controllers\Controller;
use App\Models\AgenteIaConfig;
use App\Models\Cliente;
use App\Models\EmpresaGateway;
use App\Models\IntegracaoToken;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use App\Models\PedidoEntrega;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\Unidade;
use App\Services\Entrega\UberDirectService;
use App\Services\Pix\PixPedidoService;
use App\Services\Pix\SicrediPixService;
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
    /*  Ativação                                                         */
    /* ---------------------------------------------------------------- */

    /**
     * Ativa o módulo para a empresa do token e dispara a indexação.
     *
     * Chamado pelo provisionamento do app.ia365 ("Criar agente") — o token
     * já prova que o admin da empresa autorizou a integração, então exigir
     * um segundo clique no admin do ERP era burocracia (pedido do Dennis).
     * É o ÚNICO endpoint do agente que não passa por exigirAgenteAtivo.
     * Idempotente: já ativo → só re-dispara a indexação.
     */
    public function ativarAgente(Request $request): JsonResponse
    {
        $token = $this->token($request);

        $config = AgenteIaConfig::firstOrCreate(['empresa_id' => $token->empresa_id]);
        $jaEstavaAtivo = (bool) $config->ativo;

        if (! $jaEstavaAtivo) {
            $config->update(['ativo' => true]);
        }

        \App\Jobs\IndexarEmpresaAgenteJob::dispatch($token->empresa_id);

        Log::channel('integracao')->info('Agente IA: ativado via API de integração', [
            'empresa_id' => $token->empresa_id,
            'ja_estava_ativo' => $jaEstavaAtivo,
        ]);

        return response()->json([
            'dados' => [
                'ativo' => true,
                'ja_estava_ativo' => $jaEstavaAtivo,
                'produtos_indexados' => (int) $config->produtos_indexados,
                'indexado_em' => $config->indexado_em?->toIso8601String(),
            ],
        ]);
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
            'ordenar' => ['nullable', 'string', 'in:relevancia,preco_asc,preco_desc'],
            'preco_min' => ['nullable', 'numeric', 'min:0'],
            'preco_max' => ['nullable', 'numeric', 'min:0'],
        ]);

        $limite = (int) ($validated['limite'] ?? 5);
        $incluirSemEstoque = (bool) ($validated['incluir_sem_estoque'] ?? false);
        $ordenar = $validated['ordenar'] ?? 'relevancia';
        $precoMin = isset($validated['preco_min']) ? (float) $validated['preco_min'] : null;
        $precoMax = isset($validated['preco_max']) ? (float) $validated['preco_max'] : null;

        // "Mais caro"/"até X reais" precisam de um pool de candidatos maior
        // que o limite final: a relevância deixa de ser o critério de corte.
        $modoPreco = $ordenar !== 'relevancia' || $precoMin !== null || $precoMax !== null;
        $pool = $modoPreco ? max($limite * 6, 30) : $limite;

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

            if ($precoMin !== null) {
                $query->where('preco_venda', '>=', $precoMin);
            }
            if ($precoMax !== null) {
                $query->where('preco_venda', '<=', $precoMax);
            }

            $idsTextuais = $query->orderBy('descricao')->limit($pool)->pluck('id')->all();
        }

        // 2) Busca semântica no pgvector
        $similaridades = [];
        try {
            $embedding = $this->embeddings->gerar($validated['consulta']);
            $linhas = DB::connection('vector')->select(
                'SELECT produto_id, similaridade FROM buscar_produtos(?, ?::vector, ?, ?)',
                [$token->empresa_id, '[' . implode(',', $embedding) . ']', max($pool, $limite * 2), 0.3]
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

        $fallbackCatalogo = false;

        // Sem candidato e sem modo preço → vazio direto. Com modo preço o fluxo
        // segue até o fallback de catálogo (§272.2) lá embaixo.
        if ($ids->isEmpty() && ! $modoPreco) {
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
            ->when($precoMin !== null, fn ($c) => $c->filter(fn ($p) => $p['preco'] >= $precoMin))
            ->when($precoMax !== null, fn ($c) => $c->filter(fn ($p) => $p['preco'] <= $precoMax))
            ->when($ordenar === 'preco_desc', fn ($c) => $c->sortByDesc('preco'))
            ->when($ordenar === 'preco_asc', fn ($c) => $c->sortBy('preco'))
            ->take($limite)
            ->values();

        // Fallback de catálogo (§272.2): consulta genérica ("produto", "alguma
        // coisa") com filtro/ordenação de preço não casa textual nem passa do
        // corte semântico, mas o catálogo TEM itens na faixa — devolve o
        // catálogo na faixa pedida, marcado como fallback_catalogo para o
        // agente avisar que não é um match da descrição.
        if ($dados->isEmpty() && $modoPreco) {
            $fb = Produto::withoutGlobalScope(EmpresaScope::class)
                ->with(['categoria:id,nome', 'precos'])
                ->where('empresa_id', $token->empresa_id)
                ->where('status', 'ativo');
            if ($precoMin !== null) {
                $fb->where('preco_venda', '>=', $precoMin);
            }
            if ($precoMax !== null) {
                $fb->where('preco_venda', '<=', $precoMax);
            }
            $fb->orderBy('preco_venda', $ordenar === 'preco_desc' ? 'desc' : 'asc');

            $saldosFb = $unidadeId ? null : ($saldosEmpresa ?? SaldoEstoque::porProdutoDaEmpresa($token->empresa_id));

            $dados = $fb->limit($pool)->get()
                ->map(function ($produto) use ($unidadeId, $saldosFb) {
                    $estoque = $unidadeId
                        ? SaldoEstoque::naUnidade($unidadeId, $produto->id)
                        : (float) ($saldosFb[$produto->id] ?? 0.0);

                    return $this->produtoParaResposta($produto, $estoque) + ['similaridade' => 0.0];
                })
                ->when(! $incluirSemEstoque, fn ($c) => $c->filter(fn ($p) => $p['estoque'] > 0))
                ->take($limite)
                ->values();

            $fallbackCatalogo = $dados->isNotEmpty();
        }

        return response()->json([
            'dados' => $dados,
            'consulta' => $validated['consulta'],
            'ordenar' => $ordenar,
            'fallback_catalogo' => $fallbackCatalogo,
        ]);
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

    /**
     * Dashboard do cliente no app.ia365 (padrão China Mix): vendas concluídas,
     * pedidos do agente, série diária, top produtos e últimos pedidos.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $dias = min(90, max(7, (int) $request->query('dias', 30)));
        $inicio = now()->subDays($dias)->startOfDay();

        $vendasBase = \App\Models\Venda::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->where('status', \App\Enums\StatusVenda::Concluida)
            ->where('created_at', '>=', $inicio);

        $vendas = (clone $vendasBase)
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total), 0) as receita')
            ->first();

        $serieVendas = (clone $vendasBase)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as qtd, COALESCE(SUM(total), 0) as valor')
            ->groupBy('dia')
            ->pluck('valor', 'dia')
            ->map(fn ($v) => (float) $v);

        $pedidosBase = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->where('created_at', '>=', $inicio);

        $pedidosPorStatus = (clone $pedidosBase)
            ->selectRaw('status, COUNT(*) as qtd')
            ->groupBy('status')
            ->pluck('qtd', 'status');

        $seriePedidos = (clone $pedidosBase)
            ->selectRaw('DATE(created_at) as dia, COUNT(*) as qtd')
            ->groupBy('dia')
            ->pluck('qtd', 'dia');

        // Série contínua (dias sem movimento entram zerados — eixo estável)
        $serie = [];
        for ($d = 0; $d < $dias; $d++) {
            $dia = now()->subDays($dias - 1 - $d)->format('Y-m-d');
            $serie[] = [
                'dia' => $dia,
                'valor_vendas' => (float) ($serieVendas[$dia] ?? 0),
                'pedidos' => (int) ($seriePedidos[$dia] ?? 0),
            ];
        }

        $topProdutos = DB::table('venda_itens as vi')
            ->join('vendas as v', 'v.id', '=', 'vi.venda_id')
            ->where('v.empresa_id', $token->empresa_id)
            ->where('v.status', 'concluida')
            ->where('v.created_at', '>=', $inicio)
            ->whereNull('v.deleted_at')
            ->whereNull('vi.deleted_at')
            ->selectRaw('vi.descricao, SUM(vi.quantidade) as quantidade, SUM(vi.total) as valor')
            ->groupBy('vi.descricao')
            ->orderByDesc('valor')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'descricao' => $r->descricao,
                'quantidade' => (float) $r->quantidade,
                'valor' => (float) $r->valor,
            ]);

        $ultimosPedidos = (clone $pedidosBase)
            ->with('cliente:id,nome_razao_social')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Pedido $p) => [
                'numero' => (int) $p->numero,
                'cliente' => $p->cliente?->nome_razao_social,
                'total' => (float) $p->total,
                'status' => $p->status->value,
                'data' => $p->created_at->format('d/m H:i'),
            ]);

        $catalogoAtivo = Produto::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->where('status', 'ativo')
            ->count();

        $qtdVendas = (int) ($vendas->qtd ?? 0);
        $receita = (float) ($vendas->receita ?? 0);

        return response()->json([
            'dados' => [
                'dias' => $dias,
                'vendas' => [
                    'qtd' => $qtdVendas,
                    'receita' => $receita,
                    'ticket_medio' => $qtdVendas > 0 ? round($receita / $qtdVendas, 2) : 0,
                ],
                'pedidos' => [
                    'total' => (int) $pedidosPorStatus->sum(),
                    'por_status' => $pedidosPorStatus,
                ],
                'catalogo_ativo' => $catalogoAtivo,
                'serie' => $serie,
                'top_produtos' => $topProdutos,
                'ultimos_pedidos' => $ultimosPedidos,
            ],
        ]);
    }

    /** KPIs da aba Pedidos do app.ia365: contagem por status + receita 30d. */
    public function resumoPedidos(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $base = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id);

        $porStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as qtd')
            ->groupBy('status')
            ->pluck('qtd', 'status');

        $ultimos30 = (clone $base)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total), 0) as valor')
            ->first();

        return response()->json([
            'dados' => [
                'por_status' => $porStatus,
                'total' => (clone $base)->count(),
                'qtd_30d' => (int) ($ultimos30->qtd ?? 0),
                'valor_30d' => (float) ($ultimos30->valor ?? 0),
            ],
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
            // Método de entrega coletado na conversa (25/08). Tudo opcional:
            // agente antigo (sem os campos) segue criando pedido normalmente.
            'entrega.metodo' => ['nullable', 'string', 'in:retirada,entrega'],
            'entrega.cep' => ['nullable', 'string', 'max:9'],
            'entrega.logradouro' => ['nullable', 'string', 'max:255'],
            'entrega.numero' => ['nullable', 'string', 'max:20'],
            'entrega.complemento' => ['nullable', 'string', 'max:100'],
            'entrega.bairro' => ['nullable', 'string', 'max:100'],
            'entrega.cidade' => ['nullable', 'string', 'max:100'],
            'entrega.uf' => ['nullable', 'string', 'size:2'],
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

        $metodoEntrega = $validated['entrega']['metodo'] ?? null;

        $pedido = DB::transaction(function () use ($validated, $token, $produtos, $config, $unidade, $metodoEntrega) {
            $cliente = $this->encontrarOuCriarCliente($token->empresa_id, $validated['cliente']);

            // Entrega: o endereço coletado na conversa vira o endereço do
            // CLIENTE — é dele que o DespacharEntregaUberJob monta o dropoff
            // no pagamento. Sem isso todo pedido do agente nasce inentregável
            // (cliente_sem_endereco). Cidade/UF caem na unidade quando o
            // cliente não informou (entrega local é o caso típico).
            if ($metodoEntrega === 'entrega') {
                $end = $validated['entrega'];
                $novo = array_filter([
                    'cep' => isset($end['cep']) ? preg_replace('/\D/', '', $end['cep']) : null,
                    'logradouro' => $end['logradouro'] ?? null,
                    'numero' => $end['numero'] ?? null,
                    'complemento' => $end['complemento'] ?? null,
                    'bairro' => $end['bairro'] ?? null,
                    'cidade' => $end['cidade'] ?? null,
                    'uf' => isset($end['uf']) ? mb_strtoupper($end['uf']) : null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($novo !== []) {
                    $novo['cidade'] ??= $cliente->cidade ?: $unidade->cidade;
                    $novo['uf'] ??= $cliente->uf ?: $unidade->uf;
                    $cliente->fill($novo)->save();
                }
            }

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
                'metodo_entrega' => $metodoEntrega,
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

        // Empresa com gateway PIX ativo: cobrança nasce junto com o pedido.
        // Best-effort — falha no PSP NÃO derruba o pedido (cai no fluxo
        // antigo: humano cobra).
        $pix = null;
        if (SicrediPixService::paraEmpresa($token->empresa_id)) {
            try {
                $resultado = app(PixPedidoService::class)->cobrancaParaPedido($pedido->fresh(['cliente', 'unidade']));
                if ($resultado['success'] && $resultado['cobranca']->copia_cola) {
                    $pix = $this->cobrancaParaResposta($resultado['cobranca']);
                }
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao gerar PIX do pedido', [
                    'pedido_id' => $pedido->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        // Fase 2 (13/08): empresa com Asaas ativo → link de CARTÃO junto do
        // pedido (best-effort, mesmo contrato do PIX: falha não derruba nada).
        $cartaoLink = null;
        if ($asaas = \App\Services\Pagamento\AsaasService::ativoPara($token->empresa_id)) {
            try {
                $cartaoLink = $asaas->linkCartaoParaPedido($pedido->fresh(['cliente']))['link'] ?: null;
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao gerar link de cartão Asaas', [
                    'pedido_id' => $pedido->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        // Bloco de entrega da resposta (25/08): diz ao agente o que prometer.
        // 'automatica' = Uber ativo + endereço utilizável + CEP na área ⇒ o
        // despacho sai sozinho no pagamento; senão a promessa é o humano.
        $entrega = null;
        if ($metodoEntrega === 'retirada') {
            $entrega = ['metodo' => 'retirada', 'automatica' => false,
                'mensagem' => 'Retirada combinada na loja.'];
        } elseif ($metodoEntrega === 'entrega') {
            $clienteEntrega = $pedido->fresh(['cliente'])->cliente;
            $uber = UberDirectService::ativoPara($token->empresa_id);
            $automatica = $uber && $clienteEntrega
                && UberDirectService::enderecoCliente($clienteEntrega)
                && $uber->cepAtendido($clienteEntrega->cep);
            $entrega = ['metodo' => 'entrega', 'automatica' => (bool) $automatica,
                'mensagem' => $automatica
                    ? 'A entrega é acionada automaticamente assim que o pagamento confirmar.'
                    : 'Um atendente vai combinar a entrega com você.'];
        }

        $mensagem = "Pedido #{$pedido->numero} registrado! Total R$ " . number_format((float) $pedido->total, 2, ',', '.') . '.';
        if ($pix && $cartaoLink) {
            $mensagem .= ' Para pagar agora, use o PIX copia-e-cola enviado OU o link de cartão. Assim que o pagamento cair, o pedido é confirmado automaticamente.';
        } elseif ($pix) {
            $mensagem .= ' Para pagar agora, use o PIX copia-e-cola enviado. Assim que o pagamento cair, o pedido é confirmado automaticamente.';
        } elseif ($cartaoLink) {
            $mensagem .= ' Para pagar no cartão, use o link enviado. Assim que o pagamento cair, o pedido é confirmado automaticamente.';
        } elseif ($entrega) {
            $mensagem .= ' Um atendente vai confirmar com você a forma de pagamento.';
        } else {
            $mensagem .= ' Um atendente vai confirmar com você a forma de pagamento e a entrega/retirada.';
        }
        if ($entrega) {
            $mensagem .= ' ' . $entrega['mensagem'];
        }

        return response()->json([
            'dados' => [
                'id' => (string) $pedido->id,
                'numero' => (int) $pedido->numero,
                'total' => (float) $pedido->total,
                'status' => $pedido->status->value,
                'pix' => $pix,
                'cartao_link' => $cartaoLink,
                'entrega' => $entrega,
                'mensagem' => $mensagem,
            ],
        ], 201);
    }

    /**
     * Cota a entrega Uber Direct para um endereço, ANTES de fechar o pedido.
     *
     * Ferramenta do agente (intenção COTAR ENTREGA): valida que o endereço é
     * entregável e que a credencial do gateway funciona. Sempre responde 200
     * com `disponivel` true/false — o agente lê o resultado e se adapta
     * (mesmo desenho response-driven do pix/cartao_link no POST /pedidos).
     * ⚠️ O valor cotado é o CUSTO da loja com o Uber; cobrar ou não frete do
     * cliente é decisão comercial fora deste endpoint — o agente não promete
     * valor de frete ao cliente.
     */
    public function cotarEntrega(Request $request): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'unidade_id' => ['required', 'integer'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
        ]);

        $unidade = Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($validated['unidade_id'])
            ->first();

        if (! $unidade) {
            return response()->json(['erro' => 'Loja não encontrada.'], 404);
        }

        $indisponivel = fn (string $motivo, string $mensagem) => response()->json([
            'dados' => ['disponivel' => false, 'motivo' => $motivo, 'mensagem' => $mensagem],
        ]);

        $uber = UberDirectService::ativoPara($token->empresa_id);
        if (! $uber) {
            return $indisponivel('entrega_desativada',
                'Entrega automática não está habilitada nesta loja — um atendente pode combinar a entrega.');
        }

        $cep = preg_replace('/\D/', '', (string) ($validated['cep'] ?? ''));
        if (! $uber->cepAtendido($cep)) {
            return $indisponivel('cep_fora_da_area',
                'Este CEP está fora da área de entrega — um atendente pode combinar outra forma, ou o pedido pode ser para retirada.');
        }

        // Cidade/UF caem na unidade quando não informadas (entrega local).
        $rua = trim(($validated['logradouro'] ?? '') . ' ' . ($validated['numero'] ?? ''));
        $dropoff = trim(sprintf(
            '%s, %s, %s, %s, BR',
            $rua !== '' ? $rua : ($validated['bairro'] ?? ''),
            $validated['cidade'] ?? $unidade->cidade,
            mb_strtoupper($validated['uf'] ?? $unidade->uf),
            $cep
        ), ', ');

        try {
            $quote = $uber->cotar($unidade, $dropoff);
        } catch (\Throwable $e) {
            Log::channel('integracao')->error('Agente IA: falha ao cotar entrega Uber', [
                'empresa_id' => $token->empresa_id,
                'erro' => $e->getMessage(),
            ]);
            // Registra no gateway p/ o card da aba Integração denunciar a
            // credencial quebrada (mesma coluna do "Testar conexão").
            EmpresaGateway::ativoPara($token->empresa_id, EmpresaGateway::PROVEDOR_UBER_DIRECT)
                ?->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);

            return $indisponivel('erro_cotacao',
                'Não consegui cotar a entrega agora — um atendente pode combinar a entrega.');
        }

        EmpresaGateway::ativoPara($token->empresa_id, EmpresaGateway::PROVEDOR_UBER_DIRECT)
            ?->update(['ultima_falha' => null]);

        return response()->json([
            'dados' => [
                'disponivel' => true,
                'valor_custo' => round($quote['fee'] / 100, 2),
                'prazo_minutos' => $quote['duration'],
                'mensagem' => 'Entrega disponível para este endereço. Ela é acionada automaticamente assim que o pagamento do pedido confirmar.',
            ],
        ]);
    }

    /**
     * Gera (ou devolve a 2ª via de) a cobrança PIX de um pedido.
     *
     * Usada pela intenção "PAGAR PEDIDO" do agente e pelo painel. Se o
     * pedido já foi pago, devolve o comprovante em vez de nova cobrança.
     */
    public function pixPedido(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $pedido = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->with(['cliente', 'unidade'])
            ->whereKey($id)
            ->first();

        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        if (! SicrediPixService::paraEmpresa($token->empresa_id)) {
            return response()->json(['erro' => 'PIX não está configurado para esta empresa.'], 409);
        }

        $resultado = app(PixPedidoService::class)->cobrancaParaPedido($pedido);

        if (! $resultado['success']) {
            return response()->json(['erro' => $resultado['error']], 422);
        }

        $cobranca = $resultado['cobranca'];

        return response()->json([
            'dados' => array_merge($this->cobrancaParaResposta($cobranca), [
                'pedido_numero' => (int) $pedido->numero,
                'mensagem' => $cobranca->paga()
                    ? "O pedido #{$pedido->numero} já está PAGO (em " . $cobranca->pago_em->format('d/m/Y H:i') . ').'
                    : "PIX do pedido #{$pedido->numero}: R$ " . number_format((float) $cobranca->valor, 2, ',', '.')
                        . '. Copie o código e pague no app do seu banco.',
            ]),
        ]);
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
    private function cobrancaParaResposta(PedidoCobranca $cobranca): array
    {
        return [
            'txid' => $cobranca->txid,
            'valor' => (float) $cobranca->valor,
            'status' => $cobranca->status,
            'pago' => $cobranca->paga(),
            'pago_em' => $cobranca->pago_em?->format('Y-m-d H:i'),
            'copia_cola' => $cobranca->paga() ? null : $cobranca->copia_cola,
            'expira_em' => $cobranca->expira_em?->format('Y-m-d H:i'),
        ];
    }

    /** @return array<string, mixed> */
    private function pedidoParaResposta(Pedido $pedido): array
    {
        $cobranca = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->whereNot('status', 'ERRO')
            ->orderByDesc('id')
            ->first();

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
            'pagamento' => $cobranca ? $this->cobrancaParaResposta($cobranca) : null,
            'entrega' => $this->entregaParaResposta($pedido),
        ];
    }

    /** Situação de entrega do pedido (metodo escolhido + rastreio Uber, se houver). */
    private function entregaParaResposta(Pedido $pedido): ?array
    {
        $envio = PedidoEntrega::where('pedido_id', $pedido->id)
            ->orderByDesc('id')
            ->first();

        if (! $pedido->metodo_entrega && ! $envio) {
            return null;
        }

        return [
            'metodo' => $pedido->metodo_entrega,
            'status' => $envio?->status,
            'rastreio_url' => $envio?->tracking_url ?: null,
            'erro' => $envio?->erro ? true : false,
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
