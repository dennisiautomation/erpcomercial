<?php

namespace App\Observers;

use App\Jobs\NotificarPlataformaIntegracaoJob;
use App\Models\AgenteIaConfig;

/**
 * Agente IA ligado/desligado no admin do ERP → aviso ao app.ia365, para o
 * agente de lá refletir (08/09/2026). Interessam `ativo` e, desde 25/09,
 * `provedor_cartao` (qual cartão o agente usa); o próprio job
 * grava `plataforma_notificado_em` com saveQuietly, sem passar por aqui.
 */
class AgenteIaConfigObserver
{
    public function saved(AgenteIaConfig $config): void
    {
        if (! $config->plataformaRegistrada()) {
            return;
        }

        if ($config->wasChanged('ativo')) {
            NotificarPlataformaIntegracaoJob::dispatch($config->empresa_id, 'agente', (bool) $config->ativo);
        }

        // 25/09/2026: trocar o cartão em uso (Asaas × InfinitePay) muda o
        // treinamento do agente — mesmo aviso, a plataforma re-lê /capacidades.
        if ($config->wasChanged('provedor_cartao')) {
            NotificarPlataformaIntegracaoJob::dispatch($config->empresa_id, 'cartao', true);
        }
    }
}
