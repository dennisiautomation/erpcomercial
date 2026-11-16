<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\PlataformaFatura;
use App\Services\Plataforma\FaturasPlataforma;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cobrança da plataforma (IA365 → empresa-cliente).
 *
 * Roda diariamente:
 *  1. Gera fatura do ciclo para empresas com geração AUTOMÁTICA
 *     (mensal: todo dia 1º para o mês corrente; anual: 30 dias antes da renovação).
 *     Empresa VINCULADA ao caixa.ia365 não gera aqui: a fatura dela vem de lá.
 *  2. Avisa dono, gerente, admin e financeiro (sino + e-mail, uma vez por fatura
 *     e por tipo) quando a fatura vence em ≤3 dias e quando entra em atraso.
 *  3. SUSPENDE a empresa (bloqueio total) quando fatura pendente passa de
 *     vencimento + tolerância e a empresa tem bloqueio automático ligado.
 *
 * Reativação: automática quando a fatura é paga (admin ou caixa.ia365).
 */
class ProcessarCobrancasPlataformaCommand extends Command
{
    protected $signature = 'plataforma:processar-cobrancas {--dry-run : só mostra o que faria}';

    protected $description = 'Gera faturas da plataforma, avisa vencimentos e aplica bloqueio por inadimplência';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $hoje = Carbon::today();

        // Cobrança direta configurada, vinculada ao caixa.ia365 ou com fatura em aberto.
        $empresas = Empresa::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where(fn ($d) => $d->whereNotNull('cobranca_periodicidade')->whereNotNull('cobranca_valor'))
                    ->orWhereNotNull('caixa_cliente_id')
                    ->orWhereIn('id', PlataformaFatura::pendentes()->select('empresa_id'));
            })
            ->get();

        $this->info("Processando {$empresas->count()} empresa(s) com cobrança — {$hoje->format('d/m/Y')}");

        foreach ($empresas as $empresa) {
            $this->gerarFaturaDoCiclo($empresa, $hoje, $dry);
            $this->avisarESuspender($empresa, $hoje, $dry);
        }

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */
    /*  Geração                                                            */
    /* ------------------------------------------------------------------ */

    private function gerarFaturaDoCiclo(Empresa $empresa, Carbon $hoje, bool $dry): void
    {
        if (! $empresa->temCobrancaDireta() || $empresa->cobranca_geracao !== 'automatica') {
            return; // geração manual: Dennis gera pelo botão quando quiser
        }
        if ($empresa->vinculadaAoCaixa()) {
            return; // a fatura desta empresa nasce no caixa.ia365 — gerar aqui duplicaria
        }

        if ($empresa->cobranca_periodicidade === 'mensal') {
            $competencia = $hoje->format('Y-m');
            $dia = min((int) ($empresa->cobranca_dia_vencimento ?: 10), $hoje->daysInMonth);
            $vencimento = $hoje->copy()->setDay($dia);
            $descricao = 'Mensalidade ERP Comercial — ' . $hoje->translatedFormat('F/Y');
        } else { // anual
            $renovacao = $empresa->cobranca_proxima_renovacao;
            if (! $renovacao || $hoje->lt($renovacao->copy()->subDays(30))) {
                return; // ainda longe da renovação
            }
            $competencia = $renovacao->format('Y');
            $vencimento = $renovacao->copy();
            $descricao = 'Anuidade ERP Comercial — ' . $renovacao->format('Y');
        }

        $jaExiste = PlataformaFatura::where('empresa_id', $empresa->id)
            ->where('competencia', $competencia)
            ->where('status', '!=', 'cancelada')
            ->exists();

        if ($jaExiste) {
            return;
        }

        if ($dry) {
            $this->line("  [dry] geraria fatura {$competencia} de R$ {$empresa->cobranca_valor} p/ {$empresa->razao_social}");

            return;
        }

        $fatura = PlataformaFatura::create([
            'empresa_id'             => $empresa->id,
            'competencia'            => $competencia,
            'descricao'              => $descricao,
            'valor'                  => $empresa->cobranca_valor,
            'vencimento'             => $vencimento,
            'status'                 => 'pendente',
            'gerada_automaticamente' => true,
        ]);

        $this->info("  ✓ fatura {$competencia} gerada p/ {$empresa->razao_social} (vence {$vencimento->format('d/m/Y')})");
        Log::info('[CobrancaPlataforma] fatura gerada', ['fatura_id' => $fatura->id, 'empresa_id' => $empresa->id]);

        FaturasPlataforma::avisar($fatura, 'emitida');
    }

    /* ------------------------------------------------------------------ */
    /*  Avisos + suspensão                                                 */
    /* ------------------------------------------------------------------ */

    private function avisarESuspender(Empresa $empresa, Carbon $hoje, bool $dry): void
    {
        $pendentes = PlataformaFatura::where('empresa_id', $empresa->id)
            ->pendentes()
            ->orderBy('vencimento')
            ->get();

        if ($pendentes->isEmpty()) {
            return;
        }

        foreach ($pendentes as $fatura) {
            $fatura->setRelation('empresa', $empresa);
            $diasParaVencer = (int) $hoje->diffInDays($fatura->vencimento, false);
            $tipo = $diasParaVencer < 0 ? 'atraso' : ($diasParaVencer <= 3 ? 'vence' : null);

            if ($tipo && ! isset(((array) $fatura->avisos)[$tipo])) {
                if ($dry) {
                    $this->line("  [dry] avisaria '{$tipo}' da fatura {$fatura->competencia} p/ {$empresa->razao_social}");
                } else {
                    FaturasPlataforma::avisar($fatura, $tipo);
                }
            }
        }

        // Suspensão: alguma pendente passou da tolerância + bloqueio ligado
        if ($empresa->cobranca_bloqueio_automatico && ! $empresa->estaSuspensa()) {
            $estourada = $pendentes->first(fn ($f) => $f->passouDaTolerancia());

            if ($estourada) {
                if ($dry) {
                    $this->warn("  [dry] SUSPENDERIA {$empresa->razao_social} (fatura {$estourada->competencia} estourou a tolerância)");

                    return;
                }

                $empresa->forceFill(['cobranca_suspensa_em' => now()])->save();

                $this->warn("  ⛔ {$empresa->razao_social} SUSPENSA (fatura {$estourada->competencia} vencida além da tolerância)");
                Log::warning('[CobrancaPlataforma] empresa suspensa', [
                    'empresa_id' => $empresa->id,
                    'fatura_id'  => $estourada->id,
                ]);

                FaturasPlataforma::avisar($estourada, 'suspensa');
            }
        }
    }
}
