<?php

use App\Jobs\SincronizarNFesRecebidasJob;
use App\Models\ConfiguracaoFiscal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ──────────────────────────────────────────────────────────────────
// Sincroniza NFes recebidas a cada 4h, uma vez por unidade com
// fiscal ativo. Cada unidade entra como job separado na fila —
// falha em uma não afeta as outras.
// ──────────────────────────────────────────────────────────────────
Schedule::call(function () {
    ConfiguracaoFiscal::withoutGlobalScopes()
        ->where('emissao_fiscal_ativa', true)
        ->whereNotNull('focus_token')
        ->chunkById(50, function ($configs) {
            foreach ($configs as $config) {
                SincronizarNFesRecebidasJob::dispatch($config->empresa_id, $config->unidade_id);
            }
        });
})->everyFourHours()->name('sincronizar-nfes-recebidas')->withoutOverlapping();
