<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendaController extends Controller
{
    public function index(Request $request)
    {
        $query = Venda::where('empresa_id', session('empresa_id'))
            ->where('unidade_id', session('unidade_id'))
            ->with(['cliente:id,nome_razao_social,cpf_cnpj', 'vendedor:id,name']);

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

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('forma_pagamento')) {
            $query->where('forma_pagamento', $request->forma_pagamento);
        }

        $vendas = $query->latest()->paginate(20)->withQueryString();

        // Summary stats
        $empresaId = session('empresa_id');
        $unidadeId = session('unidade_id');
        $baseQuery = Venda::where('empresa_id', $empresaId)->where('unidade_id', $unidadeId);

        // Apply same date filters for stats
        $statsQuery = clone $baseQuery;
        if ($request->filled('data_inicio')) {
            $statsQuery->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $statsQuery->whereDate('created_at', '<=', $request->data_fim);
        }

        $stats = [
            'total_concluidas' => (clone $statsQuery)->where('status', StatusVenda::Concluida)->sum('total'),
            'count_concluidas' => (clone $statsQuery)->where('status', StatusVenda::Concluida)->count(),
            'count_canceladas' => (clone $statsQuery)->where('status', StatusVenda::Cancelada)->count(),
            'total_hoje' => (clone $baseQuery)->where('status', StatusVenda::Concluida)->whereDate('created_at', today())->sum('total'),
        ];

        return view('app.vendas.index', compact('vendas', 'stats'));
    }

    public function create()
    {
        $vendedores = User::where('empresa_id', auth()->user()->empresa_id)
            ->whereIn('perfil', ['vendedor', 'gerente', 'dono'])
            ->where('status', 'ativo')
            ->orderBy('name')
            ->get();

        return view('app.vendas.create', compact('vendedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'itens'                  => 'required|array|min:1',
            'itens.*.produto_id'     => 'required|exists:produtos,id',
            'itens.*.quantidade'     => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto_valor' => 'nullable|numeric|min:0',
            'cliente_id'             => 'nullable|exists:clientes,id',
            'vendedor_id'            => 'nullable|exists:users,id',
            'forma_pagamento'        => 'required|string',
            'desconto_valor'         => 'nullable|numeric|min:0',
            'observacoes'            => 'nullable|string|max:1000',
        ]);

        try {
            $venda = DB::transaction(function () use ($request) {
                $empresaId = session('empresa_id');
                $unidadeId = session('unidade_id');

                $ultimoNumero = Venda::withoutGlobalScopes()
                    ->where('empresa_id', $empresaId)
                    ->max('numero');
                $numero = $ultimoNumero ? $ultimoNumero + 1 : 1;

                $subtotal = 0;
                $itensData = [];

                foreach ($request->itens as $item) {
                    $produto = Produto::find($item['produto_id']);
                    if (!$produto) continue;

                    $precoUnit = $item['preco_unitario'];
                    $qtd = $item['quantidade'];
                    $descontoValor = $item['desconto_valor'] ?? 0;
                    $totalItem = round(($precoUnit * $qtd) - $descontoValor, 2);

                    $itensData[] = [
                        'produto_id'          => $item['produto_id'],
                        'descricao'           => $produto->descricao,
                        'quantidade'          => $qtd,
                        'preco_unitario'      => $precoUnit,
                        'desconto_valor'      => $descontoValor,
                        'desconto_percentual' => 0,
                        'total'               => $totalItem,
                    ];

                    $subtotal += $totalItem;
                }

                if (empty($itensData)) {
                    throw new \Exception('Nenhum item valido na venda.');
                }

                $descontoGeral = $request->desconto_valor ?? 0;
                $total = round($subtotal - $descontoGeral, 2);
                if ($total < 0) $total = 0;

                $venda = Venda::create([
                    'empresa_id'          => $empresaId,
                    'unidade_id'          => $unidadeId,
                    'cliente_id'          => $request->cliente_id,
                    'vendedor_id'         => $request->vendedor_id ?? auth()->id(),
                    'numero'              => $numero,
                    'subtotal'            => $subtotal,
                    'desconto_percentual' => 0,
                    'desconto_valor'      => $descontoGeral,
                    'total'               => $total,
                    'forma_pagamento'     => $request->forma_pagamento,
                    'pagamento_detalhes'  => [['forma' => $request->forma_pagamento, 'valor' => $total]],
                    'troco'               => 0,
                    'status'              => StatusVenda::Concluida,
                    'tipo'                => 'balcao',
                    'observacoes'         => $request->observacoes,
                ]);

                // Create VendaItens
                foreach ($itensData as $itemData) {
                    $venda->itens()->create($itemData);
                }

                // Deduct estoque
                foreach ($request->itens as $item) {
                    if (!empty($item['produto_id'])) {
                        $produto = Produto::find($item['produto_id']);
                        if (!$produto) continue;

                        $estoqueAnterior = EstoqueMovimentacao::withoutGlobalScopes()
                            ->where('produto_id', $item['produto_id'])
                            ->where('unidade_id', $unidadeId)
                            ->latest()
                            ->value('quantidade_posterior') ?? 0;

                        EstoqueMovimentacao::create([
                            'empresa_id'           => $empresaId,
                            'unidade_id'           => $unidadeId,
                            'produto_id'           => $item['produto_id'],
                            'tipo'                 => TipoMovimentacaoEstoque::Saida,
                            'quantidade'           => $item['quantidade'],
                            'quantidade_anterior'  => $estoqueAnterior,
                            'quantidade_posterior'  => $estoqueAnterior - $item['quantidade'],
                            'custo_unitario'       => $item['preco_unitario'],
                            'origem_tipo'          => Venda::class,
                            'origem_id'            => $venda->id,
                            'user_id'              => auth()->id(),
                            'observacoes'          => "Venda Balcao #{$venda->numero}",
                        ]);
                    }
                }

                // Create ContaReceber
                ContaReceber::create([
                    'empresa_id'      => $empresaId,
                    'unidade_id'      => $unidadeId,
                    'cliente_id'      => $request->cliente_id,
                    'venda_id'        => $venda->id,
                    'descricao'       => "Venda Balcao #{$venda->numero} - " . ucfirst(str_replace('_', ' ', $request->forma_pagamento)),
                    'valor'           => $total,
                    'valor_pago'      => $total,
                    'vencimento'      => now(),
                    'pago_em'         => now(),
                    'forma_pagamento' => $request->forma_pagamento,
                    'parcela'         => 1,
                    'total_parcelas'  => 1,
                    'status'          => 'paga',
                ]);

                // Calculate and create Comissao for vendedor
                $vendedorId = $request->vendedor_id ?? auth()->id();
                if ($vendedorId) {
                    $vendedor = User::find($vendedorId);
                    $percentualComissao = $vendedor->comissao_percentual ?? 5;
                    $valorComissao = round($total * ($percentualComissao / 100), 2);

                    if ($valorComissao > 0) {
                        Comissao::create([
                            'empresa_id'     => $empresaId,
                            'unidade_id'     => $unidadeId,
                            'user_id'        => $vendedorId,
                            'venda_id'       => $venda->id,
                            'valor_venda'    => $total,
                            'percentual'     => $percentualComissao,
                            'valor_comissao' => $valorComissao,
                            'status'         => 'pendente',
                        ]);
                    }
                }

                return $venda;
            });

            return redirect()->route('app.vendas.show', $venda)
                ->with('success', "Venda #{$venda->numero} registrada com sucesso!");

        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Erro ao registrar venda: ' . $e->getMessage());
        }
    }

    public function show(Venda $venda)
    {
        $venda->load(['cliente', 'vendedor', 'itens.produto', 'itens.servico', 'caixa', 'pedido', 'notasFiscais', 'contasReceber']);

        return view('app.vendas.show', compact('venda'));
    }

    public function destroy(Venda $venda)
    {
        if ($venda->status === StatusVenda::Cancelada) {
            return back()->with('error', 'Esta venda ja esta cancelada.');
        }

        DB::transaction(function () use ($venda) {
            // Revert estoque
            foreach ($venda->itens as $item) {
                if ($item->produto_id) {
                    $produto = Produto::find($item->produto_id);
                    $estoqueAnterior = $produto->estoqueMovimentacoes()
                        ->where('unidade_id', $venda->unidade_id)
                        ->latest()
                        ->value('quantidade_posterior') ?? 0;

                    EstoqueMovimentacao::create([
                        'empresa_id'          => $venda->empresa_id,
                        'unidade_id'          => $venda->unidade_id,
                        'produto_id'          => $item->produto_id,
                        'tipo'                => TipoMovimentacaoEstoque::Devolucao,
                        'quantidade'          => $item->quantidade,
                        'quantidade_anterior' => $estoqueAnterior,
                        'quantidade_posterior' => $estoqueAnterior + $item->quantidade,
                        'custo_unitario'      => $item->preco_unitario,
                        'origem_tipo'         => Venda::class,
                        'origem_id'           => $venda->id,
                        'user_id'             => auth()->id(),
                        'observacoes'         => "Cancelamento Venda #{$venda->numero}",
                    ]);
                }
            }

            // Cancel contas_receber
            $venda->contasReceber()->update(['status' => 'cancelada']);

            // Update venda status
            $venda->update(['status' => StatusVenda::Cancelada]);
        });

        return back()->with('success', 'Venda cancelada com sucesso!');
    }
}
