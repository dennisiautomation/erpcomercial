<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            $pedido->update(['status' => $novoStatus]);
        });

        return back()->with('success', "Status do pedido atualizado para {$novoStatus->label()}!");
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
