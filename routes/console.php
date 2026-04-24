<?php

use App\Jobs\SincronizarNFesRecebidasJob;
use App\Jobs\SincronizarNFSesRecebidasJob;
use App\Models\ConfiguracaoFiscal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Fiscalmente-ativas: wrapper para reaproveitar dentro dos Schedule::call.
 * Closure (em vez de function global) evita redeclare quando routes
 * reloadeiam em memória entre requests/tests.
 */
$configsFiscalAtivo = static fn () => ConfiguracaoFiscal::withoutGlobalScopes()
    ->where('emissao_fiscal_ativa', true)
    ->where(function ($q) {
        $q->whereNotNull('focus_token')
          ->orWhereNotNull('focus_token_producao')
          ->orWhereNotNull('focus_token_homologacao');
    });

// ──────────────────────────────────────────────────────────────────
// NFes recebidas (destinatário): a cada 4h, uma job por unidade.
// ──────────────────────────────────────────────────────────────────
Schedule::call(function () use ($configsFiscalAtivo) {
    $configsFiscalAtivo()->chunkById(50, function ($configs) {
        foreach ($configs as $config) {
            SincronizarNFesRecebidasJob::dispatch($config->empresa_id, $config->unidade_id);
        }
    });
})->everyFourHours()->name('sincronizar-nfes-recebidas')->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// NFSes recebidas (tomador): a cada 6h. Pesa menos que NFes e
// volume muitas vezes é menor — roda menos frequente.
// ──────────────────────────────────────────────────────────────────
Schedule::call(function () use ($configsFiscalAtivo) {
    $configsFiscalAtivo()
        ->where('emite_nfse', true)
        ->chunkById(50, function ($configs) {
            foreach ($configs as $config) {
                SincronizarNFSesRecebidasJob::dispatch($config->empresa_id, $config->unidade_id);
            }
        });
})->cron('0 */6 * * *')->name('sincronizar-nfses-recebidas')->withoutOverlapping();
