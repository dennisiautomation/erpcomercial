<?php

namespace App\Http\Controllers\App;

use App\Enums\CanalVenda;
use App\Enums\StatusPedido;
use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\AdquirenteTaxa;
use App\Models\ConfiguracaoFiscal;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Services\SaldoEstoque;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use App\Services\FocusNFe\NFeService;
use App\Services\LancamentoCaixaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $query = Pedido::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->with(['cliente:id,nome_razao_social,cpf_cnpj', 'vendedor:id,name', 'itens']);

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('nome_razao_social', 'like', "%{$busca}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $pedidos = $query->latest()->paginate(20)->withQueryString();

        // Summary stats
        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');
        $stats = [
            'total_pendente' => Pedido::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)
                ->whereIn('status', [StatusPedido::Rascunho, StatusPedido::Confirmado])->sum('total'),
            'count_rascunho' => Pedido::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusPedido::Rascunho)->count(),
            'count_confirmado' => Pedido::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusPedido::Confirmado)->count(),
            'count_faturado' => Pedido::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusPedido::Faturado)->count(),
            'count_entregue' => Pedido::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId)->where('status', StatusPedido::Entregue)->count(),
        ];

        return view('app.pedidos.index', compact('pedidos', 'stats'));
    }

    public function create()
    {
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.pedidos.create', compact('vendedores', 'produtos', 'servicos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'vendedor_id'             => 'nullable|exists:users,id',
            'condicao_pagamento'      => 'nullable|string|max:255',
            'canal'                   => 'nullable|' . CanalVenda::regraIn(),
            'desconto_percentual'     => 'nullable|numeric|min:0|max:100',
            'desconto_valor'          => 'nullable|numeric|min:0',
            'observacoes_internas'    => 'nullable|string|max:2000',
            'observacoes_externas'    => 'nullable|string|max:2000',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'nullable|exists:produtos,id',
            'itens.*.servico_id'      => 'nullable|exists:servicos,id',
            'itens.*.descricao'       => 'nullable|string|max:500',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request) {
            $empresaId = session('empresa_id');
            $unidadeId = session('unidade_id');

            $ultimoNumero = Pedido::where('empresa_id', $empresaId)->max('numero');
            $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $descricao = $item['descricao'] ?? '';
                if (!empty($item['produto_id'])) {
                    $produto = Produto::find($item['produto_id']);
                    $descricao = $descricao ?: $produto->descricao;
                } elseif (!empty($item['servico_id'])) {
                    $servico = Servico::find($item['servico_id']);
                    $descricao = $descricao ?: $servico->descricao;
                }

                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'] ?? null,
                    'servico_id'         => $item['servico_id'] ?? null,
                    'descricao'          => $descricao,
                    'quantidade'         => $qtd,
                    'preco_unitario'     => $precoUnit,
                    'desconto_percentual'=> $descontoPerc,
                    'desconto_valor'     => $descontoValor,
                    'total'              => $totalItem,
                ];

                $subtotal += $totalItem;
            }

            $descontoGeralPerc  = $request->desconto_percentual ?? 0;
            $descontoGeralValor = $request->desconto_valor ?? round($subtotal * ($descontoGeralPerc / 100), 2);
            $total = round($subtotal - $descontoGeralValor, 2);

            $pedido = Pedido::create([
                'empresa_id'          => $empresaId,
                'unidade_id'          => $unidadeId,
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => $request->vendedor_id,
                'numero'              => $numero,
                'condicao_pagamento'  => $request->condicao_pagamento,
                // Canal da venda (05/09): a tela pré-seleciona Presencial; o Gersen lê
                'canal'               => $request->canal ?: CanalVenda::Presencial->value,
                'subtotal'            => $subtotal,
                'desconto_percentual' => $descontoGeralPerc,
                'desconto_valor'      => $descontoGeralValor,
                'total'               => $total,
                'status'              => StatusPedido::Rascunho,
                'observacoes_internas'=> $request->observacoes_internas,
                'observacoes_externas'=> $request->observacoes_externas,
            ]);

            foreach ($itensData as $itemData) {
                $pedido->itens()->create($itemData);
            }
        });

        return redirect()->route('app.pedidos.index')
            ->with('success', 'Pedido criado com sucesso!');
    }

    public function show(Pedido $pedido)
    {
        $pedido->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'orcamento', 'venda', 'unidade']);

        // Prontidão fiscal da unidade do pedido — o modal de faturamento
        // mostra o que falta para NFC-e/NF-e em vez de falhar depois.
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $pedido->empresa_id)
            ->where('unidade_id', $pedido->unidade_id)
            ->first();

        $faltasComuns = [];
        if (! $config || ! $config->emissao_fiscal_ativa) {
            $faltasComuns[] = 'ativar a emissão fiscal';
        } else {
            if (! $config->certificado_validade) {
                $faltasComuns[] = 'certificado digital A1';
            }
            if (! $config->responsavel_tecnico_cnpj) {
                $faltasComuns[] = 'responsável técnico';
            }
        }

        $faltasNfe = $faltasComuns;
        if ($config && $config->emissao_fiscal_ativa && ! $config->emite_nfe) {
            $faltasNfe[] = 'habilitar NF-e na Configuração Fiscal';
        }

        $faltasNfce = $faltasComuns;
        if ($config && $config->emissao_fiscal_ativa) {
            if (! $config->emite_nfce) {
                $faltasNfce[] = 'habilitar NFC-e na Configuração Fiscal';
            }
            if (! $config->csc_nfce) {
                $faltasNfce[] = 'CSC da NFC-e (portal SEFAZ)';
            }
        }

        $fiscalPedido = [
            'nfe_ok'      => empty($faltasNfe),
            'nfce_ok'     => empty($faltasNfce),
            'faltas_nfe'  => $faltasNfe,
            'faltas_nfce' => $faltasNfce,
        ];

        // Faturar pergunta a forma de pagamento (27/10/2026, pedido da DK): a
        // venda e a NFC-e saíam "a definir". Sugere a condição do pedido e mostra
        // o quadro do orçamento de origem como referência.
        $formaSugerida = self::FORMA_DA_CONDICAO[$pedido->condicao_pagamento ?? ''] ?? null;
        $quadroOrcamento = null;
        if ($pedido->orcamento && $pedido->orcamento->parcelas) {
            $configLoja = \App\Models\ConfiguracaoLoja::daUnidade($pedido->empresa_id, $pedido->unidade_id);
            $frete = (float) $pedido->orcamento->frete_valor;
            $quadroOrcamento = app(\App\Services\OrcamentoPagamentoService::class)->simular(
                (float) $pedido->orcamento->total - $frete,
                $frete,
                (int) $pedido->orcamento->parcelas,
                (float) $pedido->orcamento->desconto_avista_percentual,
                $configLoja,
            );
        }
        $formasFaturar = self::FORMAS_FATURAR;

        return view('app.pedidos.show', compact('pedido', 'fiscalPedido', 'formaSugerida', 'quadroOrcamento', 'formasFaturar'));
    }

    public function edit(Pedido $pedido)
    {
        if ($pedido->status !== StatusPedido::Rascunho) {
            return redirect()->route('app.pedidos.show', $pedido)
                ->with('error', 'Apenas pedidos em rascunho podem ser editados.');
        }

        $pedido->load(['cliente', 'itens.produto', 'itens.servico']);
        $vendedores = User::where('empresa_id', session('empresa_id'))->orderBy('name')->get();
        $produtos = Produto::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();
        $servicos = Servico::where('empresa_id', session('empresa_id'))->where('status', 'ativo')->orderBy('descricao')->get();

        return view('app.pedidos.edit', compact('pedido', 'vendedores', 'produtos', 'servicos'));
    }

    public function update(Request $request, Pedido $pedido)
    {
        if ($pedido->status !== StatusPedido::Rascunho) {
            return back()->with('error', 'Apenas pedidos em rascunho podem ser editados.');
        }

        $request->validate([
            'cliente_id'              => 'required|exists:clientes,id',
            'vendedor_id'             => 'nullable|exists:users,id',
            'condicao_pagamento'      => 'nullable|string|max:255',
            'canal'                   => 'nullable|' . CanalVenda::regraIn(),
            'desconto_percentual'     => 'nullable|numeric|min:0|max:100',
            'desconto_valor'          => 'nullable|numeric|min:0',
            'observacoes_internas'    => 'nullable|string|max:2000',
            'observacoes_externas'    => 'nullable|string|max:2000',
            'itens'                   => 'required|array|min:1',
            'itens.*.produto_id'      => 'nullable|exists:produtos,id',
            'itens.*.servico_id'      => 'nullable|exists:servicos,id',
            'itens.*.descricao'       => 'nullable|string|max:500',
            'itens.*.quantidade'      => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($request, $pedido) {
            $subtotal = 0;
            $itensData = [];

            foreach ($request->itens as $item) {
                $descricao = $item['descricao'] ?? '';
                if (!empty($item['produto_id'])) {
                    $produto = Produto::find($item['produto_id']);
                    $descricao = $descricao ?: $produto->descricao;
                } elseif (!empty($item['servico_id'])) {
                    $servico = Servico::find($item['servico_id']);
                    $descricao = $descricao ?: $servico->descricao;
                }

                $descontoPerc = $item['desconto_percentual'] ?? 0;
                $precoUnit = $item['preco_unitario'];
                $qtd = $item['quantidade'];
                $descontoValor = round($precoUnit * $qtd * ($descontoPerc / 100), 2);
                $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                $itensData[] = [
                    'produto_id'         => $item['produto_id'] ?? null,
                    'servico_id'         => $item['servico_id'] ?? null,
                    'descricao'          => $descricao,
                    'quantidade'         => $qtd,
                    'preco_unitario'     => $precoUnit,
                    'desconto_percentual'=> $descontoPerc,
                    'desconto_valor'     => $descontoValor,
                    'total'              => $totalItem,
                ];

                $subtotal += $totalItem;
            }

            $descontoGeralPerc  = $request->desconto_percentual ?? 0;
            $descontoGeralValor = $request->desconto_valor ?? round($subtotal * ($descontoGeralPerc / 100), 2);
            // Frete do Agente IA (repasse Uber) sobrevive à edição humana —
            // sem esta soma, editar o pedido apagaria o frete do total.
            $total = round($subtotal - $descontoGeralValor + (float) ($pedido->frete_valor ?? 0), 2);

            $pedido->update([
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => $request->vendedor_id,
                'condicao_pagamento'  => $request->condicao_pagamento,
                // Canal da venda (05/09): a tela pré-seleciona Presencial; o Gersen lê
                'canal'               => $request->canal ?: CanalVenda::Presencial->value,
                'subtotal'            => $subtotal,
                'desconto_percentual' => $descontoGeralPerc,
                'desconto_valor'      => $descontoGeralValor,
                'total'               => $total,
                'observacoes_internas'=> $request->observacoes_internas,
                'observacoes_externas'=> $request->observacoes_externas,
            ]);

            $pedido->itens()->forceDelete();
            foreach ($itensData as $itemData) {
                $pedido->itens()->create($itemData);
            }
        });

        return redirect()->route('app.pedidos.show', $pedido)
            ->with('success', 'Pedido atualizado com sucesso!');
    }

    public function updateStatus(Request $request, Pedido $pedido)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $novoStatus = StatusPedido::from($request->status);

        // Validate transitions
        $transicoes = [
            'rascunho'   => ['confirmado', 'cancelado'],
            'confirmado' => ['faturado', 'cancelado'],
            'faturado'   => ['entregue', 'cancelado'],
            'entregue'   => [],
            'cancelado'  => [],
        ];

        $permitidas = $transicoes[$pedido->status->value] ?? [];
        if (!in_array($novoStatus->value, $permitidas)) {
            return back()->with('error', "Transicao de {$pedido->status->label()} para {$novoStatus->label()} nao permitida.");
        }

        DB::transaction(function () use ($pedido, $novoStatus) {
            // When confirmed, create contas_receber
            if ($novoStatus === StatusPedido::Confirmado) {
                ContaReceber::create([
                    'empresa_id'      => $pedido->empresa_id,
                    'unidade_id'      => $pedido->unidade_id,
                    'cliente_id'      => $pedido->cliente_id,
                    'pedido_id'       => $pedido->id,
                    'descricao'       => "Pedido #{$pedido->numero}",
                    'valor'           => $pedido->total,
                    'vencimento'      => now()->addDays(30),
                    'forma_pagamento' => $pedido->condicao_pagamento ?? 'a_definir',
                    'parcela'         => 1,
                    'total_parcelas'  => 1,
                    'status'          => 'pendente',
                ]);
            }

            // When faturado, deduct estoque
            if ($novoStatus === StatusPedido::Faturado) {
                $this->baixarEstoquePedido($pedido);
            }

            $pedido->update(['status' => $novoStatus]);
        });

        return back()->with('success', "Status do pedido atualizado para {$novoStatus->label()}!");
    }

    /**
     * Fatura o pedido escolhendo o documento: nenhum, recibo, cupom fiscal
     * (NFC-e) ou nota fiscal (NF-e mod. 55 — "nota grande"). Gera uma Venda
     * (tipo 'pedido') que carrega o documento e alimenta os relatórios.
     */
    /** Formas aceitas no Faturar — as mesmas chaves do PDV (o fiscal mapeia). */
    private const FORMAS_FATURAR = [
        'dinheiro'       => 'Dinheiro',
        'pix'            => 'PIX',
        'cartao_debito'  => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito',
        'boleto'         => 'Boleto',
        'transferencia'  => 'Transferência',
    ];

    /** Condição de pagamento do pedido → forma sugerida no Faturar. */
    private const FORMA_DA_CONDICAO = [
        'a_vista' => 'dinheiro',
        'pix'     => 'pix',
        'cartao'  => 'cartao_credito',
        'boleto'  => 'boleto',
    ];

    public function faturar(Request $request, Pedido $pedido)
    {
        $request->validate([
            'documento'       => 'required|in:nenhum,recibo,cupom_fiscal,nota_fiscal',
            'forma_pagamento' => 'required|in:' . implode(',', array_keys(self::FORMAS_FATURAR)),
            'parcelas'        => 'nullable|integer|min:1|max:24',
        ], [
            'forma_pagamento.required' => 'Escolha a forma de pagamento.',
        ]);

        if ($pedido->status->value !== 'confirmado') {
            return back()->with('error', 'Apenas pedidos confirmados podem ser faturados.');
        }

        $documento = $request->documento;

        // Pré-requisitos fiscais antes de mexer em estoque/status
        $config = null;
        if (in_array($documento, ['cupom_fiscal', 'nota_fiscal'], true)) {
            $config = ConfiguracaoFiscal::withoutGlobalScopes()
                ->where('empresa_id', $pedido->empresa_id)
                ->where('unidade_id', $pedido->unidade_id)
                ->first();

            $habilitado = $config && $config->emissao_fiscal_ativa
                && ($documento === 'cupom_fiscal' ? $config->emite_nfce : $config->emite_nfe);

            if (! $habilitado) {
                return back()->with('error', 'Emissão fiscal não está ativa (ou o tipo de nota não está habilitado) nesta unidade. Verifique a Configuração Fiscal.');
            }

            if ($documento === 'nota_fiscal' && ! $pedido->cliente_id) {
                return back()->with('error', 'NF-e exige cliente informado no pedido (com endereço completo).');
            }
        }

        $formaPagamento = $request->forma_pagamento;
        $parcelas = $formaPagamento === 'cartao_credito' ? max(1, (int) $request->input('parcelas', 1)) : null;

        // Caixa (28/10/2026, DK Med): a venda do pedido entra no caixa aberto de
        // quem fatura, na loja DO PEDIDO — como no PDV. Sem caixa aberto, não
        // fatura: era assim que as vendas de pedido sumiam do fechamento.
        $caixaSvc = app(LancamentoCaixaService::class);
        $caixa = $caixaSvc->caixaAbertoDoUsuario((int) $pedido->unidade_id, (int) auth()->id());
        if (! $caixa) {
            return back()->with('error', 'Abra o caixa nesta loja antes de faturar: a venda do pedido entra no caixa, como no PDV.');
        }

        $venda = DB::transaction(function () use ($pedido, $formaPagamento, $parcelas, $caixa, $caixaSvc) {
            $ultimoNumero = Venda::withoutGlobalScopes()
                ->where('empresa_id', $pedido->empresa_id)
                ->max('numero');

            $venda = Venda::create([
                'empresa_id'          => $pedido->empresa_id,
                'unidade_id'          => $pedido->unidade_id,
                'pedido_id'           => $pedido->id,
                'cliente_id'          => $pedido->cliente_id,
                'vendedor_id'         => $pedido->vendedor_id ?? auth()->id(),
                'caixa_id'            => $caixa->id,
                'numero'              => $ultimoNumero ? $ultimoNumero + 1 : 1,
                'subtotal'            => $pedido->subtotal,
                'desconto_percentual' => $pedido->desconto_percentual ?? 0,
                'desconto_valor'      => $pedido->desconto_valor ?? 0,
                'total'               => $pedido->total,
                'forma_pagamento'     => $formaPagamento,
                // O total é o do pedido: a forma só é registrada (não aplica
                // desconto à vista nem juros — isso fica no orçamento/pedido).
                'pagamento_detalhes'  => [array_filter([
                    'forma'    => $formaPagamento,
                    'valor'    => (float) $pedido->total,
                    'parcelas' => $parcelas,
                ], fn ($v) => $v !== null)],
                'troco'               => 0,
                'status'              => StatusVenda::Concluida,
                'tipo'                => 'pedido',
                'canal'               => $pedido->canal?->value, // herda do pedido (WhatsApp segue WhatsApp)
                'observacoes'         => "Faturamento do Pedido #{$pedido->numero}",
            ]);

            foreach ($pedido->itens as $item) {
                $venda->itens()->create([
                    'produto_id'          => $item->produto_id,
                    'servico_id'          => $item->servico_id,
                    'descricao'           => $item->descricao,
                    'quantidade'          => $item->quantidade,
                    'preco_unitario'      => $item->preco_unitario,
                    'desconto_percentual' => $item->desconto_percentual ?? 0,
                    'desconto_valor'      => $item->desconto_valor ?? 0,
                    'total'               => $item->total,
                ]);
            }

            // Estoque sai como faturamento do pedido (mesma origem de antes)
            $this->baixarEstoquePedido($pedido);

            // Entrada no caixa (uma por forma, com venda_id: o cancelamento estorna
            // por ele) + baixa da conta a receber aberta na confirmação.
            $caixaSvc->lancarVenda($venda, $caixa, $venda->pagamento_detalhes, (int) auth()->id());
            $this->baixarContaReceberDoPedido($pedido, $venda, $formaPagamento, $parcelas);

            $pedido->update(['status' => StatusPedido::Faturado]);

            return $venda;
        });

        // Emissão fora da transaction: falha fiscal não desfaz o faturamento
        switch ($documento) {
            case 'recibo':
                // Folha A4 do pedido (28/10/2026): antes abria o cupom térmico de
                // 80mm, e a DK Med voltava ao orçamento para ter um impresso decente.
                return redirect()->route('app.pedidos.imprimir', $pedido)
                    ->with('success', "Pedido #{$pedido->numero} faturado! Pedido pronto para impressão.");

            case 'cupom_fiscal':
                try {
                    $client = FocusNFeClient::fromConfig($config);
                    $nota = (new NFCeService($client))->emitir($venda->load(['itens.produto', 'cliente', 'empresa']), $config);

                    return redirect()->route('app.vendas.recibo', $venda)
                        ->with('success', "Pedido #{$pedido->numero} faturado com NFC-e emitida!");
                } catch (\Throwable $e) {
                    Log::error('[Pedido] Erro ao emitir NFC-e no faturamento.', [
                        'pedido_id' => $pedido->id,
                        'venda_id'  => $venda->id,
                        'error'     => $e->getMessage(),
                    ]);

                    return redirect()->route('app.vendas.recibo', $venda)
                        ->with('error', 'Pedido faturado, mas a NFC-e falhou: ' . $e->getMessage() . ' — o recibo está disponível.');
                }

            case 'nota_fiscal':
                try {
                    $client = FocusNFeClient::fromConfig($config);
                    $nota = (new NFeService($client))->emitir($venda->load(['itens.produto', 'cliente', 'empresa']), $config);

                    $avisoEmail = $pedido->cliente?->email
                        ? ' XML e DANFE serão enviados por e-mail ao cliente após a autorização.'
                        : '';

                    return redirect()->route('app.notas-fiscais.show', $nota)
                        ->with('success', "Pedido #{$pedido->numero} faturado! NF-e enviada para autorização." . $avisoEmail);
                } catch (\Throwable $e) {
                    Log::error('[Pedido] Erro ao emitir NF-e no faturamento.', [
                        'pedido_id' => $pedido->id,
                        'venda_id'  => $venda->id,
                        'error'     => $e->getMessage(),
                    ]);

                    return redirect()->route('app.pedidos.show', $pedido)
                        ->with('error', 'Pedido faturado, mas a NF-e falhou: ' . $e->getMessage());
                }
        }

        return redirect()->route('app.pedidos.show', $pedido)
            ->with('success', "Pedido #{$pedido->numero} faturado com sucesso!");
    }

    /**
     * Pedido / protocolo de entrega em A4 (28/10/2026, DK Med). Antes o "Imprimir"
     * mandava a tela do sistema para o papel e o faturar abria o cupom térmico;
     * quem queria um impresso decente voltava ao orçamento — e saía "ORÇAMENTO".
     *
     * Não é a folha do orçamento com outro título: cabeçalho enxuto (nome, CNPJ,
     * telefone, logo pequena), bloco "Entregar para", coluna de conferência,
     * situação do pagamento e canhoto de recebimento destacável. Nome/CNPJ/logo
     * vêm de `cabecalhoOrcamento()` da loja DONA do pedido; o resto do cabeçalho
     * do orçamento (endereço comercial, site, condições, rodapé) fica de fora.
     */
    public function imprimir(Pedido $pedido)
    {
        $pedido->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'orcamento', 'venda', 'unidade']);

        $empresa = \App\Models\Empresa::find($pedido->empresa_id);
        $unidade = \App\Models\Unidade::withoutGlobalScopes()->find($pedido->unidade_id);
        $configLoja = \App\Models\ConfiguracaoLoja::daUnidade($pedido->empresa_id, $pedido->unidade_id);
        $cabecalho = $configLoja->cabecalhoOrcamento($empresa, $unidade);

        // Forma de pagamento: a da venda, se já faturou; senão a condição do pedido.
        $pagamento = null;
        if ($pedido->venda) {
            $detalhe = collect($pedido->venda->pagamento_detalhes ?? [])->first() ?? [];
            $forma = $pedido->venda->forma_pagamento;
            $pagamento = self::FORMAS_FATURAR[$forma] ?? ucfirst(str_replace('_', ' ', (string) $forma));
            if (! empty($detalhe['parcelas']) && (int) $detalhe['parcelas'] > 1) {
                $pagamento .= ' em ' . (int) $detalhe['parcelas'] . 'x';
            }
        } elseif ($pedido->condicao_pagamento) {
            $pagamento = self::CONDICOES_LABELS[$pedido->condicao_pagamento]
                ?? ucfirst(str_replace('_', ' ', $pedido->condicao_pagamento));
        }

        // Telefone da loja: o informado para impressos, senão o da unidade/empresa
        $telefone = $configLoja->orcamento_telefone ?: ($unidade?->telefone ?: $empresa?->telefone);
        $telefone = filled($telefone) ? \App\Models\ConfiguracaoLoja::formatarTelefone($telefone) : null;

        // Situação do pagamento: o entregador precisa saber se cobra ou não.
        // Cartão já foi pago na maquininha (o pendente é recebível da adquirente).
        if ($pedido->venda) {
            $formaVenda = (string) $pedido->venda->forma_pagamento;
            $aReceber = in_array($formaVenda, ['cartao_credito', 'cartao_debito'], true)
                ? 0.0
                : round((float) ContaReceber::withoutGlobalScopes()
                    ->where('venda_id', $pedido->venda->id)
                    ->whereIn('status', ['pendente', 'vencida'])
                    ->get()
                    ->sum(fn ($c) => (float) $c->valor - (float) $c->valor_pago), 2);
            $situacao = $aReceber > 0.005
                ? ['texto' => 'A receber', 'valor' => $aReceber]
                : ['texto' => 'Pago', 'valor' => null];
        } else {
            $situacao = ['texto' => 'A pagar', 'valor' => (float) $pedido->total];
        }

        // Entrega por app (Uber): entregador e rastreio, quando houver
        $entrega = \App\Models\PedidoEntrega::where('pedido_id', $pedido->id)->latest('id')->first();

        return view('app.pedidos.print', compact('pedido', 'cabecalho', 'telefone', 'pagamento', 'situacao', 'entrega'));
    }

    /** Rótulos das condições de pagamento do pedido (as chaves do select de criar/editar). */
    private const CONDICOES_LABELS = [
        'a_vista'   => 'À vista',
        'pix'       => 'PIX',
        'cartao'    => 'Cartão',
        'boleto'    => 'Boleto',
        '30_dias'       => '30 dias',
        '30_60_dias'    => '30/60 dias',
        '30_60_90_dias' => '30/60/90 dias',
    ];

    /**
     * Baixa a conta a receber aberta na confirmação do pedido (28/10/2026).
     *
     * Até aqui ela ficava pendente para sempre: o faturar não a tocava. Cartão
     * com regra de adquirente vira recebível previsto (D+prazo, líquido da taxa,
     * parcelas a cada 30 dias) — a mesma regra do PDV; as demais formas ficam
     * pagas na hora. Sem conta (pedido confirmado pela API do agente), nasce
     * uma já paga, como na venda de balcão.
     */
    private function baixarContaReceberDoPedido(Pedido $pedido, Venda $venda, string $forma, ?int $parcelas): void
    {
        $conta = ContaReceber::withoutGlobalScopes()
            ->where('empresa_id', $pedido->empresa_id)
            ->where('pedido_id', $pedido->id)
            ->where('status', 'pendente')
            ->orderBy('id')
            ->first();

        $total = (float) $venda->total;
        $parcelas = max(1, (int) ($parcelas ?? 1));
        $formaLabel = ucfirst(str_replace('_', ' ', $forma));
        $regra = in_array($forma, ['cartao_credito', 'cartao_debito'], true)
            ? AdquirenteTaxa::paraPagamento((int) $pedido->empresa_id, $forma, $parcelas)
            : null;

        $base = [
            'empresa_id'      => $pedido->empresa_id,
            'unidade_id'      => $pedido->unidade_id,
            'cliente_id'      => $pedido->cliente_id,
            'venda_id'        => $venda->id,
            'pedido_id'       => $pedido->id,
            'forma_pagamento' => $forma,
        ];

        if ($regra) {
            $valorParcela = round($total / $parcelas, 2);
            $acumulado = 0;
            for ($i = 1; $i <= $parcelas; $i++) {
                $valorEsta = $i === $parcelas ? round($total - $acumulado, 2) : $valorParcela;
                $acumulado = round($acumulado + $valorEsta, 2);

                $dados = $base + [
                    'descricao'          => "Pedido #{$pedido->numero} - {$formaLabel} {$i}/{$parcelas} ({$regra->nome})",
                    'valor'              => $valorEsta,
                    'valor_pago'         => 0,
                    'vencimento'         => now()->addDays($regra->prazo_dias + 30 * ($i - 1)),
                    'pago_em'            => null,
                    'adquirente_taxa_id' => $regra->id,
                    'taxa_percentual'    => $regra->taxa_percentual,
                    'valor_liquido'      => round($valorEsta * (1 - ((float) $regra->taxa_percentual) / 100), 2),
                    'parcela'            => $i,
                    'total_parcelas'     => $parcelas,
                    'status'             => 'pendente',
                ];

                if ($i === 1 && $conta) {
                    $conta->update($dados);
                } else {
                    ContaReceber::create($dados);
                }
            }

            return;
        }

        $dados = $base + [
            'descricao'      => "Pedido #{$pedido->numero} - {$formaLabel}",
            'valor'          => $total,
            'valor_pago'     => $total,
            'vencimento'     => now(),
            'pago_em'        => now(),
            'parcela'        => 1,
            'total_parcelas' => 1,
            'status'         => 'paga',
        ];

        $conta ? $conta->update($dados) : ContaReceber::create($dados);
    }

    /** Baixa o estoque dos itens do pedido (chamado no faturamento). */
    private function baixarEstoquePedido(Pedido $pedido): void
    {
        foreach ($pedido->itens as $item) {
            if ($item->produto_id) {
                SaldoEstoque::registrar(
                    $pedido->empresa_id,
                    $pedido->unidade_id,
                    SaldoEstoque::estoqueDeVendaId($pedido->unidade_id),
                    (int) $item->produto_id,
                    TipoMovimentacaoEstoque::Saida->value,
                    -(float) $item->quantidade,
                    [
                        'custo_unitario' => $item->preco_unitario,
                        'origem_tipo'    => Pedido::class,
                        'origem_id'      => $pedido->id,
                        'observacoes'    => "Faturamento Pedido #{$pedido->numero}",
                    ]
                );
            }
        }
    }

    public function destroy(Pedido $pedido)
    {
        if (!in_array($pedido->status, [StatusPedido::Rascunho, StatusPedido::Cancelado])) {
            return back()->with('error', 'Apenas pedidos em rascunho ou cancelados podem ser excluidos.');
        }

        $pedido->itens()->forceDelete();
        $pedido->delete();

        return redirect()->route('app.pedidos.index')
            ->with('success', 'Pedido excluido com sucesso!');
    }
}
