<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    public function vendas(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        $query = Venda::with(['cliente', 'vendedor'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);

        if ($request->filled('vendedor_id')) {
            $query->where('vendedor_id', $request->vendedor_id);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $vendas = $query->orderByDesc('created_at')->get();

        $totalVendas = $vendas->count();
        $faturamento = $vendas->sum('total');
        $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;

        // Top 10 produtos
        $topProdutos = VendaItem::select(
                'produto_id',
                DB::raw('SUM(quantidade) as qtd_vendida'),
                DB::raw('SUM(total) as faturamento')
            )
            ->whereHas('venda', function ($q) use ($empresaId, $dataInicio, $dataFim) {
                $q->where('empresa_id', $empresaId)
                  ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()]);
            })
            ->groupBy('produto_id')
            ->with('produto:id,descricao')
            ->orderByDesc('faturamento')
            ->limit(10)
            ->get();

        // Top 10 clientes
        $topClientes = Venda::select(
                'cliente_id',
                DB::raw('COUNT(*) as total_vendas'),
                DB::raw('SUM(total) as faturamento')
            )
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$dataInicio, $dataFim->endOfDay()])
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->with('cliente:id,nome_razao_social')
            ->orderByDesc('faturamento')
            ->limit(10)
            ->get();

        return view('app.relatorios.vendas', compact(
            'vendas', 'totalVendas', 'faturamento', 'ticketMedio',
            'topProdutos', 'topClientes', 'dataInicio', 'dataFim'
        ));
    }

    public function estoque(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        // Get all products with their last movimentacao to calculate current stock
        $produtos = Produto::where('empresa_id', $empresaId)
            ->with('categoria')
            ->orderBy('descricao')
            ->get();

        // For each product, get the latest stock position
        $produtos->each(function ($produto) use ($empresaId) {
            $ultima = EstoqueMovimentacao::where('produto_id', $produto->id)
                ->where('empresa_id', $empresaId)
                ->orderByDesc('id')
                ->first();

            $produto->estoque_atual = $ultima ? (float) $ultima->quantidade_posterior : 0;
            $produto->estoque_status = 'ok';

            if ($produto->estoque_minimo && $produto->estoque_atual <= 0) {
                $produto->estoque_status = 'critico';
            } elseif ($produto->estoque_minimo && $produto->estoque_atual <= $produto->estoque_minimo) {
                $produto->estoque_status = 'baixo';
            }
        });

        // Curva ABC
        $produtosComVendas = VendaItem::select(
                'produto_id',
                DB::raw('SUM(total) as faturamento')
            )
            ->whereHas('venda', function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            })
            ->groupBy('produto_id')
            ->orderByDesc('faturamento')
            ->get();

        $totalFaturamento = $produtosComVendas->sum('faturamento');
        $acumulado = 0;
        $curvaABC = [];

        foreach ($produtosComVendas as $item) {
            $acumulado += (float) $item->faturamento;
            $percentual = $totalFaturamento > 0 ? ($acumulado / $totalFaturamento) * 100 : 0;

            $curva = 'C';
            if ($percentual <= 80) {
                $curva = 'A';
            } elseif ($percentual <= 95) {
                $curva = 'B';
            }

            $curvaABC[$item->produto_id] = $curva;
        }

        return view('app.relatorios.estoque', compact('produtos', 'curvaABC'));
    }

    public function financeiro(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        // Receitas
        $receitas = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->sum('valor_pago');

        // Despesas
        $despesas = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->sum('valor_pago');

        // Custos (categorias de custo)
        $custos = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->whereIn('categoria', ['custo', 'CMV', 'custo_mercadoria'])
            ->sum('valor_pago');

        $despesasOperacionais = $despesas - $custos;
        $lucroBruto = $receitas - $custos;
        $resultado = $receitas - $despesas;

        // Contas a receber por vencimento
        $contasReceber = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->select(
                DB::raw("CASE
                    WHEN vencimento < CURDATE() THEN 'Vencido'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Proximos 7 dias'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Proximos 30 dias'
                    ELSE 'Acima de 30 dias'
                END as faixa"),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as quantidade')
            )
            ->groupBy('faixa')
            ->get();

        // Contas a pagar por vencimento
        $contasPagar = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->select(
                DB::raw("CASE
                    WHEN vencimento < CURDATE() THEN 'Vencido'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Proximos 7 dias'
                    WHEN vencimento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Proximos 30 dias'
                    ELSE 'Acima de 30 dias'
                END as faixa"),
                DB::raw('SUM(valor) as total'),
                DB::raw('COUNT(*) as quantidade')
            )
            ->groupBy('faixa')
            ->get();

        return view('app.relatorios.financeiro', compact(
            'receitas', 'despesas', 'custos', 'despesasOperacionais',
            'lucroBruto', 'resultado', 'contasReceber', 'contasPagar',
            'dataInicio', 'dataFim'
        ));
    }
}
