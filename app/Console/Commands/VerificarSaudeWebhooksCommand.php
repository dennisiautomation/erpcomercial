<?php

namespace App\Console\Commands;

use App\Models\ConfiguracaoFiscal;
use App\Models\Notificacao;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Verifica semanalmente se os webhooks cadastrados na Focus continuam ativos.
 * Se houver discrepância entre o estado local (focus_webhook_ids) e o estado
 * remoto, recadastra os faltantes e notifica o dono.
 */
class VerificarSaudeWebhooksCommand extends Command
{
    protected $signature = 'fiscal:saude-webhooks';
    protected $description = 'Verifica integridade dos webhooks Focus NFe e recadastra os ausentes';

    public function handle(): int
    {
        if (! FocusNFeClient::masterDisponivel()) {
            $this->warn('Master token Focus não configurado — pulando');
            return self::SUCCESS;
        }

        $service = FocusEmpresaService::make();
        $configs = ConfiguracaoFiscal::withoutGlobalScopes()
            ->whereNotNull('focus_empresa_id')
            ->get();

        $this->info("Verificando {$configs->count()} configuração(ões)…");
        $resincronizados = 0; $ok = 0;

        foreach ($configs as $config) {
            $unidade = $config->unidade()->withoutGlobalScopes()->first();
            $empresa = $config->empresa()->withoutGlobalScopes()->first();
            if (! $unidade || ! $empresa) continue;

            $cnpj = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);
            if (! $cnpj) continue;

            try {
                $remotos = $service->listarWebhooks($cnpj);
                $remotosIds = collect($remotos)->pluck('id')->filter()->values()->all();
                $locaisIds = array_values($config->focus_webhook_ids ?? []);

                $faltando = array_diff($locaisIds, $remotosIds);
                $extras   = array_diff($remotosIds, $locaisIds);

                if ($faltando || $extras) {
                    $this->warn("  ⚠ {$unidade->nome}: " . count($faltando) . " ausente(s) + " . count($extras) . " extra(s) → resync");
                    $service->sincronizarWebhooks($config);
                    $config->webhooks_sincronizados_em = now();
                    $config->save();
                    $this->notificar($empresa, $unidade, $faltando, $extras);
                    $resincronizados++;
                } else {
                    $ok++;
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$unidade->nome}: " . $e->getMessage());
                Log::error('[SaudeWebhooks] falha', [
                    'unidade_id' => $unidade->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        $this->info("✓ {$ok} OK · ↻ {$resincronizados} resync");
        return self::SUCCESS;
    }

    private function notificar($empresa, $unidade, array $faltando, array $extras): void
    {
        $dono = $empresa->users()->where('perfil', 'dono')->first();
        if (! $dono) return;

        Notificacao::create([
            'user_id' => $dono->id,
            'empresa_id' => $empresa->id,
            'tipo' => 'fiscal',
            'titulo' => "Webhooks Focus NFe ressincronizados — {$unidade->nome}",
            'mensagem' => count($faltando) . ' faltando, ' . count($extras) . ' extras — agora alinhados.',
            'url' => route('admin.empresas.saude-focus', $empresa->id, false),
        ]);
    }
}
