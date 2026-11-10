<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Despesas por centro de custo (10/11/2026). Só LÊ o Contas a Pagar: quanto cada centro de
 * custo consumiu no período, separado em Pessoal × Outras despesas × Custos, com o detalhe
 * por conta do plano. Fica no Financeiro (permission:financeiro) e não em Relatórios, porque
 * mostra salário e Relatórios é aberto ao vendedor e ao caixa.
 */
class DespesaCentroCustoController extends Controller
{
    private const REGIMES = ['pagamento', 'competencia', 'vencimento'];

    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;
        if (! $empresaId) {
            return redirect()->route('admin.dashboard')
                ->with('warning', 'Este relatório é da empresa — entre no sistema do cliente para ver.');
        }

        $dataInicio = $request->filled('data_inicio') ? Carbon::parse($request->data_inicio) : now()->startOfMonth();
        $dataFim = $request->filled('data_fim') ? Carbon::parse($request->data_fim) : now()->endOfMonth();
        $regime = in_array($request->input('regime'), self::REGIMES, true) ? $request->input('regime') : 'pagamento';
        $unidadeId = $request->integer('unidade_id') ?: null;

        $contas = ContaPagar::where('empresa_id', $empresaId)
            ->doPeriodo($regime, $dataInicio, $dataFim)
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->with(['planoConta.parent', 'centroCusto'])
            ->get();

        $total = (float) $contas->sum('valor_efetivo');

        $centros = $contas
            // vínculo primeiro; conta antiga só com o texto entra pelo texto; sem nada = "Sem centro de custo"
            ->groupBy(fn ($c) => $c->centro_custo_id ? 'id:' . $c->centro_custo_id : (trim((string) $c->centro_custo) !== '' ? 'txt:' . mb_strtolower(trim($c->centro_custo)) : 'sem'))
            ->map(function ($itens, $chave) use ($total) {
                $custos = $itens->filter(fn ($c) => $c->planoConta?->tipo === 'custo');
                $despesas = $itens->reject(fn ($c) => $c->planoConta?->tipo === 'custo');
                $soma = (float) $itens->sum('valor_efetivo');

                return [
                    'chave'   => $chave,
                    'nome'    => $chave === 'sem' ? 'Sem centro de custo' : ($itens->first()->centroCusto?->nome ?? $itens->first()->centro_custo),
                    'pessoal' => (float) $despesas->filter(fn ($c) => $c->eh_pessoal)->sum('valor_efetivo'),
                    'outras'  => (float) $despesas->reject(fn ($c) => $c->eh_pessoal)->sum('valor_efetivo'),
                    'custos'  => (float) $custos->sum('valor_efetivo'),
                    'total'   => $soma,
                    'pago'    => (float) $itens->where('status', 'paga')->sum('valor_efetivo'),
                    'contas'  => $itens->count(),
                    'pct'     => $total > 0 ? round($soma / $total * 100, 1) : 0,
                    'detalhe' => $itens
                        ->groupBy(fn ($c) => $c->plano_conta_id ?: 'sem')
                        ->map(fn ($daConta) => [
                            'nome'  => $daConta->first()->planoConta
                                ? $daConta->first()->planoConta->codigo . ' — ' . $daConta->first()->planoConta->nome
                                : ($daConta->first()->categoria ? $daConta->first()->categoria . ' (sem plano de contas)' : 'Sem classificação'),
                            'ordem' => $daConta->first()->planoConta?->codigo ?? 'zzz',
                            'total' => (float) $daConta->sum('valor_efetivo'),
                            'contas' => $daConta->count(),
                        ])
                        ->sortBy('ordem')->values(),
                ];
            })
            ->sortBy(fn ($c) => $c['chave'] === 'sem' ? 'zzz' : mb_strtolower($c['nome']))
            ->values();

        $totais = [
            'pessoal' => (float) $centros->sum('pessoal'),
            'outras'  => (float) $centros->sum('outras'),
            'custos'  => (float) $centros->sum('custos'),
            'total'   => $total,
            'pago'    => (float) $centros->sum('pago'),
        ];

        $unidades = Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']);

        return view('app.financeiro.despesas-centro-custo', compact(
            'centros', 'totais', 'dataInicio', 'dataFim', 'regime', 'unidadeId', 'unidades'
        ));
    }
}
