<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\CentroCusto;
use App\Models\ContaReceber;
use App\Models\PlanoContas;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class FluxoCaixaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : now()->startOfMonth();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : now()->endOfMonth();

        // Filtros das SAÍDAS (plano de contas e centro de custo). As entradas não mudam: o filtro
        // serve para ver quanto sai de um tipo de despesa, não para refazer o saldo da empresa.
        $planoContaId = $request->integer('plano_conta_id') ?: null;
        $centroCustoId = $request->integer('centro_custo_id') ?: null;
        $filtroSaidas = function ($query) use ($planoContaId, $centroCustoId) {
            return $query
                ->when($planoContaId, fn ($q) => $q->where('plano_conta_id', $planoContaId))
                ->when($centroCustoId, fn ($q) => $q->where('centro_custo_id', $centroCustoId));
        };

        // Entradas (contas recebidas no periodo)
        $entradas = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->orderBy('pago_em')
            ->get();

        // Saidas (contas pagas no periodo)
        $saidas = $filtroSaidas(ContaPagar::where('empresa_id', $empresaId))
            ->where('status', 'paga')
            ->whereBetween('pago_em', [$dataInicio, $dataFim])
            ->orderBy('pago_em')
            ->get();

        // Previstas a receber (pendentes no periodo)
        $previstaReceber = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->whereBetween('vencimento', [$dataInicio, $dataFim])
            ->sum('valor');

        // Previstas a pagar (pendentes no periodo)
        // Programado: cada conta pendente que vence no período, na ordem em que vence.
        $programadas = $filtroSaidas(ContaPagar::where('empresa_id', $empresaId))
            ->where('status', 'pendente')
            ->whereBetween('vencimento', [$dataInicio, $dataFim])
            ->with(['fornecedor', 'funcionario'])
            ->orderBy('vencimento')->orderBy('id')
            ->get();
        $previstaPagar = $programadas->sum('valor');

        $planoContas = PlanoContas::where('empresa_id', $empresaId)->analiticas()->ativas()
            ->whereIn('tipo', ['despesa', 'custo'])->orderBy('codigo')->get(['id', 'codigo', 'nome']);
        $centrosCusto = CentroCusto::where('empresa_id', $empresaId)->where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        // Build daily flow
        $fluxoDiario = [];
        $period = CarbonPeriod::create($dataInicio, $dataFim);

        foreach ($period as $date) {
            $dia = $date->format('Y-m-d');
            $fluxoDiario[$dia] = [
                'data'     => $dia,
                'entradas' => 0,
                'saidas'   => 0,
                'itens'    => [],
            ];
        }

        foreach ($entradas as $entrada) {
            $dia = $entrada->pago_em->format('Y-m-d');
            if (isset($fluxoDiario[$dia])) {
                $fluxoDiario[$dia]['entradas'] += (float) $entrada->valor_pago;
                $fluxoDiario[$dia]['itens'][] = [
                    'descricao' => $entrada->descricao,
                    'categoria' => 'Receita',
                    'valor'     => (float) $entrada->valor_pago,
                    'tipo'      => 'entrada',
                ];
            }
        }

        foreach ($saidas as $saida) {
            $dia = $saida->pago_em->format('Y-m-d');
            if (isset($fluxoDiario[$dia])) {
                $fluxoDiario[$dia]['saidas'] += (float) $saida->valor_pago;
                $fluxoDiario[$dia]['itens'][] = [
                    'descricao'  => $saida->descricao,
                    'categoria'  => $saida->categoria ?? 'Despesa',
                    'valor'      => (float) $saida->valor_pago,
                    'tipo'       => 'saida',
                ];
            }
        }

        // Calculate running balance
        $saldoAcumulado = 0;
        $chartSaldo = [];
        foreach ($fluxoDiario as &$dia) {
            $saldoAcumulado += $dia['entradas'] - $dia['saidas'];
            $dia['saldo'] = $saldoAcumulado;
            $chartSaldo[] = $saldoAcumulado;
        }
        unset($dia);

        $totalEntradas = collect($fluxoDiario)->sum('entradas');
        $totalSaidas = collect($fluxoDiario)->sum('saidas');
        $saldoFinal = $totalEntradas - $totalSaidas;

        // Chart data
        $chartLabels = array_keys($fluxoDiario);
        $chartEntradas = array_column($fluxoDiario, 'entradas');
        $chartSaidas = array_column($fluxoDiario, 'saidas');

        return view('app.financeiro.fluxo-caixa', compact(
            'fluxoDiario', 'totalEntradas', 'totalSaidas', 'saldoFinal',
            'dataInicio', 'dataFim', 'chartLabels', 'chartEntradas', 'chartSaidas',
            'chartSaldo', 'previstaReceber', 'previstaPagar',
            'programadas', 'planoContas', 'centrosCusto', 'planoContaId', 'centroCustoId'
        ));
    }
}
