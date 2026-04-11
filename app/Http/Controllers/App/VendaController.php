<?php

namespace App\Http\Controllers\App;

use App\Enums\StatusVenda;
use App\Enums\TipoMovimentacaoEstoque;
use App\Http\Controllers\Controller;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
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

        $vendas = $query->latest()->paginate(20)->withQueryString();

        return view('app.vendas.index', compact('vendas'));
    }

    public function show(Venda $venda)
    {
        $venda->load(['cliente', 'vendedor', 'itens.produto', 'caixa', 'pedido', 'notasFiscais', 'contasReceber']);

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
