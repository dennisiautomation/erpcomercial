<?php

namespace App\Console\Commands;

use App\Models\EmpresaGateway;
use App\Services\Entrega\MelhorEnvioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Renova os access_tokens do Melhor Envio (validade 30 dias) antes de vencer.
 * Roda todo dia; só toca nos gateways cujo token vence em ≤ 3 dias. A falha
 * fica em `ultima_falha` (o card da aba Integração mostra) e NÃO desativa o
 * gateway — o token() do serviço tenta de novo sob demanda.
 */
class RenovarTokensMelhorEnvioCommand extends Command
{
    protected $signature = 'melhorenvio:renovar-tokens {--forcar : renova todos, mesmo longe do vencimento}';
    protected $description = 'Renova os tokens OAuth do Melhor Envio que estão perto de vencer';

    public function handle(): int
    {
        if (! MelhorEnvioService::appConfigurado()) {
            $this->info('Aplicativo Melhor Envio não configurado em /admin/integracoes — nada a fazer.');

            return self::SUCCESS;
        }

        $gateways = EmpresaGateway::where('provedor', EmpresaGateway::PROVEDOR_MELHOR_ENVIO)
            ->whereNotNull('refresh_token')
            ->get();

        $ok = 0;
        $falhas = 0;
        foreach ($gateways as $gw) {
            $svc = MelhorEnvioService::paraGateway($gw);
            if (! $this->option('forcar') && ! $svc->precisaRenovar()) {
                continue;
            }
            try {
                $svc->renovarToken();
                $ok++;
                $this->line("empresa {$gw->empresa_id}: renovado até {$gw->fresh()->token_expira_em}");
            } catch (\Throwable $e) {
                $falhas++;
                $gw->update(['ultima_falha' => 'Renovação do token: ' . mb_substr($e->getMessage(), 0, 900)]);
                Log::channel('integracao')->error('Melhor Envio: renovação de token falhou', [
                    'empresa_id' => $gw->empresa_id, 'erro' => $e->getMessage(),
                ]);
                $this->error("empresa {$gw->empresa_id}: {$e->getMessage()}");
            }
        }

        $this->info("Renovados: {$ok}; falhas: {$falhas}; verificados: {$gateways->count()}.");

        return self::SUCCESS;
    }
}
