<?php

namespace App\Services\Plataforma;

use App\Enums\Perfil;
use App\Mail\FaturaPlataformaAviso;
use App\Models\Empresa;
use App\Models\PlataformaFatura;
use App\Models\User;
use App\Services\NotificacaoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Faturas da plataforma (IA365 cobra a empresa-cliente) — 16/11/2026.
 *
 * ONDE CADA COISA VIVE: a cobrança (PIX/boleto do Inter, recorrência, WhatsApp
 * e a baixa pelo banco) nasce e é baixada no caixa.ia365. O ERP guarda um
 * espelho da fatura e faz só quatro coisas com ele: mostra a faixa no topo,
 * mostra a tela "Faturas", manda e-mail/sino aos responsáveis e bloqueia.
 *
 * Fatura lançada à mão no admin (origem 'erp') usa as mesmas quatro coisas.
 */
class FaturasPlataforma
{
    /** Quem vê valor, faixa completa, tela "Faturas" e recebe e-mail e sino. */
    public const PERFIS = ['dono', 'gerente', 'admin', 'financeiro'];

    /** Avisos possíveis — cada fatura manda cada um UMA vez (coluna `avisos`). */
    public const AVISOS = ['emitida', 'vence', 'atraso', 'suspensa', 'paga'];

    public static function podeVer(?User $user): bool
    {
        if (! $user || ! $user->empresa_id) {
            return false;
        }
        $perfil = $user->perfil instanceof Perfil ? $user->perfil->value : (string) $user->perfil;

        return in_array($perfil, self::PERFIS, true);
    }

    /** @return Collection<int, User> */
    public static function destinatarios(Empresa $empresa): Collection
    {
        return User::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->whereIn('perfil', self::PERFIS)
            ->where('status', 'ativo')
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /*  Avisos (sino + e-mail)                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Avisa os responsáveis da empresa — sino e e-mail — uma vez por tipo.
     * Devolve quantas pessoas foram avisadas (0 = já tinha sido avisado).
     */
    public static function avisar(PlataformaFatura $fatura, string $tipo): int
    {
        $avisos = (array) ($fatura->avisos ?? []);
        if (! in_array($tipo, self::AVISOS, true) || isset($avisos[$tipo])) {
            return 0;
        }

        $empresa = $fatura->empresa;
        if (! $empresa) {
            return 0;
        }

        // Marca ANTES de mandar: se o e-mail de alguém falhar, não repete para todos amanhã.
        $avisos[$tipo] = now()->format('Y-m-d H:i:s');
        $fatura->forceFill(['avisos' => $avisos])->save();

        [$titulo, $mensagem, $cor] = self::textoDoSino($fatura, $tipo);
        $n = 0;

        foreach (self::destinatarios($empresa) as $user) {
            $n++;
            NotificacaoService::criar($user->id, $empresa->id, 'fatura_plataforma', $titulo, $mensagem, '/app/faturas', 'cash-coin', $cor);

            if (! filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                Mail::to($user->email)->send(new FaturaPlataformaAviso($fatura, $tipo, $user));
            } catch (\Throwable $e) {
                Log::warning('[FaturaPlataforma] e-mail não saiu', ['fatura_id' => $fatura->id, 'tipo' => $tipo, 'user_id' => $user->id, 'erro' => $e->getMessage()]);
            }
        }

        Log::info('[FaturaPlataforma] aviso', ['fatura_id' => $fatura->id, 'empresa_id' => $empresa->id, 'tipo' => $tipo, 'pessoas' => $n]);

        return $n;
    }

    /** @return array{0: string, 1: string, 2: string} título, mensagem, cor */
    public static function textoDoSino(PlataformaFatura $f, string $tipo): array
    {
        $valor = 'R$ ' . number_format((float) $f->valor, 2, ',', '.');
        $vence = $f->vencimento->format('d/m/Y');

        return match ($tipo) {
            'emitida' => ['Fatura disponível', "{$f->titulo()} — {$valor}, vence em {$vence}.", 'info'],
            'vence' => ['Fatura vence em breve', "{$f->titulo()} — {$valor}, vence em {$vence}.", 'warning'],
            'atraso' => ['Fatura em atraso', "{$f->titulo()} — {$valor}, venceu em {$vence}. Regularize para evitar a suspensão do acesso.", 'danger'],
            'suspensa' => ['Acesso suspenso por pendência financeira', "{$f->titulo()} — {$valor}, venceu em {$vence}. Pague a fatura para liberar o acesso.", 'danger'],
            default => ['Pagamento confirmado', "{$f->titulo()} — {$valor}. Obrigado!", 'success'],
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Baixa e bloqueio                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Marca a fatura como paga, agenda a próxima renovação da anual e libera o
     * acesso se não sobrou pendência estourada. Usado pelo admin e pelo caixa.ia365.
     */
    public static function baixar(PlataformaFatura $fatura, array $dados = []): void
    {
        $fatura->update([
            'status' => 'paga',
            'pago_em' => $dados['pago_em'] ?? Carbon::today(),
            'forma_pagamento' => $dados['forma_pagamento'] ?? $fatura->forma_pagamento,
            'observacao' => $dados['observacao'] ?? $fatura->observacao,
            'marcada_por' => $dados['marcada_por'] ?? null,
        ]);

        $empresa = $fatura->empresa;
        if (! $empresa) {
            return;
        }

        // Anual paga → agenda a próxima renovação (+1 ano sobre o vencimento)
        if ($empresa->cobranca_periodicidade === 'anual'
            && $empresa->cobranca_proxima_renovacao
            && $fatura->vencimento->isSameDay($empresa->cobranca_proxima_renovacao)) {
            $empresa->cobranca_proxima_renovacao = $fatura->vencimento->copy()->addYear();
            $empresa->save();
        }

        AssinaturaSite::aoPagar($empresa); // empresa do site em teste: a 1ª paga vira assinatura
        self::liberarSePossivel($empresa);
    }

    /** Libera o acesso se a empresa está suspensa e não sobrou NENHUMA pendente estourada. */
    public static function liberarSePossivel(Empresa $empresa): bool
    {
        if (! $empresa->estaSuspensa()) {
            return false;
        }
        $aindaEstourada = PlataformaFatura::where('empresa_id', $empresa->id)->pendentes()->get()
            ->contains(fn ($f) => $f->setRelation('empresa', $empresa)->passouDaTolerancia());
        if ($aindaEstourada) {
            return false;
        }
        $empresa->forceFill(['cobranca_suspensa_em' => null])->save();
        Log::info('[FaturaPlataforma] acesso liberado', ['empresa_id' => $empresa->id]);

        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  O que chega do caixa.ia365                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Recebe o estado de UMA cobrança do caixa.ia365 e espelha na fatura.
     * Pode chegar repetido: o resultado é sempre o mesmo (a chave é `ref`).
     *
     * @param  array{empresa_id:int, ref:int, situacao:string, valor:float|string, vencimento:string, descricao?:?string,
     *               competencia?:?string, pago_em?:?string, link?:?string, pix_copia_cola?:?string, linha_digitavel?:?string,
     *               tem_nota?:bool, cliente_id?:?int, cliente_nome?:?string}  $d
     * @return array{fatura_id: ?int, status: string, criada: bool, avisados: int}
     */
    public static function receberDoCaixa(Empresa $empresa, array $d): array
    {
        $situacao = $d['situacao']; // aberta | paga | cancelada
        $fatura = PlataformaFatura::where('caixa_ref', $d['ref'])->first();
        $criada = false;
        $avisados = 0;

        if (! empty($d['cliente_id']) && (int) $empresa->caixa_cliente_id !== (int) $d['cliente_id']) {
            self::vincular($empresa, (int) $d['cliente_id'], $d['cliente_nome'] ?? null);
        }

        if (! $fatura) {
            if ($situacao === 'cancelada') {
                return ['fatura_id' => null, 'status' => 'ignorada', 'criada' => false, 'avisados' => 0];
            }
            $fatura = new PlataformaFatura(['empresa_id' => $empresa->id, 'origem' => 'caixa', 'caixa_ref' => $d['ref'], 'status' => 'pendente']);
            $criada = true;
        }

        $vencimento = Carbon::parse($d['vencimento'])->startOfDay();
        $fatura->fill([
            'empresa_id' => $empresa->id,
            'descricao' => mb_substr((string) ($d['descricao'] ?? ''), 0, 255) ?: null,
            'competencia' => ($d['competencia'] ?? null) ?: $vencimento->format('Y-m'),
            'valor' => $d['valor'],
            'vencimento' => $vencimento,
            'link' => $d['link'] ?? null,
            'pix_copia_cola' => $d['pix_copia_cola'] ?? null,
            'linha_digitavel' => $d['linha_digitavel'] ?? null,
            'tem_nota' => (bool) ($d['tem_nota'] ?? false),
        ]);
        $fatura->setRelation('empresa', $empresa);

        $antes = $fatura->exists ? $fatura->getOriginal('status') : null;

        if ($situacao === 'paga') {
            if ($antes !== 'paga') {
                $fatura->save();
                self::baixar($fatura, ['pago_em' => ! empty($d['pago_em']) ? Carbon::parse($d['pago_em']) : Carbon::today(), 'forma_pagamento' => 'caixa.ia365']);
                // Fatura que já chega paga (histórico) não avisa ninguém; a que estava em aberto, sim.
                if (! $criada) {
                    $avisados = self::avisar($fatura, 'paga');
                }
            } else {
                $fatura->save();
            }
        } elseif ($situacao === 'cancelada') {
            $fatura->status = 'cancelada';
            $fatura->save();
            self::liberarSePossivel($empresa);
        } elseif ($antes === 'paga' && $fatura->cartao_pago_em) {
            // Paga no CARTÃO aqui e o caixa ainda diz "aberta": a baixa de lá não chegou. Continua paga
            // e o pedido de baixa volta para a fila (plataforma:conferir-assinaturas).
            $fatura->caixa_baixa_pendente = true;
            $fatura->save();
        } else { // aberta
            if ($antes === 'paga' || $antes === 'cancelada') {
                // Baixa desfeita no caixa (ou cobrança reaberta): volta a valer, e o "paga" pode ser avisado de novo.
                $avisos = (array) ($fatura->avisos ?? []);
                unset($avisos['paga']);
                $fatura->fill(['status' => 'pendente', 'pago_em' => null, 'forma_pagamento' => null, 'avisos' => $avisos ?: null]);
            }
            $fatura->save();
            if ($criada) {
                $avisados = self::avisar($fatura, 'emitida');
            }
        }

        return ['fatura_id' => $fatura->id, 'status' => $fatura->status, 'criada' => $criada, 'avisados' => $avisados];
    }

    /**
     * Conferência: o caixa manda as cobranças VIVAS da empresa; a pendente do
     * caixa que não está na lista foi apagada/cancelada lá → cancela aqui.
     */
    public static function conferirVivas(Empresa $empresa, array $refs): int
    {
        $refs = array_map('intval', $refs);
        $sumidas = PlataformaFatura::where('empresa_id', $empresa->id)->where('origem', 'caixa')->pendentes()
            ->when($refs !== [], fn ($q) => $q->whereNotIn('caixa_ref', $refs))
            ->get();
        foreach ($sumidas as $f) {
            $f->update(['status' => 'cancelada', 'observacao' => trim(($f->observacao ? $f->observacao . ' · ' : '') . 'Cancelada na conferência com o caixa.ia365')]);
        }
        if ($sumidas->isNotEmpty()) {
            self::liberarSePossivel($empresa);
        }

        return $sumidas->count();
    }

    public static function vincular(Empresa $empresa, int $clienteId, ?string $nome): void
    {
        $empresa->forceFill([
            'caixa_cliente_id' => $clienteId,
            'caixa_cliente_nome' => $nome ? mb_substr($nome, 0, 150) : $empresa->caixa_cliente_nome,
            'caixa_vinculado_em' => $empresa->caixa_vinculado_em ?: now(),
        ])->save();
    }

    /** Tira o vínculo: as pendentes vindas do caixa deixam de valer aqui. */
    public static function desvincular(Empresa $empresa): int
    {
        $empresa->forceFill(['caixa_cliente_id' => null, 'caixa_cliente_nome' => null, 'caixa_vinculado_em' => null])->save();

        return self::conferirVivas($empresa, []);
    }

    /* ------------------------------------------------------------------ */
    /*  Faixa no topo                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * A faixa que este usuário vê, ou null.
     *
     * Dono, gerente, admin e financeiro: faixa completa desde a emissão.
     * Demais (caixa, vendedor, consulta): só quando há fatura em atraso, e sem valor.
     *
     * @return null|array{nivel: string, restrita: bool, texto: string, detalhe: ?string, fatura_id: int, pendentes: int}
     */
    public static function faixa(?User $user): ?array
    {
        if (! $user || $user->is_admin || ! $user->empresa_id) {
            return null;
        }

        $pendentes = PlataformaFatura::where('empresa_id', $user->empresa_id)->pendentes()->orderBy('vencimento')->get();
        $f = $pendentes->first();
        if (! $f) {
            return null;
        }

        $dias = $f->diasParaVencer();
        $nivel = $dias < 0 ? 'danger' : ($dias <= 3 ? 'warning' : 'info');

        if (! self::podeVer($user)) {
            return $dias < 0
                ? ['nivel' => 'danger', 'restrita' => true, 'texto' => 'Fatura do sistema em atraso.', 'detalhe' => 'Avise o responsável pela loja.', 'fatura_id' => $f->id, 'pendentes' => $pendentes->count()]
                : null;
        }

        $valor = 'R$ ' . number_format((float) $f->valor, 2, ',', '.');
        $quando = match (true) {
            $dias < -1 => 'venceu há ' . abs($dias) . ' dias (' . $f->vencimento->format('d/m') . ')',
            $dias === -1 => 'venceu ontem (' . $f->vencimento->format('d/m') . ')',
            $dias === 0 => 'vence hoje',
            $dias === 1 => 'vence amanhã',
            default => 'vence em ' . $f->vencimento->format('d/m/Y'),
        };

        return [
            'nivel' => $nivel,
            'restrita' => false,
            'texto' => $dias < 0 ? 'Fatura em atraso.' : 'Fatura disponível.',
            'detalhe' => "{$f->titulo()} — {$valor}, {$quando}." . ($pendentes->count() > 1 ? ' Há mais ' . ($pendentes->count() - 1) . ' em aberto.' : ''),
            'fatura_id' => $f->id,
            'pendentes' => $pendentes->count(),
        ];
    }
}
