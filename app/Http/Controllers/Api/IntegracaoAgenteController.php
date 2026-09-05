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
use App\Services\Entrega\MelhorEnvioService;
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
            // 05/09 (venda humana no app.ia365): busca livre por nome/telefone do
            // cliente ou número do pedido, e recorte por situação do pagamento.
            'busca' => ['nullable', 'string', 'max:80'],
            'pagamento' => ['nullable', 'string', 'in:pendente,pago'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = (int) ($validated['pagina'] ?? 1);

        $query = Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->with(['cliente', 'unidade:id,nome', 'itens' => fn ($q) => $q->with('produto:id,descricao,foto'), 'vendedor:id,name'])
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['telefone'] ?? null, function ($q, $tel) {
                $digitos = preg_replace('/\D/', '', $tel);
                $q->whereHas('cliente', function ($c) use ($digitos) {
                    $c->where('telefone', 'like', "%{$digitos}%")
                        ->orWhere('whatsapp', 'like', "%{$digitos}%");
                });
            })
            ->when(trim((string) ($validated['busca'] ?? '')) !== '', function ($q) use ($validated) {
                $termo = trim($validated['busca']);
                $digitos = preg_replace('/\D/', '', $termo);
                $q->where(function ($w) use ($termo, $digitos) {
                    if ($digitos !== '' && ctype_digit($termo)) {
                        $w->orWhere('numero', (int) $termo);
                    }
                    $w->orWhereHas('cliente', function ($c) use ($termo, $digitos) {
                        $c->where('nome_razao_social', 'like', "%{$termo}%");
                        if (strlen($digitos) >= 4) {
                            $c->orWhere('telefone', 'like', "%{$digitos}%")
                                ->orWhere('whatsapp', 'like', "%{$digitos}%");
                        }
                    });
                });
            })
            ->when($validated['pagamento'] ?? null, function ($q, $pg) {
                // "pago" = cobrança PIX concluída OU anotação de pagamento no pedido
                // (cartão Asaas / recebido por fora) — o critério é o mesmo que a
                // situação "pago" da resposta (pagamentoResumo).
                $sub = PedidoCobranca::query()->selectRaw('1')
                    ->whereColumn('pedido_cobrancas.pedido_id', 'pedidos.id')
                    ->whereNotNull('pago_em');
                if ($pg === 'pago') {
                    $q->where(function ($w) use ($sub) {
                        $w->whereExists($sub)
                            ->orWhere('observacoes_internas', 'like', '%PAGO%');
                    });
                } else {
                    $q->whereNotExists($sub)
                        ->where(function ($w) {
                            $w->whereNull('observacoes_internas')
                                ->orWhere('observacoes_internas', 'not like', '%PAGO%');
                        })
                        ->whereIn('status', ['rascunho']);
                }
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
            ->with(['cliente', 'unidade:id,nome', 'itens' => fn ($q) => $q->with('produto:id,descricao,foto'), 'vendedor:id,name'])
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
            // 05/09 (venda humana): quem vendeu no painel do app.ia365. Casado
            // pelo nome contra os usuários ATIVOS da empresa; sem match cai no
            // vendedor padrão do agente (comportamento de sempre).
            'vendedor_nome' => ['nullable', 'string', 'max:120'],
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
            // 05/09: serviço do Melhor Envio escolhido na conversa (id da cotação)
            'entrega.servico_id' => ['nullable', 'string', 'max:20'],
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

        $vendedorId = $this->vendedorPorNome($token->empresa_id, $validated['vendedor_nome'] ?? null)
            ?? $config?->vendedor_padrao_id;

        $metodoEntrega = $validated['entrega']['metodo'] ?? null;

        // Frete REPASSADO ao cliente (modelo China Mix: preco = fee/100, o
        // pagamento cobra subtotal + frete). Cotação ANTES da transação (rede
        // fora de lock); falha/indisponível ⇒ pedido segue SEM frete e o
        // atendente combina — a trava de frete nunca derruba a venda.
        $freteValor = null;
        $fretePrazoMin = null;
        $frete = null;
        if ($metodoEntrega === 'entrega') {
            // 05/09: mesma cidade → Uber Direct; outra cidade → Melhor Envio pelas
            // medidas dos produtos (resolverEntrega decide e nunca lança). Falha ou
            // indisponível ⇒ pedido segue SEM frete e o atendente combina — a
            // trava de frete nunca derruba a venda.
            $itensCotacao = [];
            foreach ($validated['itens'] as $i) {
                $p = $produtos[$i['produto_id']] ?? null;
                if ($p) {
                    $itensCotacao[] = ['produto' => $p, 'quantidade' => (float) $i['quantidade'], 'valor_unitario' => (float) $p->preco_venda];
                }
            }
            try {
                $frete = $this->resolverEntrega($token->empresa_id, $unidade, $validated['entrega'], $itensCotacao, $validated['entrega']['servico_id'] ?? null);
            } catch (\Throwable $e) {
                Log::channel('integracao')->warning('Agente IA: pedido segue sem frete (cotação falhou)', [
                    'empresa_id' => $token->empresa_id,
                    'erro' => $e->getMessage(),
                ]);
                $frete = null;
            }
            if ($frete && ! empty($frete['disponivel'])) {
                $freteValor = (float) $frete['valor'];
                $fretePrazoMin = $frete['prazo_minutos'] ?? null;
            } else {
                $frete = null;
            }
        }

        $pedido = DB::transaction(function () use ($validated, $token, $produtos, $vendedorId, $unidade, $metodoEntrega, $freteValor, $frete) {
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
                'vendedor_id' => $vendedorId,
                'numero' => $ultimoNumero ? $ultimoNumero + 1 : 1,
                'subtotal' => round($subtotal, 2),
                'desconto_percentual' => 0,
                'desconto_valor' => 0,
                // Frete entra no total ⇒ PIX/cartão (que cobram $pedido->total)
                // já saem com a entrega embutida, igual ao China Mix.
                'total' => round($subtotal + ($freteValor ?? 0), 2),
                'status' => StatusPedido::Rascunho,
                'metodo_entrega' => $metodoEntrega,
                'frete_valor' => $freteValor,
                'frete_provedor' => $frete['provedor'] ?? null,
                'frete_servico_id' => $frete['servico_id'] ?? null,
                'frete_servico_nome' => $frete['servico_nome'] ?? null,
                'frete_prazo_dias' => $frete['prazo_dias'] ?? null,
                // Canal da venda (05/09): pedido do agente = conversa de WhatsApp.
                'canal' => \App\Enums\CanalVenda::Whatsapp->value,
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
        // 'automatica' = frete cotado e embutido no total ⇒ o despacho sai
        // sozinho no pagamento; senão a promessa é o humano.
        $entrega = null;
        if ($metodoEntrega === 'retirada') {
            $entrega = ['metodo' => 'retirada', 'automatica' => false, 'frete_valor' => null,
                'mensagem' => 'Retirada combinada na loja.'];
        } elseif ($metodoEntrega === 'entrega') {
            if ($freteValor !== null && ($frete['provedor'] ?? null) === 'melhor_envio') {
                // 05/09: outra cidade — postagem pela etiqueta do Melhor Envio (a
                // loja gera no painel; automação da etiqueta é a fase 2).
                $entrega = ['metodo' => 'entrega', 'automatica' => false, 'provedor' => 'melhor_envio',
                    'frete_valor' => $freteValor, 'servico' => $frete['servico_nome'],
                    'transportadora' => $frete['transportadora'], 'prazo_dias' => $frete['prazo_dias'],
                    'mensagem' => 'Frete por ' . $frete['servico_nome']
                        . ($frete['transportadora'] ? ' (' . $frete['transportadora'] . ')' : '')
                        . ' de R$ ' . number_format($freteValor, 2, ',', '.')
                        . ' já incluído no total; prazo de até ' . (int) $frete['prazo_dias']
                        . ' dias úteis após a postagem, que acontece assim que o pagamento confirmar.'];
            } elseif ($freteValor !== null) {
                $entrega = ['metodo' => 'entrega', 'automatica' => true, 'provedor' => 'uber_direct', 'frete_valor' => $freteValor,
                    'prazo_minutos' => $fretePrazoMin,
                    'mensagem' => 'Entrega de R$ ' . number_format($freteValor, 2, ',', '.')
                        . ' já incluída no total; ela é acionada automaticamente assim que o pagamento confirmar.'];
            } else {
                $entrega = ['metodo' => 'entrega', 'automatica' => false, 'frete_valor' => null,
                    'mensagem' => 'Um atendente vai combinar a entrega com você (o frete não está incluído no total).'];
            }
        }

        $mensagem = "Pedido #{$pedido->numero} registrado! Total R$ " . number_format((float) $pedido->total, 2, ',', '.')
            . ($freteValor !== null
                ? ' (produtos R$ ' . number_format((float) $pedido->subtotal, 2, ',', '.')
                    . ' + entrega R$ ' . number_format($freteValor, 2, ',', '.') . ')'
                : '') . '.';
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
     * Cota a entrega para um endereço, ANTES de fechar o pedido.
     *
     * Ferramenta do agente (intenção COTAR ENTREGA). Sempre responde 200 com
     * `disponivel` true/false — o agente lê o resultado e se adapta (mesmo
     * desenho response-driven do pix/cartao_link no POST /pedidos).
     *
     * 05/09/2026 — dois provedores, decididos em resolverEntrega():
     *   - mesma cidade da loja → Uber Direct (`valor` + `prazo_minutos`);
     *   - outra cidade → Melhor Envio pelas medidas dos produtos (`opcoes`
     *     com até 3 serviços — PAC/SEDEX/Jadlog… — `valor` da mais barata ou
     *     do `servico_id` pedido, `prazo_dias`).
     * `valor` é o PREÇO ao cliente (repasse 1:1, decisão do Dennis 25/08); no
     * CRIAR PEDIDO ele entra no total e o PIX/cartão já cobram com a entrega.
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
            // 05/09: itens para o Melhor Envio cotar pelas medidas (opcional —
            // sem eles vale o pacote padrão da loja × 1)
            'itens' => ['nullable', 'array', 'max:30'],
            'itens.*.produto_id' => ['nullable', 'integer'],
            'itens.*.quantidade' => ['nullable', 'numeric', 'min:0.001', 'max:9999'],
            'servico_id' => ['nullable', 'string', 'max:20'],
        ]);

        $unidade = Unidade::withoutGlobalScope(EmpresaScope::class)
            ->where('empresa_id', $token->empresa_id)
            ->whereKey($validated['unidade_id'])
            ->first();

        if (! $unidade) {
            return response()->json(['erro' => 'Loja não encontrada.'], 404);
        }

        $itens = $this->itensParaCotacao($token->empresa_id, $validated['itens'] ?? []);

        return response()->json([
            'dados' => $this->resolverEntrega($token->empresa_id, $unidade, $validated, $itens, $validated['servico_id'] ?? null),
        ]);
    }

    /**
     * Itens da conversa → produtos da EMPRESA do token (id de outra empresa
     * é ignorado, não vaza medida nem preço).
     *
     * @return array<int, array{produto: Produto, quantidade: float, valor_unitario: float}>
     */
    private function itensParaCotacao(int $empresaId, array $itens): array
    {
        $ids = collect($itens)->pluck('produto_id')->filter()->unique()->values();
        $produtos = $ids->isEmpty()
            ? collect()
            : Produto::withoutGlobalScope(EmpresaScope::class)
                ->where('empresa_id', $empresaId)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

        $out = [];
        foreach ($itens as $i) {
            $p = $produtos[$i['produto_id'] ?? 0] ?? null;
            if ($p) {
                $out[] = ['produto' => $p, 'quantidade' => (float) ($i['quantidade'] ?? 1), 'valor_unitario' => (float) $p->preco_venda];
            }
        }

        return $out;
    }

    /**
     * Decide o provedor e cota. Nunca lança: devolve `disponivel` false com
     * `motivo`/`mensagem` para o agente se adaptar.
     *
     * Uber é entrega LOCAL: vale quando o CEP está nas faixas cadastradas;
     * sem faixas cadastradas, vale para a mesma cidade da loja (ViaCEP) — e
     * só deixa de valer se o Melhor Envio estiver conectado (senão o
     * comportamento é o de sempre). Uber que falha cai no Melhor Envio quando
     * ele existe (Correios também entregam na cidade).
     *
     * @param  array<string, mixed>  $end  cep/logradouro/numero/bairro/cidade/uf
     * @param  array<int, array{produto: ?Produto, quantidade: float, valor_unitario: float}>  $itens
     * @return array<string, mixed>
     */
    private function resolverEntrega(int $empresaId, Unidade $unidade, array $end, array $itens = [], ?string $servicoId = null): array
    {
        $indisponivel = fn (string $motivo, string $mensagem) => ['disponivel' => false, 'motivo' => $motivo, 'mensagem' => $mensagem];

        $uber = UberDirectService::ativoPara($empresaId);
        $me = MelhorEnvioService::ativoPara($empresaId);
        if (! $uber && ! $me) {
            return $indisponivel('entrega_desativada',
                'Entrega automática não está habilitada nesta loja — um atendente pode combinar a entrega.');
        }

        $cep = preg_replace('/\D/', '', (string) ($end['cep'] ?? ''));

        $local = false;
        if ($uber && $uber->cepAtendido($cep)) {
            $local = $uber->temFaixas() || ! $me || MelhorEnvioService::mesmaCidade($cep, $unidade);
        }

        if ($local) {
            // Cidade/UF caem na unidade quando não informadas (entrega local).
            $rua = trim(($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? ''));
            $dropoff = trim(sprintf(
                '%s, %s, %s, %s, BR',
                $rua !== '' ? $rua : ($end['bairro'] ?? ''),
                $end['cidade'] ?? $unidade->cidade,
                mb_strtoupper($end['uf'] ?? $unidade->uf),
                $cep
            ), ', ');

            try {
                $quote = $uber->cotar($unidade, $dropoff);
                EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_UBER_DIRECT)?->update(['ultima_falha' => null]);
                $valor = round($quote['fee'] / 100, 2);

                return [
                    'disponivel' => true,
                    'provedor' => 'uber_direct',
                    'valor' => $valor,
                    'prazo_minutos' => $quote['duration'],
                    'mensagem' => 'Entrega disponível: R$ ' . number_format($valor, 2, ',', '.')
                        . ', chega em ~' . (int) $quote['duration'] . ' min após a confirmação do pagamento. O frete é somado ao total do pedido.',
                ];
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao cotar entrega Uber', [
                    'empresa_id' => $empresaId,
                    'erro' => $e->getMessage(),
                ]);
                // Registra no gateway p/ o card da aba Integração denunciar a
                // credencial quebrada (mesma coluna do "Testar conexão").
                EmpresaGateway::ativoPara($empresaId, EmpresaGateway::PROVEDOR_UBER_DIRECT)
                    ?->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);
                if (! $me) {
                    return $indisponivel('erro_cotacao',
                        'Não consegui cotar a entrega agora — um atendente pode combinar a entrega.');
                }
                // Uber falhou, mas há Melhor Envio: segue para ele.
            }
        }

        if ($me) {
            try {
                $opcoes = $me->cotar($unidade, $cep, $itens, $servicoId);
                $me->gateway()->update(['ultima_falha' => null]);
            } catch (\Throwable $e) {
                Log::channel('integracao')->error('Agente IA: falha ao cotar frete Melhor Envio', [
                    'empresa_id' => $empresaId,
                    'erro' => $e->getMessage(),
                ]);
                $me->gateway()->update(['ultima_falha' => mb_substr($e->getMessage(), 0, 1000)]);

                return $indisponivel('erro_cotacao',
                    'Não consegui cotar o frete agora — um atendente pode combinar a entrega.');
            }
            if ($opcoes === []) {
                return $indisponivel('sem_servico',
                    'Nenhuma transportadora atende este CEP com as medidas informadas — um atendente pode combinar a entrega, ou o pedido pode ser para retirada.');
            }

            $escolhida = $opcoes[0];
            if ($servicoId !== null && $servicoId !== '') {
                foreach ($opcoes as $op) {
                    if ($op['servico_id'] === (string) $servicoId) {
                        $escolhida = $op;
                        break;
                    }
                }
            }
            $lista = array_slice($opcoes, 0, 3);
            $partes = [];
            foreach ($lista as $n => $op) {
                $partes[] = ($n + 1) . ') ' . $op['nome'] . ($op['transportadora'] !== '' ? ' — ' . $op['transportadora'] : '')
                    . ': R$ ' . number_format($op['valor'], 2, ',', '.') . ', até ' . $op['prazo_dias'] . ' dias úteis (servico_id ' . $op['servico_id'] . ')';
            }

            return [
                'disponivel' => true,
                'provedor' => 'melhor_envio',
                'valor' => $escolhida['valor'],
                'prazo_dias' => $escolhida['prazo_dias'],
                'servico_id' => $escolhida['servico_id'],
                'servico_nome' => $escolhida['nome'],
                'transportadora' => $escolhida['transportadora'],
                'opcoes' => $lista,
                'mensagem' => 'Frete para o CEP ' . substr($cep, 0, 5) . '-' . substr($cep, 5) . ': ' . implode('; ', $partes)
                    . '. O frete é somado ao total do pedido e a postagem acontece após a confirmação do pagamento.',
            ];
        }

        return $indisponivel('cep_fora_da_area',
            'Este CEP está fora da área de entrega — um atendente pode combinar outra forma, ou o pedido pode ser para retirada.');
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
            'subtotal' => (float) $pedido->subtotal,
            'canal' => $pedido->canal?->value,
            'condicao_pagamento' => $pedido->condicao_pagamento,
            'vendedor' => $pedido->vendedor?->name,
            'loja' => $pedido->unidade?->nome,
            'cliente' => $pedido->cliente ? [
                'id' => (string) $pedido->cliente->id,
                'nome' => $pedido->cliente->nome_razao_social,
                'telefone' => $pedido->cliente->whatsapp ?: $pedido->cliente->telefone,
                'cpf_cnpj' => $pedido->cliente->cpf_cnpj,
                'endereco' => $this->enderecoTexto($pedido->cliente),
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
            // 05/09: situação consolidada do pagamento (PIX Sicredi, cartão Asaas
            // ou recebido por fora) — é o que a aba Pedidos do app.ia365 mostra.
            'pagamento_resumo' => $this->pagamentoResumo($pedido, $cobranca),
            'entrega' => $this->entregaParaResposta($pedido),
            'acoes' => $this->acoesPermitidas($pedido),
        ];
    }

    /* ---------------------------------------------------------------- */
    /*  Venda humana pelo app.ia365 (05/09/2026)                          */
    /*                                                                    */
    /*  O atendente opera o pedido pelo painel do CRM: re-consulta o PIX, */
    /*  registra pagamento recebido por fora, cancela e marca entregue.   */
    /*  As transições espelham as do PedidoController::updateStatus       */
    /*  (rascunho → confirmado/cancelado; confirmado → cancelado;         */
    /*  faturado → entregue) — faturar continua SÓ no ERP (estoque/fiscal).*/
    /* ---------------------------------------------------------------- */

    /**
     * POST /pedidos/{id}/verificar-pagamento — re-consulta a cobrança PIX no
     * Sicredi agora (mesma rotina do webhook e do cron) e devolve o pedido.
     */
    public function verificarPagamento(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $pedido = $this->pedidoDaEmpresa($token->empresa_id, $id);
        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        $cobranca = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->whereNot('status', 'ERRO')
            ->orderByDesc('id')
            ->first();

        $consultou = false;
        if ($cobranca && ! $cobranca->paga()) {
            try {
                app(PixPedidoService::class)->sincronizarCobranca($cobranca);
                $consultou = true;
            } catch (\Throwable $e) {
                Log::channel('integracao')->warning('Venda humana: falha ao re-consultar PIX', [
                    'pedido_id' => $pedido->id, 'erro' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'dados' => array_merge($this->pedidoParaResposta($pedido->fresh(['cliente', 'unidade', 'itens.produto', 'vendedor'])), [
                'consultou_psp' => $consultou,
            ]),
        ]);
    }

    /**
     * POST /pedidos/{id}/confirmar-pagamento — pagamento recebido POR FORA
     * (dinheiro, maquininha, PIX manual, transferência): rascunho → confirmado
     * com a anotação de quem confirmou; dispara o despacho automático igual
     * ao PIX. NÃO cria conta a receber (já foi pago) e NÃO fatura.
     */
    public function confirmarPagamento(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'forma' => ['required', 'string', 'in:dinheiro,cartao_maquininha,pix_manual,transferencia,outro'],
            'observacao' => ['nullable', 'string', 'max:500'],
            'autor' => ['nullable', 'string', 'max:120'],
        ]);

        $pedido = $this->pedidoDaEmpresa($token->empresa_id, $id);
        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        if ($pedido->status === StatusPedido::Cancelado) {
            return response()->json(['erro' => 'Pedido cancelado não pode receber pagamento.'], 422);
        }

        $formas = [
            'dinheiro' => ['Dinheiro', 'a_vista'],
            'cartao_maquininha' => ['Cartão na maquininha', 'cartao'],
            'pix_manual' => ['PIX (conferido manualmente)', 'pix'],
            'transferencia' => ['Transferência', 'a_vista'],
            'outro' => ['Outra forma', 'a_vista'],
        ];
        [$rotulo, $condicao] = $formas[$validated['forma']];
        $autor = trim((string) ($validated['autor'] ?? '')) ?: 'painel app.ia365';

        $nota = sprintf(
            "\nPAGO POR FORA (%s) em %s — R$ %s, confirmado por %s.%s",
            $rotulo,
            now()->format('d/m/Y H:i'),
            number_format((float) $pedido->total, 2, ',', '.'),
            $autor,
            isset($validated['observacao']) && trim($validated['observacao']) !== '' ? ' Obs.: ' . trim($validated['observacao']) : ''
        );

        DB::transaction(function () use ($pedido, $nota, $condicao) {
            if ($pedido->status === StatusPedido::Rascunho) {
                $pedido->status = StatusPedido::Confirmado;
            }
            if (! $pedido->condicao_pagamento) {
                $pedido->condicao_pagamento = $condicao;
            }
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '') . $nota);
            $pedido->save();
        });

        // Mesmo gatilho do PIX/cartão: pagou → despacho automático (o job
        // ignora retirada, Melhor Envio e pedido sem gateway; falha lá nunca
        // desfaz a confirmação).
        \App\Jobs\DespacharEntregaUberJob::dispatch($pedido->id, (int) $pedido->empresa_id);

        Log::channel('integracao')->info('Venda humana: pagamento confirmado por fora', [
            'empresa_id' => $pedido->empresa_id, 'pedido_id' => $pedido->id,
            'numero' => $pedido->numero, 'forma' => $validated['forma'], 'autor' => $autor,
        ]);

        return response()->json(['dados' => $this->pedidoParaResposta($pedido->fresh(['cliente', 'unidade', 'itens.produto', 'vendedor']))]);
    }

    /**
     * POST /pedidos/{id}/cancelar — rascunho/confirmado → cancelado com motivo.
     * Faturado fica de fora (estoque e fiscal só se desfazem no ERP). A
     * cobrança PIX ativa é marcada CANCELADA aqui (o Sicredi expira sozinho).
     */
    public function cancelarPedido(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:300'],
            'autor' => ['nullable', 'string', 'max:120'],
        ]);

        $pedido = $this->pedidoDaEmpresa($token->empresa_id, $id);
        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        if (! in_array($pedido->status, [StatusPedido::Rascunho, StatusPedido::Confirmado], true)) {
            return response()->json([
                'erro' => "Pedido {$pedido->status->label()} só pode ser cancelado no ERP.",
            ], 422);
        }

        $cobrancaPaga = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->whereNotNull('pago_em')
            ->exists();
        if ($cobrancaPaga) {
            return response()->json(['erro' => 'Este pedido já tem PIX pago — cancele pelo ERP para tratar o estorno.'], 422);
        }

        $autor = trim((string) ($validated['autor'] ?? '')) ?: 'painel app.ia365';
        $motivo = trim((string) ($validated['motivo'] ?? ''));

        DB::transaction(function () use ($pedido, $autor, $motivo) {
            $pedido->status = StatusPedido::Cancelado;
            $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '')
                . sprintf("\nCANCELADO em %s por %s%s.", now()->format('d/m/Y H:i'), $autor, $motivo !== '' ? " — motivo: {$motivo}" : ''));
            $pedido->save();

            PedidoCobranca::where('empresa_id', $pedido->empresa_id)
                ->where('pedido_id', $pedido->id)
                ->whereNull('pago_em')
                ->whereNotIn('status', ['ERRO'])
                ->update(['status' => 'CANCELADA']);
        });

        Log::channel('integracao')->info('Venda humana: pedido cancelado', [
            'empresa_id' => $pedido->empresa_id, 'pedido_id' => $pedido->id,
            'numero' => $pedido->numero, 'autor' => $autor, 'motivo' => $motivo,
        ]);

        return response()->json(['dados' => $this->pedidoParaResposta($pedido->fresh(['cliente', 'unidade', 'itens.produto', 'vendedor']))]);
    }

    /**
     * POST /pedidos/{id}/entregue — faturado → entregue (mesma transição do
     * ERP). Confirmado ainda não faturado devolve 422 explicando.
     */
    public function marcarEntregue(Request $request, int $id): JsonResponse
    {
        $token = $this->token($request);

        if ($erro = $this->exigirAgenteAtivo($token)) {
            return $erro;
        }

        $validated = $request->validate([
            'autor' => ['nullable', 'string', 'max:120'],
        ]);

        $pedido = $this->pedidoDaEmpresa($token->empresa_id, $id);
        if (! $pedido) {
            return response()->json(['erro' => 'Pedido não encontrado.'], 404);
        }

        if ($pedido->status !== StatusPedido::Faturado) {
            return response()->json([
                'erro' => $pedido->status === StatusPedido::Confirmado
                    ? 'Fature o pedido no ERP antes de marcar como entregue (é o faturamento que baixa o estoque).'
                    : "Pedido {$pedido->status->label()} não pode ser marcado como entregue.",
            ], 422);
        }

        $autor = trim((string) ($validated['autor'] ?? '')) ?: 'painel app.ia365';
        $pedido->status = StatusPedido::Entregue;
        $pedido->observacoes_internas = trim(($pedido->observacoes_internas ?? '')
            . sprintf("\nENTREGUE em %s (marcado por %s).", now()->format('d/m/Y H:i'), $autor));
        $pedido->save();

        Log::channel('integracao')->info('Venda humana: pedido marcado entregue', [
            'empresa_id' => $pedido->empresa_id, 'pedido_id' => $pedido->id, 'numero' => $pedido->numero, 'autor' => $autor,
        ]);

        return response()->json(['dados' => $this->pedidoParaResposta($pedido->fresh(['cliente', 'unidade', 'itens.produto', 'vendedor']))]);
    }

    private function pedidoDaEmpresa(int $empresaId, int $id): ?Pedido
    {
        return Pedido::withoutGlobalScope(EmpresaScope::class)
            ->withoutGlobalScope(UnidadeScope::class)
            ->where('empresa_id', $empresaId)
            ->with(['cliente', 'unidade:id,nome', 'itens' => fn ($q) => $q->with('produto:id,descricao,foto'), 'vendedor:id,name'])
            ->whereKey($id)
            ->first();
    }

    private function vendedorPorNome(int $empresaId, ?string $nome): ?int
    {
        $nome = trim((string) $nome);
        if ($nome === '') {
            return null;
        }

        $user = \App\Models\User::query()
            ->where('empresa_id', $empresaId)
            ->where('status', 'ativo')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($nome)])
            ->orderBy('id')
            ->first();

        return $user?->id;
    }

    private function enderecoTexto(Cliente $cliente): ?string
    {
        $linha = trim(implode(', ', array_filter([
            trim(implode(' ', array_filter([$cliente->logradouro, $cliente->numero]))),
            $cliente->complemento,
            $cliente->bairro,
            trim(implode('/', array_filter([$cliente->cidade, $cliente->uf]))),
        ])));
        if ($cliente->cep) {
            $cep = preg_replace('/\D/', '', (string) $cliente->cep);
            $linha .= ($linha !== '' ? ' — ' : '') . 'CEP ' . (strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep);
        }

        return $linha !== '' ? $linha : null;
    }

    /**
     * Situação consolidada do pagamento: 'pago' | 'aguardando' | 'sem_cobranca'.
     * Fonte: cobrança PIX (pago_em) ou anotação de pagamento no pedido
     * (cartão Asaas "CARTÃO PAGO" / recebido por fora "PAGO POR FORA").
     */
    private function pagamentoResumo(Pedido $pedido, ?PedidoCobranca $cobranca): array
    {
        $obs = (string) $pedido->observacoes_internas;
        if ($cobranca?->paga()) {
            return ['situacao' => 'pago', 'via' => 'pix', 'em' => $cobranca->pago_em?->format('Y-m-d H:i')];
        }
        if (preg_match('/CARTÃO PAGO via Asaas em (\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/u', $obs, $m)) {
            return ['situacao' => 'pago', 'via' => 'cartao', 'em' => $this->dataBr($m[1])];
        }
        if (preg_match('/PAGO POR FORA \(([^)]+)\) em (\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/u', $obs, $m)) {
            return ['situacao' => 'pago', 'via' => 'manual', 'forma' => $m[1], 'em' => $this->dataBr($m[2])];
        }
        if ($pedido->status === StatusPedido::Cancelado) {
            return ['situacao' => 'cancelado', 'via' => null, 'em' => null];
        }
        if ($cobranca && in_array($cobranca->status, ['ATIVA'], true)) {
            return ['situacao' => 'aguardando', 'via' => 'pix', 'em' => null, 'expira_em' => $cobranca->expira_em?->format('Y-m-d H:i')];
        }
        // Confirmado/faturado/entregue sem rastro de pagamento = fechado no ERP
        if ($pedido->status !== StatusPedido::Rascunho) {
            return ['situacao' => 'pago', 'via' => 'erp', 'em' => null];
        }

        return ['situacao' => 'sem_cobranca', 'via' => null, 'em' => null];
    }

    private function dataBr(string $br): ?string
    {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('d/m/Y H:i', $br)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /** O que o painel pode fazer com este pedido (o servidor é quem manda). */
    private function acoesPermitidas(Pedido $pedido): array
    {
        $temPix = SicrediPixService::paraEmpresa((int) $pedido->empresa_id) !== null;
        $pixPago = PedidoCobranca::where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)->whereNotNull('pago_em')->exists();

        return [
            'gerar_pix' => $temPix && $pedido->status === StatusPedido::Rascunho && ! $pixPago,
            'verificar_pagamento' => $temPix && $pedido->status === StatusPedido::Rascunho,
            'confirmar_pagamento' => $pedido->status === StatusPedido::Rascunho,
            'cancelar' => in_array($pedido->status, [StatusPedido::Rascunho, StatusPedido::Confirmado], true) && ! $pixPago,
            'marcar_entregue' => $pedido->status === StatusPedido::Faturado,
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
            'frete_valor' => $pedido->frete_valor !== null ? (float) $pedido->frete_valor : null,
            'provedor' => $pedido->frete_provedor,
            'servico' => $pedido->frete_servico_nome,
            'prazo_dias' => $pedido->frete_prazo_dias,
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
