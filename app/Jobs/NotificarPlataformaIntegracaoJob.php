<?php

namespace App\Jobs;

use App\Models\AgenteIaConfig;
use App\Models\EmpresaGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisa o app.ia365 que uma integração desta empresa mudou (08/09/2026).
 *
 * É o primeiro "push" do ERP para fora. Desenho:
 *  - o aviso é só um GATILHO: leva {empresa_id, provedor, ativo}; a plataforma
 *    NÃO confia nele — re-lê GET /capacidades com o próprio token e sincroniza
 *    o agente a partir do que o ERP responder;
 *  - assinado HMAC-SHA256 com o token_hash do token de integração que
 *    registrou o callback (a plataforma tem o gsn_ em claro e calcula o mesmo
 *    sha256): zero credencial nova; mesmo protocolo do sync-hmac.ts da
 *    plataforma (X-Sync-Timestamp em ms + X-Sync-Signature = hmac(ts.body));
 *  - 5 tentativas com espera crescente; a falha final vira
 *    `plataforma_ultima_falha` (card do Agente IA) e `ultima_falha` do
 *    gateway (card da integração) — nunca erro na tela de quem conectou.
 */
class NotificarPlataformaIntegracaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $empresaId,
        public readonly string $provedor,
        public readonly bool $ativo,
    ) {
    }

    /** @return array<int, int> segundos entre tentativas */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $config = AgenteIaConfig::where('empresa_id', $this->empresaId)->with('plataformaToken')->first();
        if (! $config || ! $config->plataformaRegistrada() || ! $config->plataformaToken) {
            // Sem registro não há para quem avisar — não é erro, é o normal de
            // empresa que ainda não criou o agente na plataforma.
            return;
        }

        $body = json_encode([
            'empresa_id' => $this->empresaId,
            'provedor' => $this->provedor,
            'ativo' => $this->ativo,
            'evento_em' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ts = (string) (int) floor(microtime(true) * 1000);
        $assinatura = hash_hmac('sha256', $ts . '.' . $body, $config->plataformaToken->token_hash);
        $url = rtrim($config->plataforma_url, '/') . '/api/integracao/erp/sync';

        $res = Http::withBody($body, 'application/json')
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Sync-Timestamp' => $ts,
                'X-Sync-Signature' => $assinatura,
                'X-Erp-Empresa' => (string) $this->empresaId,
            ])
            ->timeout(20)
            ->post($url);

        if (! $res->successful()) {
            throw new \RuntimeException("plataforma respondeu HTTP {$res->status()}: " . mb_substr($res->body(), 0, 300));
        }

        // Só estes 2 campos: nada de `ativo` — o observer do config ignora.
        $config->forceFill([
            'plataforma_notificado_em' => now(),
            'plataforma_ultima_falha' => null,
        ])->saveQuietly();

        Log::channel('integracao')->info('Agente IA: plataforma avisada da integração', [
            'empresa_id' => $this->empresaId,
            'provedor' => $this->provedor,
            'ativo' => $this->ativo,
            'resposta' => mb_substr($res->body(), 0, 500),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $msg = mb_substr('Aviso ao app.ia365 falhou (' . $this->provedor . '): ' . $e->getMessage(), 0, 1000);

        AgenteIaConfig::where('empresa_id', $this->empresaId)->update(['plataforma_ultima_falha' => $msg]);

        if ($this->provedor !== 'agente') {
            // Query builder de propósito: não dispara o observer do gateway.
            EmpresaGateway::where('empresa_id', $this->empresaId)
                ->where('provedor', $this->provedor)
                ->update(['ultima_falha' => $msg]);
        }

        Log::channel('integracao')->error('Agente IA: aviso à plataforma esgotou as tentativas', [
            'empresa_id' => $this->empresaId,
            'provedor' => $this->provedor,
            'erro' => $e->getMessage(),
        ]);
    }
}
