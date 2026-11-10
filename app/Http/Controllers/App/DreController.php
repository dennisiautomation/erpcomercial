<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\PlanoContas;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DreController extends Controller
{
    public function index(Request $request)
    {
        if ($guard = $this->guardAdminSemEmpresa()) {
            return $guard;
        }
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $unidadeId = $request->input('unidade_id');

        $unidades = Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get();

        $regime = $this->regime($request);

        // Build DRE
        $dre = $this->buildDre($empresaId, $dataInicio, $dataFim, $unidadeId, $regime);

        // Previous period for comparison
        $diasPeriodo = Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1;
        $dataInicioAnterior = Carbon::parse($dataInicio)->subDays($diasPeriodo)->format('Y-m-d');
        $dataFimAnterior = Carbon::parse($dataInicio)->subDay()->format('Y-m-d');
        $dreAnterior = $this->buildDre($empresaId, $dataInicioAnterior, $dataFimAnterior, $unidadeId, $regime);
        // por chave, não por posição: as linhas de detalhe mudam de um período para o outro
        $anteriorPorChave = collect($dreAnterior['linhas'])->pluck('valor', 'chave');

        return view('app.dre.index', compact(
            'dre', 'dreAnterior', 'anteriorPorChave', 'unidades', 'dataInicio', 'dataFim', 'unidadeId', 'regime'
        ));
    }

    public function porUnidade(Request $request)
    {
        if ($guard = $this->guardAdminSemEmpresa()) {
            return $guard;
        }
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $unidades = Unidade::where('empresa_id', $empresaId)->orderBy('nome')->get();

        $regime = $this->regime($request);

        $dresPorUnidade = [];
        foreach ($unidades as $unidade) {
            $dre = $this->buildDre($empresaId, $dataInicio, $dataFim, $unidade->id, $regime);
            $dresPorUnidade[$unidade->id] = [
                'unidade' => $unidade,
                'dre' => $dre,
                'porChave' => collect($dre['linhas'])->pluck('valor', 'chave'),
            ];
        }
        // As linhas da tabela vêm do consolidado (tem todas as contas de todas as lojas);
        // cada loja preenche a sua coluna pela chave.
        $linhasRef = $this->buildDre($empresaId, $dataInicio, $dataFim, null, $regime)['linhas'];

        return view('app.dre.por-unidade', compact('dresPorUnidade', 'linhasRef', 'unidades', 'dataInicio', 'dataFim', 'regime'));
    }

    public function exportar(Request $request)
    {
        if ($guard = $this->guardAdminSemEmpresa()) {
            return $guard;
        }
        $empresaId = auth()->user()->empresa_id;

        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $unidadeId = $request->input('unidade_id');

        $dre = $this->buildDre($empresaId, $dataInicio, $dataFim, $unidadeId, $this->regime($request));

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

    /** Admin da plataforma não tem empresa (armadilha 21) — DRE é por empresa. */
    private function guardAdminSemEmpresa(): ?\Illuminate\Http\RedirectResponse
    {
        if (auth()->user()->empresa_id) {
            return null;
        }

        return redirect()->route('admin.dashboard')
            ->with('warning', 'O admin da plataforma não tem DRE próprio — o DRE é da empresa.');
    }

    /** `pagamento` (caixa, o padrão de sempre) ou `competencia`. */
    private function regime(Request $request): string
    {
        return $request->input('regime') === 'competencia' ? 'competencia' : 'pagamento';
    }

    private function buildDre(int $empresaId, string $dataInicio, string $dataFim, ?int $unidadeId = null, string $regime = 'pagamento'): array
    {
        // 1. RECEITA BRUTA
        //    pagamento: o que foi recebido no período · competência: o que foi lançado no período
        //    (recebido ou não), pelo valor recebido quando já foi.
        $queryReceitas = ContaReceber::where('empresa_id', $empresaId);
        if ($regime === 'competencia') {
            $queryReceitas->whereIn('status', ['pendente', 'paga', 'vencida'])
                ->whereDate('created_at', '>=', $dataInicio)
                ->whereDate('created_at', '<=', $dataFim);
        } else {
            $queryReceitas->where('status', 'paga')
                ->whereDate('pago_em', '>=', $dataInicio)
                ->whereDate('pago_em', '<=', $dataFim);
        }
        if ($unidadeId) {
            $queryReceitas->where('unidade_id', $unidadeId);
        }
        $valorReceita = $regime === 'competencia'
            ? "CASE WHEN status = 'paga' THEN valor_pago ELSE valor END"
            : 'valor_pago';

        $receitaBruta = (float) (clone $queryReceitas)->sum(DB::raw($valorReceita));
        $receitasPorConta = (clone $queryReceitas)
            ->whereNotNull('plano_conta_id')
            ->selectRaw("plano_conta_id, SUM({$valorReceita}) as total")
            ->groupBy('plano_conta_id')
            ->pluck('total', 'plano_conta_id');

        // 2. DEDUCOES — sem fonte própria ainda
        $deducoes = 0;
        $receitaLiquida = $receitaBruta - $deducoes;

        // 3. CUSTOS e DESPESAS — contas a pagar do período, classificadas pelo plano de contas
        $contas = ContaPagar::where('empresa_id', $empresaId)
            ->doPeriodo($regime, $dataInicio, $dataFim)
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->with('planoConta.parent')
            ->get();

        $custos = $contas->filter(fn ($c) => $c->planoConta?->tipo === 'custo');
        $despesas = $contas->reject(fn ($c) => $c->planoConta?->tipo === 'custo');
        $pessoal = $despesas->filter(fn ($c) => $c->eh_pessoal);
        $outras = $despesas->reject(fn ($c) => $c->eh_pessoal);

        $cmv = (float) $custos->sum('valor_efetivo');
        $despesasPessoal = (float) $pessoal->sum('valor_efetivo');
        $despesasTotal = (float) $despesas->sum('valor_efetivo');
        $despesasSemContaTotal = (float) $outras->whereNull('plano_conta_id')->sum('valor_efetivo');

        $lucroBruto = $receitaLiquida - $cmv;
        $resultadoOperacional = $lucroBruto - $despesasTotal;
        $resultadoFinanceiro = 0; // sem fonte própria ainda
        $resultadoLiquido = $resultadoOperacional + $resultadoFinanceiro;

        // ---- Linhas. `chave` identifica a linha: é por ela (não pela posição) que o período
        //      anterior e o DRE por unidade se alinham — as linhas de detalhe mudam de um para outro.
        $pctBase = $receitaBruta > 0 ? $receitaBruta : 1;
        $linhas = [];
        $add = function (string $chave, string $descricao, float $valor, string $tipo, int $nivel = 0) use (&$linhas, $pctBase) {
            $linhas[] = [
                'chave'      => $chave,
                'descricao'  => str_repeat('   ', $nivel) . $descricao,
                'rotulo'     => $descricao,
                'nivel'      => $nivel,
                'valor'      => $valor,
                'percentual' => round((abs($valor) / $pctBase) * 100, 1),
                'tipo'       => $tipo,
            ];
        };
        // Agrupa as contas por GRUPO do plano (conta-pai) e, dentro dele, por conta.
        $porGrupo = function ($contas, string $prefixo, int $nivel, string $semPlano) use ($add) {
            $grupos = $contas->groupBy(fn ($c) => $c->planoConta
                ? ($c->planoConta->parent ? 'g' . $c->planoConta->parent->id : 'c' . $c->planoConta->id)
                : 'sem')
                ->sortBy(fn ($itens, $k) => $k === 'sem' ? 'zzz' : ($itens->first()->planoConta->parent?->codigo ?? $itens->first()->planoConta->codigo));

            foreach ($grupos as $k => $itens) {
                $total = -(float) $itens->sum('valor_efetivo');
                if ($k === 'sem') {
                    $add("{$prefixo}.sem", $semPlano, $total, 'detalhe', $nivel);

                    continue;
                }
                $pai = $itens->first()->planoConta->parent;
                if (! $pai) { // conta lançável solta na raiz do plano
                    $add("{$prefixo}.c{$itens->first()->plano_conta_id}", $itens->first()->planoConta->nome, $total, 'detalhe', $nivel);

                    continue;
                }
                $add("{$prefixo}.g{$pai->id}", $pai->nome, $total, 'grupo', $nivel);
                foreach ($itens->groupBy('plano_conta_id')->sortBy(fn ($i) => $i->first()->planoConta->codigo) as $contaId => $daConta) {
                    $add("{$prefixo}.c{$contaId}", $daConta->first()->planoConta->nome, -(float) $daConta->sum('valor_efetivo'), 'detalhe', $nivel + 1);
                }
            }
        };

        $add('receita', 'RECEITA BRUTA', $receitaBruta, 'header');
        if ($receitasPorConta->isNotEmpty()) {
            $nomes = PlanoContas::whereIn('id', $receitasPorConta->keys())->pluck('nome', 'id');
            foreach ($receitasPorConta as $contaId => $total) {
                $add("receita.c{$contaId}", $nomes[$contaId] ?? 'Outros', (float) $total, 'detalhe', 1);
            }
        }
        $add('deducoes', '(-) DEDUCOES', -$deducoes, 'subtotal');
        $add('receita_liquida', '= RECEITA LIQUIDA', $receitaLiquida, 'resultado');
        $add('cmv', '(-) CUSTO DAS MERCADORIAS VENDIDAS (CMV)', -$cmv, 'subtotal');
        $add('lucro_bruto', '= LUCRO BRUTO', $lucroBruto, 'resultado');
        $add('despesas', '(-) DESPESAS OPERACIONAIS', -$despesasTotal, 'header');

        if ($pessoal->isNotEmpty()) {
            $add('pessoal', 'Despesas com Pessoal', -$despesasPessoal, 'grupo', 1);
            $porGrupo($pessoal, 'pessoal', 2, 'Pessoal sem plano de contas');
        }
        $porGrupo($outras->whereNotNull('plano_conta_id'), 'despesas', 1, '');
        if ($despesasSemContaTotal > 0) {
            $add('despesas.sem', 'Outras Despesas (sem classificacao)', -$despesasSemContaTotal, 'detalhe', 1);
        }

        $add('resultado_operacional', '= RESULTADO OPERACIONAL', $resultadoOperacional, 'resultado');
        $add('resultado_financeiro', '(+/-) RESULTADO FINANCEIRO', $resultadoFinanceiro, 'subtotal');
        $add('resultado_liquido', '= RESULTADO LIQUIDO', $resultadoLiquido, 'final');

        return [
            'linhas' => $linhas,
            'regime' => $regime,
            'receitaBruta' => $receitaBruta,
            'receitaLiquida' => $receitaLiquida,
            'lucroBruto' => $lucroBruto,
            'despesasTotal' => $despesasTotal,
            'despesasPessoal' => $despesasPessoal,
            'resultadoOperacional' => $resultadoOperacional,
            'resultadoLiquido' => $resultadoLiquido,
        ];
    }
}
