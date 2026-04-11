<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();
        $unidadeId = session('unidade_id');

        // Faturamento do mês
        $faturamentoMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'finalizada')
            ->sum('total');

        // Total de vendas no mês
        $totalVendasMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'finalizada')
            ->count();

        // Ticket médio
        $ticketMedio = $totalVendasMes > 0 ? $faturamentoMes / $totalVendasMes : 0;

        // Contas a receber vencidas (inadimplência)
        $inadimplencia = ContaReceber::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Contas a pagar vencidas
        $contasPagarVencidas = ContaPagar::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Top 5 produtos vendidos no mês
        $topProdutos = VendaItem::whereHas('venda', function ($q) use ($unidadeId, $inicioMes, $fimMes) {
                $q->where('unidade_id', $unidadeId)
                  ->whereBetween('created_at', [$inicioMes, $fimMes])
                  ->where('status', 'finalizada');
            })
            ->whereNotNull('produto_id')
            ->selectRaw('produto_id, SUM(quantidade) as total_quantidade, SUM(total) as total_valor')
            ->groupBy('produto_id')
            ->orderByDesc('total_quantidade')
            ->with('produto:id,descricao')
            ->limit(5)
            ->get();

        // Últimas 10 vendas
        $ultimasVendas = Venda::where('unidade_id', $unidadeId)
            ->with(['cliente:id,nome_razao_social', 'vendedor:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('app.dashboard', compact(
            'faturamentoMes',
            'totalVendasMes',
            'ticketMedio',
            'inadimplencia',
            'contasPagarVencidas',
            'topProdutos',
            'ultimasVendas',
        ));
    }
}
