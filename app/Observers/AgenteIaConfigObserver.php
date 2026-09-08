<?php

namespace App\Observers;

use App\Jobs\NotificarPlataformaIntegracaoJob;
use App\Models\AgenteIaConfig;

/**
 * Agente IA ligado/desligado no admin do ERP → aviso ao app.ia365, para o
 * agente de lá refletir (08/09/2026). Só `ativo` interessa; o próprio job
 * grava `plataforma_notificado_em` com saveQuietly, sem passar por aqui.
 */
class AgenteIaConfigObserver
{
    public function saved(AgenteIaConfig $config): void
    {
        if (! $config->wasChanged('ativo') || ! $config->plataformaRegistrada()) {
            return;
        }

        NotificarPlataformaIntegracaoJob::dispatch($config->empresa_id, 'agente', (bool) $config->ativo);
    }
}
