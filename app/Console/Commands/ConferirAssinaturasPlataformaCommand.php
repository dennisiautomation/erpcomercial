<?php

namespace App\Console\Commands;

use App\Models\PlataformaFatura;
use App\Services\Plataforma\AssinaturaSite;
use Illuminate\Console\Command;

/**
 * Rede de segurança da assinatura pelo sistema (20/11/2026) — a cada 10 minutos:
 *  1. fatura em aberto com link de cartão gerado nos últimos 10 dias: pergunta à InfinitePay
 *     se foi paga (o aviso dela pode se perder);
 *  2. fatura paga no cartão cuja baixa o caixa.ia365 ainda não recebeu: pede de novo.
 */
class ConferirAssinaturasPlataformaCommand extends Command
{
    protected $signature = 'plataforma:conferir-assinaturas';

    protected $description = 'Confere links de cartão das faturas da plataforma e repete a baixa pendente no caixa.ia365';

    public function handle(): int
    {
        $pagas = 0;
        if (AssinaturaSite::cartaoDisponivel()) {
            $abertas = PlataformaFatura::pendentes()->whereNotNull('cartao_nsu')->whereNull('cartao_pago_em')
                ->where('cartao_gerado_em', '>=', now()->subDays(10))->limit(50)->get();
            foreach ($abertas as $f) {
                if (AssinaturaSite::conferirCartao($f)['pago']) {
                    $pagas++;
                }
            }
        }

        $baixas = 0;
        foreach (PlataformaFatura::where('caixa_baixa_pendente', true)->limit(50)->get() as $f) {
            if (AssinaturaSite::avisarCaixaDaBaixa($f)) {
                $baixas++;
            }
        }

        $this->info("Cartão confirmado: {$pagas} · baixas entregues ao caixa.ia365: {$baixas}");

        return self::SUCCESS;
    }
}
