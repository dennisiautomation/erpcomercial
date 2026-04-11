<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Venda;
use App\Models\VendaItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();
        $unidadeId = session('unidade_id');
        $empresaId = auth()->user()->empresa_id;

        // Faturamento do mes
        $faturamentoMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'finalizada')
            ->sum('total');

        // Faturamento do mes anterior (para comparacao)
        $inicioMesAnterior = Carbon::now()->subMonth()->startOfMonth();
        $fimMesAnterior = Carbon::now()->subMonth()->endOfMonth();
        $faturamentoMesAnterior = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])
            ->where('status', 'finalizada')
            ->sum('total');

        // Variacao percentual
        $variacaoFaturamento = $faturamentoMesAnterior > 0
            ? round((($faturamentoMes - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100, 1)
            : 0;

        // Total de vendas no mes
        $totalVendasMes = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'finalizada')
            ->count();

        $totalVendasMesAnterior = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMesAnterior, $fimMesAnterior])
            ->where('status', 'finalizada')
            ->count();

        $variacaoVendas = $totalVendasMesAnterior > 0
            ? round((($totalVendasMes - $totalVendasMesAnterior) / $totalVendasMesAnterior) * 100, 1)
            : 0;

        // Ticket medio
        $ticketMedio = $totalVendasMes > 0 ? $faturamentoMes / $totalVendasMes : 0;

        // Total de clientes ativos
        $totalClientes = Cliente::where('status', 'ativo')->count();

        // Contas a receber vencidas (inadimplencia)
        $inadimplencia = ContaReceber::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Contas a pagar vencidas
        $contasPagarVencidas = ContaPagar::where('unidade_id', $unidadeId)
            ->where('status', 'pendente')
            ->where('vencimento', '<', Carbon::today())
            ->sum('valor');

        // Top 5 produtos vendidos no mes
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

        // Vendas por dia do mes (para grafico)
        $vendasPorDia = Venda::where('unidade_id', $unidadeId)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where('status', 'finalizada')
            ->selectRaw('DATE(created_at) as dia, SUM(total) as total_dia, COUNT(*) as qtd')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // Ultimas 10 vendas
        $ultimasVendas = Venda::where('unidade_id', $unidadeId)
            ->with(['cliente:id,nome_razao_social', 'vendedor:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('app.dashboard', compact(
            'faturamentoMes',
            'variacaoFaturamento',
            'totalVendasMes',
            'variacaoVendas',
            'ticketMedio',
            'totalClientes',
            'inadimplencia',
            'contasPagarVencidas',
            'topProdutos',
            'vendasPorDia',
            'ultimasVendas',
        ));
    }
}
