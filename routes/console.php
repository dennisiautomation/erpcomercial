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

// ──────────────────────────────────────────────────────────────────
// Backup mensal de XMLs (Focus) — diário às 3h, com retenção 5 anos.
// ──────────────────────────────────────────────────────────────────
Schedule::command('fiscal:backup-xmls')
    ->dailyAt('03:00')
    ->name('backup-xmls-fiscais')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// Saúde dos webhooks Focus — toda segunda às 4h.
// ──────────────────────────────────────────────────────────────────
Schedule::command('fiscal:saude-webhooks')
    ->weeklyOn(1, '04:00')
    ->name('saude-webhooks-focus')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// Alerta certificado A1 vencendo — diariamente às 8h.
// ──────────────────────────────────────────────────────────────────
Schedule::command('fiscal:alertar-certificado')
    ->dailyAt('08:00')
    ->name('alertar-certificado-vencendo')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// Cobrança direta da plataforma (IA365 → clientes) — diário às 6h:
// gera faturas do ciclo, avisa vencimentos e aplica bloqueio.
// ──────────────────────────────────────────────────────────────────
Schedule::command('plataforma:processar-cobrancas')
    ->dailyAt('06:00')
    ->name('processar-cobrancas-plataforma')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// Cópia local dos XMLs por nota — diário às 3h30 (rede de segurança;
// o fluxo normal é o hook saved do model NotaFiscal).
// ──────────────────────────────────────────────────────────────────
Schedule::command('fiscal:baixar-xmls-notas')
    ->dailyAt('03:30')
    ->name('baixar-xmls-notas')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// Melhor Envio (05/09/2026): renova os tokens OAuth das empresas que
// vencem em ≤ 3 dias (validade 30 dias) — diário às 5h.
// ──────────────────────────────────────────────────────────────────
Schedule::command('melhorenvio:renovar-tokens')
    ->dailyAt('05:00')
    ->name('melhor-envio-renovar-tokens')
    ->withoutOverlapping();

// ──────────────────────────────────────────────────────────────────
// PIX do Agente IA — rede de segurança do webhook Sicredi: consulta
// cobranças ATIVAS e confirma pagamentos perdidos; expira vencidas.
// ──────────────────────────────────────────────────────────────────
Schedule::command('agente:pix-sincronizar')
    ->everyFifteenMinutes()
    ->name('agente-pix-sincronizar')
    ->withoutOverlapping();
