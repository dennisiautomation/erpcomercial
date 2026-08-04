<?php

namespace App\Console\Commands;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\Notificacao;
use App\Models\PlataformaFatura;
use App\Models\User;
use App\Services\NotificacaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cobrança direta da plataforma (IA365 → empresa-cliente, sem gateway).
 *
 * Roda diariamente:
 *  1. Gera fatura do ciclo para empresas com geração AUTOMÁTICA
 *     (mensal: todo dia 1º para o mês corrente; anual: 30 dias antes da renovação).
 *  2. Avisa o dono no sino quando a fatura vence em ≤3 dias ou está em atraso.
 *  3. SUSPENDE a empresa (bloqueio total) quando fatura pendente passa de
 *     vencimento + tolerância e a empresa tem bloqueio automático ligado.
 *
 * Reativação: automática ao marcar a fatura como paga no admin.
 */
class ProcessarCobrancasPlataformaCommand extends Command
{
    protected $signature = 'plataforma:processar-cobrancas {--dry-run : só mostra o que faria}';

    protected $description = 'Gera faturas da plataforma, avisa vencimentos e aplica bloqueio por inadimplência';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $hoje = Carbon::today();

        $empresas = Empresa::withoutGlobalScopes()
            ->whereNotNull('cobranca_periodicidade')
            ->whereNotNull('cobranca_valor')
            ->get();

        $this->info("Processando {$empresas->count()} empresa(s) com cobrança direta — {$hoje->format('d/m/Y')}");

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
        if ($empresa->cobranca_geracao !== 'automatica') {
            return; // geração manual: Dennis gera pelo botão quando quiser
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

        $this->notificarDono(
            $empresa,
            "Fatura {$competencia} disponível",
            "Valor R$ " . number_format((float) $fatura->valor, 2, ',', '.') . " — vencimento {$vencimento->format('d/m/Y')}.",
            'warning'
        );
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
            $diasParaVencer = (int) $hoje->diffInDays($fatura->vencimento, false);

            if ($diasParaVencer >= 0 && $diasParaVencer <= 3) {
                $this->notificarDono(
                    $empresa,
                    'Fatura vence em breve',
                    ($fatura->descricao ?: "Fatura {$fatura->competencia}")
                        . ' vence em ' . $fatura->vencimento->format('d/m/Y')
                        . ' — R$ ' . number_format((float) $fatura->valor, 2, ',', '.') . '.',
                    'warning'
                );
            } elseif ($diasParaVencer < 0) {
                $this->notificarDono(
                    $empresa,
                    'Fatura em atraso',
                    ($fatura->descricao ?: "Fatura {$fatura->competencia}")
                        . ' venceu em ' . $fatura->vencimento->format('d/m/Y')
                        . ' — R$ ' . number_format((float) $fatura->valor, 2, ',', '.')
                        . '. Regularize para evitar a suspensão do acesso.',
                    'danger'
                );
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

                $this->notificarDono(
                    $empresa,
                    'Acesso suspenso por pendência financeira',
                    'O acesso ao sistema foi suspenso. Regularize o pagamento com a IA365 para reativar.',
                    'danger'
                );
            }
        }
    }

    /**
     * Sino do(s) dono(s) da empresa, com dedup: não repete enquanto houver
     * notificação NÃO LIDA com o mesmo título para o mesmo usuário.
     */
    private function notificarDono(Empresa $empresa, string $titulo, string $mensagem, string $cor): void
    {
        $donos = User::withoutGlobalScopes()
            ->where('empresa_id', $empresa->id)
            ->where('perfil', Perfil::Dono->value)
            ->where('status', 'ativo')
            ->get();

        foreach ($donos as $dono) {
            $jaAvisado = Notificacao::withoutGlobalScopes()
                ->where('user_id', $dono->id)
                ->where('tipo', 'fatura_plataforma')
                ->where('titulo', $titulo)
                ->where('lida', false)
                ->exists();

            if ($jaAvisado) {
                continue;
            }

            NotificacaoService::criar(
                $dono->id,
                $empresa->id,
                'fatura_plataforma',
                $titulo,
                $mensagem,
                null,
                'cash-coin',
                $cor
            );
        }
    }
}
