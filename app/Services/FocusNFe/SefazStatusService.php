<?php

namespace App\Services\FocusNFe;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Consulta o status da SEFAZ da UF informada via Focus NFe.
 *
 * O resultado é cacheado globalmente por 2 minutos: a SEFAZ de SP
 * está fora pra todo mundo ao mesmo tempo, não faz sentido cada
 * tenant bater de novo. A única chamada compartilhada entre todos
 * os clientes na mesma UF.
 */
class SefazStatusService
{
    private const CACHE_TTL_SECONDS = 120;
    private const CACHE_PREFIX = 'focus.sefaz.status.';

    public function __construct(private FocusNFeClient $client) {}

    /**
     * Retorna o status da SEFAZ daquela UF.
     *
     * Formato:
     *   ['situacao' => 'online'|'instavel'|'offline'|'desconhecido',
     *    'mensagem' => string,
     *    'tempo_resposta_ms' => int|null,
     *    'consultado_em' => Carbon]
     */
    public function consultar(string $uf): array
    {
        $uf = strtoupper(trim($uf));
        if (strlen($uf) !== 2) {
            return $this->desconhecido("UF inválida: {$uf}");
        }

        return Cache::remember(self::CACHE_PREFIX . $uf, self::CACHE_TTL_SECONDS, function () use ($uf) {
            return $this->consultarEfetivamente($uf);
        });
    }

    /** Esvazia o cache de uma UF (útil em testes ou força refresh via UI). */
    public function invalidar(string $uf): void
    {
        Cache::forget(self::CACHE_PREFIX . strtoupper(trim($uf)));
    }

    private function consultarEfetivamente(string $uf): array
    {
        try {
            $inicio = microtime(true);
            $response = $this->client->get('/v2/sefaz/status', ['uf' => $uf]);
            $elapsed = (int) ((microtime(true) - $inicio) * 1000);

            $data = $response->json() ?? [];

            if (! $response->successful()) {
                Log::warning('[SefazStatus] Focus retornou erro', [
                    'uf' => $uf,
                    'status' => $response->status(),
                    'body' => $data,
                ]);
                return [
                    'situacao' => 'desconhecido',
                    'mensagem' => 'Não foi possível consultar o status. Tente novamente em alguns minutos.',
                    'tempo_resposta_ms' => $elapsed,
                    'consultado_em' => now(),
                ];
            }

            $autorizador = mb_strtolower($data['autorizador'] ?? '');
            $rawMsg = $data['mensagem'] ?? $data['situacao_autorizador'] ?? 'Sem informação.';
            $situacao = $this->classificar($rawMsg, $elapsed);

            return [
                'situacao' => $situacao,
                'mensagem' => $this->mensagemAmigavel($situacao, $rawMsg, $autorizador),
                'tempo_resposta_ms' => $elapsed,
                'consultado_em' => now(),
            ];
        } catch (\Throwable $e) {
            Log::error('[SefazStatus] Exceção ao consultar', [
                'uf' => $uf,
                'error' => $e->getMessage(),
            ]);
            return $this->desconhecido('Sem resposta do Focus.');
        }
    }

    private function classificar(string $raw, int $elapsedMs): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'offline') || str_contains($lower, 'fora do ar') || str_contains($lower, 'indispon')) {
            return 'offline';
        }
        if (str_contains($lower, 'instav') || str_contains($lower, 'lent') || $elapsedMs > 5000) {
            return 'instavel';
        }
        if (str_contains($lower, 'online') || str_contains($lower, 'operac') || str_contains($lower, 'normal') || str_contains($lower, 'dispon')) {
            return 'online';
        }
        return 'desconhecido';
    }

    private function mensagemAmigavel(string $situacao, string $raw, string $autorizador): string
    {
        $prefixo = $autorizador ? "SEFAZ {$autorizador}" : 'SEFAZ';
        return match ($situacao) {
            'online'   => "{$prefixo} operacional.",
            'instavel' => "{$prefixo} com lentidão. Emissões podem demorar.",
            'offline'  => "{$prefixo} fora do ar. Emissões estão falhando — aguarde o retorno.",
            default    => "{$prefixo}: {$raw}",
        };
    }

    private function desconhecido(string $mensagem): array
    {
        return [
            'situacao' => 'desconhecido',
            'mensagem' => $mensagem,
            'tempo_resposta_ms' => null,
            'consultado_em' => now(),
        ];
    }
}
