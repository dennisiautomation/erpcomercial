<?php

namespace App\Services\OpenFinance;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\LancamentoBancario;
use App\Services\FiadoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cruza o que entrou e saiu do banco com as contas a receber e a pagar (17/11/2026).
 *
 * O sistema só SUGERE; a baixa do título acontece quando a pessoa confirma. A nota da
 * sugestão (0–100) soma: valor igual, data perto do vencimento, CPF/CNPJ de quem pagou ou
 * recebeu igual ao do cliente/fornecedor, nome parecido e ser o único título com aquele valor.
 */
class ConciliadorSugestoes
{
    /** Abaixo disso o sistema não sugere nada. */
    public const NOTA_MINIMA = 60;

    /** Título vencido ou a vencer além destes dias do lançamento não é candidato. */
    public const JANELA_DIAS = 45;

    /** Sugere para tudo o que está pendente na empresa; devolve quantos ganharam sugestão. */
    public function sugerirPendentes(int $empresaId): int
    {
        $total = 0;

        LancamentoBancario::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('status', 'pendente')
            ->orderBy('data')
            ->get()
            ->each(function (LancamentoBancario $l) use (&$total) {
                if ($this->sugerir($l)) {
                    $total++;
                }
            });

        return $total;
    }

    public function sugerir(LancamentoBancario $l): bool
    {
        $melhor = $this->candidatos($l)->first();

        if (! $melhor || $melhor['nota'] < self::NOTA_MINIMA) {
            return $this->sugerirPagamentoDeFatura($l);
        }

        $l->update([
            'status' => 'sugerido',
            'sugestao_conta_receber_id' => $l->ehCredito() ? $melhor['titulo']->id : null,
            'sugestao_conta_pagar_id' => $l->ehCredito() ? null : $melhor['titulo']->id,
            'sugestao_confianca' => min(100, $melhor['nota']),
            'sugestao_motivo' => implode(' · ', $melhor['motivos']),
        ]);

        return true;
    }

    /**
     * Saída da CONTA com o mesmo valor de um crédito que apareceu no cartão (pagamento de fatura)
     * em até 5 dias: é a fatura sendo paga. Não é despesa nova — as compras já entram uma a uma.
     */
    private function sugerirPagamentoDeFatura(LancamentoBancario $l): bool
    {
        if ($l->ehCredito() || $l->ehDeCartao()) {
            return false;
        }

        $jaUsados = LancamentoBancario::withoutGlobalScopes()
            ->where('empresa_id', $l->empresa_id)->where('id', '!=', $l->id)
            ->where(fn ($q) => $q->whereNotNull('fatura_cartao_id')->orWhereNotNull('sugestao_fatura_cartao_id'))
            ->where('valor', $l->valor)
            ->count();

        $creditos = LancamentoBancario::withoutGlobalScopes()
            ->where('empresa_id', $l->empresa_id)
            ->whereNotNull('cartao_credito_id')->where('tipo', 'credito')
            ->where('valor', $l->valor)
            ->whereBetween('data', [$l->data->copy()->subDays(5), $l->data->copy()->addDays(5)])
            ->orderBy('data')->get();

        // um crédito no cartão explica uma saída da conta, não duas
        $credito = $creditos->get($jaUsados);
        if (! $credito) {
            return false;
        }

        $l->update([
            'status' => 'sugerido',
            'sugestao_conta_receber_id' => null,
            'sugestao_conta_pagar_id' => null,
            'sugestao_fatura_cartao_id' => $credito->cartao_credito_id,
            'sugestao_confianca' => 85,
            'sugestao_motivo' => 'mesmo valor que entrou no cartão como pagamento de fatura',
        ]);

        return true;
    }

    /**
     * Títulos em aberto que podem ser este lançamento, do mais provável ao menos.
     *
     * @return Collection<int, array{titulo: ContaReceber|ContaPagar, nota: int, motivos: string[]}>
     */
    public function candidatos(LancamentoBancario $l): Collection
    {
        // Crédito no cartão é pagamento de fatura ou estorno: nunca é uma conta a receber.
        if ($l->ehDeCartao() && $l->ehCredito()) {
            return collect();
        }

        $ocupados = $this->titulosOcupados($l);
        $inicio = $l->data->copy()->subDays(self::JANELA_DIAS);
        $fim = $l->data->copy()->addDays(self::JANELA_DIAS);

        if ($l->ehCredito()) {
            $titulos = ContaReceber::withoutGlobalScopes()
                ->with('cliente')
                ->where('empresa_id', $l->empresa_id)
                ->whereIn('status', ['pendente', 'vencida'])
                // Direto com a loja (fiado) se recebe pela ficha do cliente: entra no caixa e gera recibo.
                ->where(fn ($q) => $q->whereNull('forma_pagamento')->orWhere('forma_pagamento', '!=', FiadoService::FORMA))
                ->whereBetween('vencimento', [$inicio, $fim])
                ->whereNotIn('id', $ocupados)
                ->where(fn ($q) => $q->where('valor', $l->valor)->orWhere('valor_liquido', $l->valor))
                ->get();
        } else {
            $titulos = ContaPagar::withoutGlobalScopes()
                ->with(['fornecedor', 'funcionario'])
                ->where('empresa_id', $l->empresa_id)
                ->whereIn('status', ['pendente', 'vencida'])
                ->whereBetween('vencimento', [$inicio, $fim])
                ->whereNotIn('id', $ocupados)
                ->where('valor', $l->valor)
                ->get();
        }

        $unico = $titulos->count() === 1;

        return $titulos
            ->map(fn ($t) => $this->pontuar($l, $t, $unico))
            ->sortByDesc('nota')
            ->values();
    }

    private function pontuar(LancamentoBancario $l, ContaReceber|ContaPagar $t, bool $unico): array
    {
        $nota = 50;
        $motivos = ['mesmo valor'];

        if ($t instanceof ContaReceber && (float) $t->valor !== (float) $l->valor) {
            $nota = 45;
            $motivos = ['valor líquido da maquininha'];
        }

        $dias = (int) abs($l->data->diffInDays($t->vencimento));
        if ($dias <= 3) {
            $nota += 20;
            $motivos[] = $dias === 0 ? 'no dia do vencimento' : "a {$dias} dia(s) do vencimento";
        } elseif ($dias <= 10) {
            $nota += 10;
            $motivos[] = "a {$dias} dias do vencimento";
        } else {
            $nota += 3;
            $motivos[] = "a {$dias} dias do vencimento";
        }

        [$doc, $nome] = $this->pessoaDoTitulo($t);

        if ($l->contraparte_documento && $doc !== '' && $l->contraparte_documento === $doc) {
            $nota += 30;
            $motivos[] = 'mesmo CPF/CNPJ';
        } elseif ($l->contraparte_nome && $nome !== '' && $this->nomesParecidos($l->contraparte_nome, $nome)) {
            $nota += 15;
            $motivos[] = 'nome parecido';
        } elseif ($nome !== '' && $l->descricao && $this->nomesParecidos($l->descricao, $nome)) {
            $nota += 10;
            $motivos[] = 'nome na descrição do banco';
        }

        if ($unico) {
            $nota += 10;
            $motivos[] = 'único título com esse valor';
        }

        return ['titulo' => $t, 'nota' => $nota, 'motivos' => $motivos];
    }

    /** @return array{0: string, 1: string} documento só com dígitos e nome */
    private function pessoaDoTitulo(ContaReceber|ContaPagar $t): array
    {
        if ($t instanceof ContaReceber) {
            return [
                preg_replace('/\D/', '', (string) $t->cliente?->cpf_cnpj),
                (string) ($t->cliente?->nome_razao_social ?? ''),
            ];
        }

        return [
            preg_replace('/\D/', '', (string) $t->fornecedor?->cpf_cnpj),
            (string) ($t->fornecedor?->razao_social ?: $t->funcionario?->name ?: $t->favorecido_nome ?: ''),
        ];
    }

    private function nomesParecidos(string $a, string $b): bool
    {
        $limpar = fn (string $s) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ',
            strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s))));
        $a = $limpar($a);
        $b = $limpar($b);

        if (strlen($a) < 4 || strlen($b) < 4) {
            return false;
        }
        if (str_contains($a, $b) || str_contains($b, $a)) {
            return true;
        }

        similar_text($a, $b, $pct);

        return $pct >= 80;
    }

    /** Títulos já sugeridos ou conciliados em OUTRO lançamento — um título, um lançamento. */
    private function titulosOcupados(LancamentoBancario $l): array
    {
        $col = $l->ehCredito() ? 'conta_receber_id' : 'conta_pagar_id';

        $q = LancamentoBancario::withoutGlobalScopes()
            ->where('empresa_id', $l->empresa_id)
            ->where('id', '!=', $l->id);

        return (clone $q)->where('status', 'conciliado')->whereNotNull($col)->pluck($col)
            ->merge((clone $q)->where('status', 'sugerido')->whereNotNull("sugestao_{$col}")->pluck("sugestao_{$col}"))
            ->unique()->values()->all();
    }

    /**
     * Confirma: liga o lançamento ao título e dá a baixa com a data e o valor do banco.
     * Sem título informado, vale a sugestão do sistema.
     */
    public function confirmar(LancamentoBancario $l, ?int $usuarioId, ?int $tituloId = null): void
    {
        if (! in_array($l->status, ['pendente', 'sugerido'], true)) {
            throw new \DomainException('Este lançamento já foi tratado.');
        }

        // Pagamento de fatura: só marca de qual cartão é. Não cria nem baixa conta nenhuma.
        if (! $tituloId && $l->sugestao_fatura_cartao_id) {
            $this->marcarPagamentoDeFatura($l, $usuarioId, $l->sugestao_fatura_cartao_id);

            return;
        }

        $tituloId ??= $l->ehCredito() ? $l->sugestao_conta_receber_id : $l->sugestao_conta_pagar_id;
        if (! $tituloId) {
            throw new \DomainException('Escolha a conta que corresponde a este lançamento.');
        }

        DB::transaction(function () use ($l, $usuarioId, $tituloId) {
            $modelo = $l->ehCredito() ? ContaReceber::class : ContaPagar::class;
            $titulo = $modelo::withoutGlobalScopes()
                ->where('empresa_id', $l->empresa_id)
                ->whereIn('status', ['pendente', 'vencida'])
                ->lockForUpdate()
                ->find($tituloId);

            if (! $titulo) {
                throw new \DomainException('Esta conta não está mais em aberto.');
            }
            if ($titulo instanceof ContaReceber && $titulo->forma_pagamento === FiadoService::FORMA) {
                throw new \DomainException('Conta direto com a loja se recebe pela ficha do cliente.');
            }
            if (in_array($titulo->id, $this->titulosOcupadosConciliados($l), true)) {
                throw new \DomainException('Esta conta já foi conciliada com outro lançamento.');
            }

            $titulo->update([
                'valor_pago' => $l->valor,
                'pago_em' => $l->data,
                'status' => 'paga',
            ]);

            $col = $l->ehCredito() ? 'conta_receber_id' : 'conta_pagar_id';
            $l->update([
                'status' => 'conciliado',
                $col => $titulo->id,
                'conciliado_em' => now(),
                'conciliado_por' => $usuarioId,
                'sugestao_fatura_cartao_id' => null,
            ]);

            // Outro lançamento que sugeria este mesmo título perde a sugestão.
            LancamentoBancario::withoutGlobalScopes()
                ->where('empresa_id', $l->empresa_id)->where('id', '!=', $l->id)
                ->where('status', 'sugerido')->where("sugestao_{$col}", $titulo->id)
                ->update([
                    'status' => 'pendente', 'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null,
                    'sugestao_confianca' => null, 'sugestao_motivo' => null,
                ]);
        });
    }

    public function marcarPagamentoDeFatura(LancamentoBancario $l, ?int $usuarioId, int $cartaoId): void
    {
        if (! in_array($l->status, ['pendente', 'sugerido'], true)) {
            throw new \DomainException('Este lançamento já foi tratado.');
        }
        if ($l->ehCredito() || $l->ehDeCartao()) {
            throw new \DomainException('Pagamento de fatura é sempre uma saída da conta.');
        }

        $cartao = \App\Models\CartaoCredito::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($cartaoId);
        if (! $cartao) {
            throw new \DomainException('Cartão não encontrado.');
        }

        $l->update([
            'status' => 'conciliado',
            'fatura_cartao_id' => $cartao->id,
            'conciliado_em' => now(),
            'conciliado_por' => $usuarioId,
            'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null, 'sugestao_fatura_cartao_id' => null,
            'sugestao_confianca' => null, 'sugestao_motivo' => null,
        ]);
    }

    private function titulosOcupadosConciliados(LancamentoBancario $l): array
    {
        $col = $l->ehCredito() ? 'conta_receber_id' : 'conta_pagar_id';

        return LancamentoBancario::withoutGlobalScopes()
            ->where('empresa_id', $l->empresa_id)->where('id', '!=', $l->id)
            ->where('status', 'conciliado')->whereNotNull($col)->pluck($col)->all();
    }

    /** Desfaz a conciliação: o título volta a ficar em aberto e o lançamento volta para a fila. */
    public function desfazer(LancamentoBancario $l): void
    {
        if ($l->status !== 'conciliado') {
            throw new \DomainException('Só dá para desfazer um lançamento conciliado.');
        }

        DB::transaction(function () use ($l) {
            $titulo = $l->ehCredito()
                ? ContaReceber::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($l->conta_receber_id)
                : ContaPagar::withoutGlobalScopes()->where('empresa_id', $l->empresa_id)->find($l->conta_pagar_id);

            if ($titulo && $l->titulo_criado) {
                // A conta nasceu deste lançamento: desfazer apaga a conta (não deixa despesa em aberto que nunca existiu).
                $titulo->delete();
            } elseif ($titulo && $titulo->status === 'paga') {
                $titulo->update([
                    'valor_pago' => 0,
                    'pago_em' => null,
                    'status' => 'pendente',
                ]);
            }

            $l->update([
                'status' => 'pendente',
                'conta_receber_id' => null, 'conta_pagar_id' => null, 'fatura_cartao_id' => null, 'sugestao_fatura_cartao_id' => null,
                'conciliado_em' => null, 'conciliado_por' => null, 'titulo_criado' => false,
                'sugestao_conta_receber_id' => null, 'sugestao_conta_pagar_id' => null,
                'sugestao_confianca' => null, 'sugestao_motivo' => null,
            ]);
        });

        $this->sugerir($l->fresh());
    }
}
