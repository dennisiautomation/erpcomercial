<?php

namespace App\Services;

use App\Models\ContaPagar;
use App\Models\FolhaItem;
use App\Models\FuncionarioFicha;
use App\Models\PlanoContas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Folha de pagamento → Contas a Pagar.
 *
 * A folha não paga nem calcula imposto: ela LANÇA no Contas a Pagar o que se repete todo mês
 * (salário, vale, guias e benefícios de valor digitado). Fluxo de caixa, centro de custo e DRE
 * leem o Contas a Pagar — não leem a folha.
 *
 * Uma conta da folha é identificada por (empresa, competência, funcionário, `folha_ref`):
 * lançar o mesmo mês de novo NÃO duplica. Conta já lançada só muda de valor se pedirem
 * (`atualizarPendentes`) e só enquanto estiver pendente.
 */
class FolhaService
{
    /** Código do plano de contas padrão de cada linha (PlanoContasController::PADRAO). */
    private const PLANO_SALARIO = '3.1.01';
    private const PLANO_VALE = '3.1.02';

    /**
     * Tudo que a folha lançaria para UMA competência (1º dia do mês), sem gravar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function linhas(int $empresaId, Carbon $competencia): Collection
    {
        $competencia = $competencia->copy()->startOfMonth();
        $planos = PlanoContas::where('empresa_id', $empresaId)
            ->whereIn('codigo', [self::PLANO_SALARIO, self::PLANO_VALE])
            ->where('natureza', 'analitica')
            ->get()->keyBy('codigo');
        $linhas = collect();
        $mes = $competencia->format('m/Y');

        $fichas = FuncionarioFicha::where('empresa_id', $empresaId)
            ->where('na_folha', true)
            ->with(['funcionario', 'centroCusto'])
            ->get()
            ->filter(fn ($f) => $f->funcionario && $this->trabalhaEm($f, $competencia))
            ->sortBy(fn ($f) => $f->funcionario->name);

        foreach ($fichas as $ficha) {
            $nome = $ficha->funcionario->name;
            $comum = [
                'funcionario_id'  => $ficha->user_id,
                'favorecido_nome' => null,
                'favorecido'      => $nome,
                'unidade_id'      => $ficha->unidade_id,
                'centro_custo_id' => $ficha->centro_custo_id,
                'centro_custo'    => $ficha->centroCusto?->nome,
            ];

            if ((float) $ficha->vale_valor > 0) {
                $linhas->push($comum + [
                    'ref'        => 'vale',
                    'grupo'      => 'Vale (adiantamento)',
                    'descricao'  => "Vale {$mes} — {$nome}",
                    'valor'      => (float) $ficha->vale_valor,
                    'vencimento' => $this->dia($competencia, (int) $ficha->vale_dia),
                    'plano'      => $planos->get(self::PLANO_VALE),
                ]);
            }

            if ($ficha->saldo_salario > 0) {
                $linhas->push($comum + [
                    'ref'        => 'salario',
                    'grupo'      => 'Salário',
                    'descricao'  => "Salário {$mes} — {$nome}",
                    'valor'      => $ficha->saldo_salario,
                    'vencimento' => $this->dia(
                        $ficha->paga_mes_seguinte ? $competencia->copy()->addMonthNoOverflow() : $competencia,
                        (int) $ficha->dia_pagamento
                    ),
                    'plano'      => $planos->get(self::PLANO_SALARIO),
                ]);
            }
        }

        $itens = FolhaItem::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('valor', '>', 0)
            ->with(['funcionario', 'planoConta', 'centroCusto'])
            ->orderBy('tipo')->orderBy('nome')
            ->get();

        foreach ($itens as $item) {
            if ($item->funcionario_id) {
                // Item de funcionário segue a ficha dele: fora da folha / demitido não lança.
                $ficha = FuncionarioFicha::where('empresa_id', $empresaId)->where('user_id', $item->funcionario_id)->first();
                if (! $item->funcionario || ($ficha && (! $ficha->na_folha || ! $this->trabalhaEm($ficha, $competencia)))) {
                    continue;
                }
            }

            $paraQuem = $item->favorecido_nome ?: ($item->funcionario?->name ?? $item->nome);
            $linhas->push([
                'ref'             => 'item:' . $item->id,
                'grupo'           => FolhaItem::TIPOS[$item->tipo] ?? 'Outro',
                'descricao'       => $item->nome . " {$mes}" . ($item->funcionario ? ' — ' . $item->funcionario->name : ''),
                'valor'           => (float) $item->valor,
                'vencimento'      => $this->dia(
                    $item->vence_mes_seguinte ? $competencia->copy()->addMonthNoOverflow() : $competencia,
                    (int) $item->dia_vencimento
                ),
                'plano'           => $item->planoConta,
                // Favorecido: o nome digitado (INSS, operadora) ou, sem ele, o próprio funcionário.
                'funcionario_id'  => $item->favorecido_nome ? null : $item->funcionario_id,
                'favorecido_nome' => $item->funcionario_id && ! $item->favorecido_nome ? null : $paraQuem,
                'favorecido'      => $paraQuem,
                'dono_id'         => $item->funcionario_id, // de quem é o item (para a chave)
                'unidade_id'      => $item->unidade_id,
                'centro_custo_id' => $item->centro_custo_id,
                'centro_custo'    => $item->centroCusto?->nome,
            ]);
        }

        return $linhas->values();
    }

    /**
     * Lança `meses` competências a partir de `$inicio`. Devolve os totais do que fez.
     *
     * @return array{criadas:int, existentes:int, atualizadas:int, valor:float}
     */
    public function lancar(int $empresaId, Carbon $inicio, int $meses, bool $atualizarPendentes = false): array
    {
        $r = ['criadas' => 0, 'existentes' => 0, 'atualizadas' => 0, 'valor' => 0.0];

        DB::transaction(function () use ($empresaId, $inicio, $meses, $atualizarPendentes, &$r) {
            for ($m = 0; $m < $meses; $m++) {
                $competencia = $inicio->copy()->startOfMonth()->addMonthsNoOverflow($m);

                foreach ($this->linhas($empresaId, $competencia) as $linha) {
                    $existente = $this->contaDaLinha($empresaId, $competencia, $linha);

                    if ($existente) {
                        $r['existentes']++;
                        if ($atualizarPendentes && $existente->status === 'pendente'
                            && abs((float) $existente->valor - $linha['valor']) >= 0.005) {
                            $existente->update(['valor' => $linha['valor']]);
                            $r['atualizadas']++;
                        }

                        continue;
                    }

                    ContaPagar::create([
                        'empresa_id'       => $empresaId,
                        'unidade_id'       => $linha['unidade_id'] ?? session('unidade_id'),
                        'fornecedor_id'    => null,
                        'funcionario_id'   => $linha['funcionario_id'],
                        'favorecido_nome'  => $linha['favorecido_nome'],
                        'descricao'        => Str::limit($linha['descricao'], 255, ''),
                        'valor'            => $linha['valor'],
                        'vencimento'       => $linha['vencimento'],
                        'competencia'      => $competencia,
                        'plano_conta_id'   => $linha['plano']?->id,
                        'categoria'        => $linha['plano'] ? Str::limit($linha['plano']->nome, 100, '') : 'Folha de Pagamento',
                        'centro_custo_id'  => $linha['centro_custo_id'],
                        'centro_custo'     => $linha['centro_custo'] ? Str::limit($linha['centro_custo'], 100, '') : null,
                        'parcela'          => 1,
                        'total_parcelas'   => 1,
                        'serie_id'         => $this->serieDaLinha($empresaId, $linha),
                        'folha_ref'        => $linha['ref'],
                        'recorrente'       => true,
                        'recorrencia_tipo' => 'mensal',
                        'status'           => 'pendente',
                    ]);
                    $r['criadas']++;
                    $r['valor'] += $linha['valor'];
                }
            }
        });

        return $r;
    }

    /** Resumo por competência para a tela: previsto × já lançado × pago. */
    public function resumo(int $empresaId, Carbon $inicio, int $meses): Collection
    {
        return collect(range(0, $meses - 1))->map(function ($m) use ($empresaId, $inicio) {
            $competencia = $inicio->copy()->startOfMonth()->addMonthsNoOverflow($m);
            $linhas = $this->linhas($empresaId, $competencia);
            $contas = ContaPagar::where('empresa_id', $empresaId)
                ->whereDate('competencia', $competencia)
                ->whereNotNull('folha_ref')
                ->get(['valor', 'valor_pago', 'status']);

            return [
                'competencia'  => $competencia,
                'previsto'     => (float) $linhas->sum('valor'),
                'linhas'       => $linhas->count(),
                'lancadas'     => $contas->count(),
                'lancado'      => (float) $contas->sum('valor'),
                'pagas'        => $contas->where('status', 'paga')->count(),
                'pago'         => (float) $contas->where('status', 'paga')->sum('valor_pago'),
            ];
        });
    }

    /* ------------------------------------------------------------------ */

    private function contaDaLinha(int $empresaId, Carbon $competencia, array $linha): ?ContaPagar
    {
        // withTrashed: conta da folha EXCLUÍDA de propósito não é lançada de novo.
        return $this->contasDaRef($empresaId, $linha)
            ->withTrashed()
            ->whereDate('competencia', $competencia)
            ->first();
    }

    /** A mesma linha (ex.: salário do João) usa uma série só — é o que liga "esta e as próximas". */
    private function serieDaLinha(int $empresaId, array $linha): string
    {
        return $this->contasDaRef($empresaId, $linha)->whereNotNull('serie_id')->value('serie_id')
            ?? (string) Str::uuid();
    }

    private function contasDaRef(int $empresaId, array $linha)
    {
        $query = ContaPagar::where('empresa_id', $empresaId)->where('folha_ref', $linha['ref']);

        // salário/vale repetem a ref em todo funcionário; `item:<id>` já é única.
        if (in_array($linha['ref'], ['salario', 'vale'], true)) {
            $query->where('funcionario_id', $linha['funcionario_id']);
        }

        return $query;
    }

    /** Funcionário já admitido e ainda não desligado no mês da competência. */
    private function trabalhaEm(FuncionarioFicha $ficha, Carbon $competencia): bool
    {
        if ($ficha->admissao && $ficha->admissao->copy()->startOfMonth()->gt($competencia)) {
            return false;
        }

        return ! ($ficha->demissao && $ficha->demissao->copy()->startOfMonth()->lt($competencia));
    }

    /** Dia do mês, sem estourar: dia 31 em fevereiro vira o último dia. */
    private function dia(Carbon $mes, int $dia): Carbon
    {
        $mes = $mes->copy()->startOfMonth();

        return $mes->day(min(max($dia, 1), $mes->daysInMonth));
    }
}
