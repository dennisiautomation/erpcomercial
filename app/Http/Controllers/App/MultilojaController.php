<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\Produto;
use App\Models\ContaReceber;
use App\Models\Caixa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MultilojaController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $empresaId = session('empresa_id');

        // Only Dono/Admin can access
        if (!in_array($user->papel, ['dono', 'admin'])) {
            abort(403, 'Acesso restrito ao Dono/Administrador.');
        }

        $unidades = Unidade::where('empresa_id', $empresaId)
            ->where('status', 'ativa')
            ->get();

        $mesAtual = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        $dadosUnidades = [];
        $faturamentoTotal = 0;
        $vendasTotal = 0;

        foreach ($unidades as $unidade) {
            $vendasMes = Venda::where('unidade_id', $unidade->id)
                ->where('empresa_id', $empresaId)
                ->whereBetween('created_at', [$mesAtual, $fimMes])
                ->whereNotIn('status', ['cancelada']);

            $faturamento = (clone $vendasMes)->sum('total');
            $totalVendas = (clone $vendasMes)->count();
            $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;

            $estoqueCritico = Produto::where('empresa_id', $empresaId)
                ->whereHas('estoqueMovimentacoes', function ($q) use ($unidade) {
                    $q->where('unidade_id', $unidade->id);
                })
                ->whereRaw('estoque_minimo > 0')
                ->get()
                ->filter(function ($produto) use ($unidade) {
                    $saldo = $produto->estoqueMovimentacoes()
                        ->where('unidade_id', $unidade->id)
                        ->latest()
                        ->value('quantidade_posterior') ?? 0;
                    return $saldo <= $produto->estoque_minimo;
                })
                ->count();

            $faturamentoTotal += $faturamento;
            $vendasTotal += $totalVendas;

            $dadosUnidades[] = [
                'unidade' => $unidade,
                'faturamento' => $faturamento,
                'total_vendas' => $totalVendas,
                'ticket_medio' => $ticketMedio,
                'estoque_critico' => $estoqueCritico,
            ];
        }

        // Sort by faturamento desc for ranking
        usort($dadosUnidades, fn ($a, $b) => $b['faturamento'] <=> $a['faturamento']);

        $ticketMedioGlobal = $vendasTotal > 0 ? $faturamentoTotal / $vendasTotal : 0;

        // Alerts
        $alertas = [];

        // Estoque baixo across unidades
        $totalEstoqueCritico = collect($dadosUnidades)->sum('estoque_critico');
        if ($totalEstoqueCritico > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'icone' => 'bi-exclamation-triangle',
                'mensagem' => "{$totalEstoqueCritico} produto(s) com estoque critico nas unidades.",
            ];
        }

        // Contas vencidas
        $contasVencidas = ContaReceber::where('empresa_id', $empresaId)
            ->whereNull('data_pagamento')
            ->where('data_vencimento', '<', now())
            ->count();

        if ($contasVencidas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'icone' => 'bi-clock-history',
                'mensagem' => "{$contasVencidas} conta(s) a receber vencida(s).",
            ];
        }

        // Caixas abertos
        $caixasAbertos = Caixa::where('empresa_id', $empresaId)
            ->whereNull('fechado_em')
            ->count();

        if ($caixasAbertos > 0) {
            $alertas[] = [
                'tipo' => 'info',
                'icone' => 'bi-cash-coin',
                'mensagem' => "{$caixasAbertos} caixa(s) aberto(s) no momento.",
            ];
        }

        return view('app.multilojas.index', compact(
            'dadosUnidades',
            'faturamentoTotal',
            'vendasTotal',
            'ticketMedioGlobal',
            'unidades',
            'alertas'
        ));
    }

    public function comparar(Request $request)
    {
        $user = auth()->user();
        $empresaId = session('empresa_id');

        if (!in_array($user->papel, ['dono', 'admin'])) {
            abort(403, 'Acesso restrito ao Dono/Administrador.');
        }

        $unidades = Unidade::where('empresa_id', $empresaId)
            ->where('status', 'ativa')
            ->get();

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)->startOfDay()
            : Carbon::now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)->endOfDay()
            : Carbon::now()->endOfDay();

        $unidadesSelecionadas = $request->input('unidades', []);

        $comparacao = [];

        if (!empty($unidadesSelecionadas)) {
            foreach ($unidadesSelecionadas as $unidadeId) {
                $unidade = $unidades->find($unidadeId);
                if (!$unidade) continue;

                $vendasQuery = Venda::where('unidade_id', $unidade->id)
                    ->where('empresa_id', $empresaId)
                    ->whereBetween('created_at', [$dataInicio, $dataFim])
                    ->whereNotIn('status', ['cancelada']);

                $faturamento = (clone $vendasQuery)->sum('total');
                $totalVendas = (clone $vendasQuery)->count();
                $ticketMedio = $totalVendas > 0 ? $faturamento / $totalVendas : 0;
                $devolucoes = Venda::where('unidade_id', $unidade->id)
                    ->where('empresa_id', $empresaId)
                    ->whereBetween('created_at', [$dataInicio, $dataFim])
                    ->where('status', 'cancelada')
                    ->count();

                $comparacao[] = [
                    'unidade' => $unidade,
                    'faturamento' => $faturamento,
                    'total_vendas' => $totalVendas,
                    'ticket_medio' => $ticketMedio,
                    'devolucoes' => $devolucoes,
                ];
            }
        }

        return view('app.multilojas.comparar', compact(
            'unidades',
            'comparacao',
            'dataInicio',
            'dataFim',
            'unidadesSelecionadas'
        ));
    }
}
