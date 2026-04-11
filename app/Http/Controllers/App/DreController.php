<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\PlanoContas;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DreController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $unidadeId = $request->input('unidade_id');

        $unidades = Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get();

        // Build DRE
        $dre = $this->buildDre($empresaId, $dataInicio, $dataFim, $unidadeId);

        // Previous period for comparison
        $diasPeriodo = Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1;
        $dataInicioAnterior = Carbon::parse($dataInicio)->subDays($diasPeriodo)->format('Y-m-d');
        $dataFimAnterior = Carbon::parse($dataInicio)->subDay()->format('Y-m-d');
        $dreAnterior = $this->buildDre($empresaId, $dataInicioAnterior, $dataFimAnterior, $unidadeId);

        return view('app.dre.index', compact(
            'dre', 'dreAnterior', 'unidades', 'dataInicio', 'dataFim', 'unidadeId'
        ));
    }

    public function porUnidade(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $unidades = Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get();

        $dresPorUnidade = [];
        foreach ($unidades as $unidade) {
            $dresPorUnidade[$unidade->id] = [
                'unidade' => $unidade,
                'dre' => $this->buildDre($empresaId, $dataInicio, $dataFim, $unidade->id),
            ];
        }

        return view('app.dre.por-unidade', compact('dresPorUnidade', 'unidades', 'dataInicio', 'dataFim'));
    }

    public function exportar(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $unidadeId = $request->input('unidade_id');

        $dre = $this->buildDre($empresaId, $dataInicio, $dataFim, $unidadeId);

        $filename = 'dre_' . $dataInicio . '_' . $dataFim . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($dre) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, ['Descricao', 'Valor', '% Receita'], ';');

            foreach ($dre['linhas'] as $linha) {
                fputcsv($file, [
                    $linha['descricao'],
                    number_format($linha['valor'], 2, ',', '.'),
                    $linha['percentual'] . '%',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /* ------------------------------------------------------------------ */
    /*  Private: Build DRE Structure                                       */
    /* ------------------------------------------------------------------ */

    private function buildDre(int $empresaId, string $dataInicio, string $dataFim, ?int $unidadeId = null): array
    {
        // 1. RECEITA BRUTA - contas_receber pagas com plano_conta tipo=receita
        $queryReceitas = ContaReceber::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereDate('pago_em', '>=', $dataInicio)
            ->whereDate('pago_em', '<=', $dataFim);

        if ($unidadeId) {
            $queryReceitas->where('unidade_id', $unidadeId);
        }

        $receitaBruta = (clone $queryReceitas)->sum('valor_pago');

        // Receitas agrupadas por plano de contas
        $receitasPorConta = (clone $queryReceitas)
            ->whereNotNull('plano_conta_id')
            ->selectRaw('plano_conta_id, SUM(valor_pago) as total')
            ->groupBy('plano_conta_id')
            ->get()
            ->keyBy('plano_conta_id');

        // 2. DEDUCOES (contas_receber com valor_pago < valor - descontos, devoluções)
        $deducoes = 0; // Placeholder - could be expanded

        // 3. RECEITA LIQUIDA
        $receitaLiquida = $receitaBruta - $deducoes;

        // 4. CMV - contas_pagar com plano_conta tipo=custo
        $queryCustos = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereDate('pago_em', '>=', $dataInicio)
            ->whereDate('pago_em', '<=', $dataFim)
            ->whereHas('planoConta', function ($q) {
                $q->where('tipo', 'custo');
            });

        if ($unidadeId) {
            $queryCustos->where('unidade_id', $unidadeId);
        }

        $cmv = $queryCustos->sum('valor_pago');

        // 5. LUCRO BRUTO
        $lucroBruto = $receitaLiquida - $cmv;

        // 6. DESPESAS OPERACIONAIS - contas_pagar com plano_conta tipo=despesa
        $queryDespesas = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereDate('pago_em', '>=', $dataInicio)
            ->whereDate('pago_em', '<=', $dataFim)
            ->whereHas('planoConta', function ($q) {
                $q->where('tipo', 'despesa');
            });

        if ($unidadeId) {
            $queryDespesas->where('unidade_id', $unidadeId);
        }

        $despesasTotal = (clone $queryDespesas)->sum('valor_pago');

        // Despesas agrupadas por plano de contas
        $despesasPorConta = (clone $queryDespesas)
            ->whereNotNull('plano_conta_id')
            ->selectRaw('plano_conta_id, SUM(valor_pago) as total')
            ->groupBy('plano_conta_id')
            ->get()
            ->keyBy('plano_conta_id');

        // Also include despesas without plano_conta
        $despesasSemConta = ContaPagar::where('empresa_id', $empresaId)
            ->where('status', 'paga')
            ->whereDate('pago_em', '>=', $dataInicio)
            ->whereDate('pago_em', '<=', $dataFim)
            ->whereNull('plano_conta_id');

        if ($unidadeId) {
            $despesasSemConta->where('unidade_id', $unidadeId);
        }

        $despesasSemContaTotal = $despesasSemConta->sum('valor_pago');
        $despesasTotal = $despesasTotal + $despesasSemContaTotal;

        // 7. RESULTADO OPERACIONAL
        $resultadoOperacional = $lucroBruto - $despesasTotal;

        // 8. RESULTADO FINANCEIRO (juros, multas, etc.)
        $resultadoFinanceiro = 0; // Placeholder

        // 9. RESULTADO LIQUIDO
        $resultadoLiquido = $resultadoOperacional + $resultadoFinanceiro;

        // Build linhas for display
        $linhas = [];
        $pctBase = $receitaBruta > 0 ? $receitaBruta : 1;

        $linhas[] = ['descricao' => 'RECEITA BRUTA', 'valor' => $receitaBruta, 'percentual' => round(($receitaBruta / $pctBase) * 100, 1), 'tipo' => 'header'];

        // Detalhe receitas por conta
        $planoContasIds = $receitasPorConta->keys()->toArray();
        if (!empty($planoContasIds)) {
            $planoContasNomes = PlanoContas::whereIn('id', $planoContasIds)->pluck('nome', 'id');
            foreach ($receitasPorConta as $contaId => $item) {
                $linhas[] = ['descricao' => '   ' . ($planoContasNomes[$contaId] ?? 'Outros'), 'valor' => $item->total, 'percentual' => round(($item->total / $pctBase) * 100, 1), 'tipo' => 'detalhe'];
            }
        }

        $linhas[] = ['descricao' => '(-) DEDUCOES', 'valor' => -$deducoes, 'percentual' => round(($deducoes / $pctBase) * 100, 1), 'tipo' => 'subtotal'];
        $linhas[] = ['descricao' => '= RECEITA LIQUIDA', 'valor' => $receitaLiquida, 'percentual' => round(($receitaLiquida / $pctBase) * 100, 1), 'tipo' => 'resultado'];
        $linhas[] = ['descricao' => '(-) CUSTO DAS MERCADORIAS VENDIDAS (CMV)', 'valor' => -$cmv, 'percentual' => round(($cmv / $pctBase) * 100, 1), 'tipo' => 'subtotal'];
        $linhas[] = ['descricao' => '= LUCRO BRUTO', 'valor' => $lucroBruto, 'percentual' => round(($lucroBruto / $pctBase) * 100, 1), 'tipo' => 'resultado'];
        $linhas[] = ['descricao' => '(-) DESPESAS OPERACIONAIS', 'valor' => -$despesasTotal, 'percentual' => round(($despesasTotal / $pctBase) * 100, 1), 'tipo' => 'header'];

        // Detalhe despesas por conta
        $planoContasIdsDespesas = $despesasPorConta->keys()->toArray();
        if (!empty($planoContasIdsDespesas)) {
            $planoContasNomesDespesas = PlanoContas::whereIn('id', $planoContasIdsDespesas)->pluck('nome', 'id');
            foreach ($despesasPorConta as $contaId => $item) {
                $linhas[] = ['descricao' => '   ' . ($planoContasNomesDespesas[$contaId] ?? 'Outros'), 'valor' => -$item->total, 'percentual' => round(($item->total / $pctBase) * 100, 1), 'tipo' => 'detalhe'];
            }
        }

        if ($despesasSemContaTotal > 0) {
            $linhas[] = ['descricao' => '   Outras Despesas (sem classificacao)', 'valor' => -$despesasSemContaTotal, 'percentual' => round(($despesasSemContaTotal / $pctBase) * 100, 1), 'tipo' => 'detalhe'];
        }

        $linhas[] = ['descricao' => '= RESULTADO OPERACIONAL', 'valor' => $resultadoOperacional, 'percentual' => round(($resultadoOperacional / $pctBase) * 100, 1), 'tipo' => 'resultado'];
        $linhas[] = ['descricao' => '(+/-) RESULTADO FINANCEIRO', 'valor' => $resultadoFinanceiro, 'percentual' => round(($resultadoFinanceiro / $pctBase) * 100, 1), 'tipo' => 'subtotal'];
        $linhas[] = ['descricao' => '= RESULTADO LIQUIDO', 'valor' => $resultadoLiquido, 'percentual' => round(($resultadoLiquido / $pctBase) * 100, 1), 'tipo' => 'final'];

        return [
            'linhas' => $linhas,
            'receitaBruta' => $receitaBruta,
            'receitaLiquida' => $receitaLiquida,
            'lucroBruto' => $lucroBruto,
            'despesasTotal' => $despesasTotal,
            'resultadoOperacional' => $resultadoOperacional,
            'resultadoLiquido' => $resultadoLiquido,
        ];
    }
}
