<?php

namespace App\Observers;

use App\Jobs\NotificarPlataformaIntegracaoJob;
use App\Models\AgenteIaConfig;
use App\Models\EmpresaGateway;

/**
 * Integração ligada/desligada → aviso ao app.ia365 (08/09/2026).
 *
 * Cobre de uma vez todos os pontos que escrevem em empresa_gateways: os
 * cards Uber/Asaas/PIX Sicredi/Melhor Envio do admin, o callback OAuth do
 * Melhor Envio e o Desconectar. Só reage a `ativo` e `access_token`
 * (mudar pacote padrão ou faixa de CEP não é "integração ligada").
 *
 * ⚠️ `Model::where()->update()` NÃO passa por aqui (query builder) — por isso
 * o Desconectar do Melhor Envio salva pelo model.
 */
class EmpresaGatewayObserver
{
    public function saved(EmpresaGateway $gateway): void
    {
        $criadoLigado = $gateway->wasRecentlyCreated && $gateway->ativo;
        if (! $criadoLigado && ! $gateway->wasChanged(['ativo', 'access_token'])) {
            return;
        }

        $this->avisar($gateway, $gateway->capacidadeAtiva());
    }

    public function deleted(EmpresaGateway $gateway): void
    {
        $this->avisar($gateway, false);
    }

    private function avisar(EmpresaGateway $gateway, bool $ativo): void
    {
        $config = AgenteIaConfig::where('empresa_id', $gateway->empresa_id)->first();
        if (! $config || ! $config->plataformaRegistrada()) {
            return; // empresa sem agente na plataforma: ninguém para avisar
        }

        NotificarPlataformaIntegracaoJob::dispatch($gateway->empresa_id, $gateway->provedor, $ativo);
    }
}
