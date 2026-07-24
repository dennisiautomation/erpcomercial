<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusPedido;
use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoFiscal;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFCeService;
use App\Services\FocusNFe\NFeService;
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
        $pedido->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'orcamento', 'venda']);

        return view('app.pedidos.show', compact('pedido'));
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
            $total = round($subtotal - $descontoGeralValor, 2);

            $pedido->update([
                'cliente_id'          => $request->cliente_id,
                'vendedor_id'         => $request->vendedor_id,
                'condicao_pagamento'  => $request->condicao_pagamento,
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
    public function faturar(Request $request, Pedido $pedido)
    {
        $request->validate([
            'documento' => 'required|in:nenhum,recibo,cupom_fiscal,nota_fiscal',
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

        $venda = DB::transaction(function () use ($pedido) {
            $ultimoNumero = Venda::withoutGlobalScopes()
                ->where('empresa_id', $pedido->empresa_id)
                ->max('numero');

            $formaPagamento = $pedido->condicao_pagamento ?: 'a_definir';

            $venda = Venda::create([
                'empresa_id'          => $pedido->empresa_id,
                'unidade_id'          => $pedido->unidade_id,
                'pedido_id'           => $pedido->id,
                'cliente_id'          => $pedido->cliente_id,
                'vendedor_id'         => $pedido->vendedor_id ?? auth()->id(),
                'numero'              => $ultimoNumero ? $ultimoNumero + 1 : 1,
                'subtotal'            => $pedido->subtotal,
                'desconto_percentual' => $pedido->desconto_percentual ?? 0,
                'desconto_valor'      => $pedido->desconto_valor ?? 0,
                'total'               => $pedido->total,
                'forma_pagamento'     => $formaPagamento,
                'pagamento_detalhes'  => [['forma' => $formaPagamento, 'valor' => (float) $pedido->total]],
                'troco'               => 0,
                'status'              => StatusVenda::Concluida,
                'tipo'                => 'pedido',
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

            $pedido->update(['status' => StatusPedido::Faturado]);

            return $venda;
        });

        // Emissão fora da transaction: falha fiscal não desfaz o faturamento
        switch ($documento) {
            case 'recibo':
                return redirect()->route('app.vendas.recibo', $venda)
                    ->with('success', "Pedido #{$pedido->numero} faturado! Recibo pronto para impressão.");

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

    /** Baixa o estoque dos itens do pedido (chamado no faturamento). */
    private function baixarEstoquePedido(Pedido $pedido): void
    {
        foreach ($pedido->itens as $item) {
            if ($item->produto_id) {
                $produto = Produto::find($item->produto_id);
                $estoqueAnterior = $produto->estoqueMovimentacoes()
                    ->where('unidade_id', $pedido->unidade_id)
                    ->latest()
                    ->value('quantidade_posterior') ?? 0;

                EstoqueMovimentacao::create([
                    'empresa_id'          => $pedido->empresa_id,
                    'unidade_id'          => $pedido->unidade_id,
                    'produto_id'          => $item->produto_id,
                    'tipo'                => TipoMovimentacaoEstoque::Saida,
                    'quantidade'          => $item->quantidade,
                    'quantidade_anterior' => $estoqueAnterior,
                    'quantidade_posterior' => $estoqueAnterior - $item->quantidade,
                    'custo_unitario'      => $item->preco_unitario,
                    'origem_tipo'         => Pedido::class,
                    'origem_id'           => $pedido->id,
                    'user_id'             => auth()->id(),
                    'observacoes'         => "Faturamento Pedido #{$pedido->numero}",
                ]);
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
