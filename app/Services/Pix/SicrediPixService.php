<?php

namespace App\Services\Pix;

use App\Models\EmpresaGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PIX Sicredi (API v3, OAuth2 client_credentials + mTLS) — MULTI-TENANT.
 *
 * Portado do JL-ERP (SicrediPixService, validado em produção com dinheiro
 * real), com a diferença de que aqui as credenciais/certificados vêm do
 * EmpresaGateway (por empresa), não do .env. O certificado e a chave ficam
 * em storage/app/private/{cert_path} (volume app_storage, sobrevive a
 * rebuild). A chave privada NÃO pode ter senha (usar a versão decifrada).
 */
class SicrediPixService
{
    public function __construct(private readonly EmpresaGateway $gateway)
    {
    }

    public static function paraEmpresa(int $empresaId): ?self
    {
        $gateway = EmpresaGateway::sicrediAtivoPara($empresaId);

        return $gateway && $gateway->utilizavel() ? new self($gateway) : null;
    }

    /* ---------------------------------------------------------------- */
    /*  Token                                                            */
    /* ---------------------------------------------------------------- */

    public function getAccessToken(): ?string
    {
        $cacheKey = "sicredi_pix_token_emp{$this->gateway->empresa_id}";

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        try {
            $response = Http::withBasicAuth($this->gateway->client_id, $this->gateway->client_secret)
                ->withOptions($this->tlsOptions())
                ->post("{$this->gateway->base_url}/oauth/token?grant_type=client_credentials"
                    . '&scope=' . rawurlencode('cob.read cob.write pix.read webhook.read webhook.write'));

            if ($response->successful()) {
                $data = $response->json();
                // Sicredi expira em 300s — cache com folga de 30s
                Cache::put($cacheKey, $data['access_token'], max(60, ($data['expires_in'] ?? 300) - 30));

                return $data['access_token'];
            }

            $this->registrarFalha("token HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300));
        } catch (\Throwable $e) {
            $this->registrarFalha('token: ' . $e->getMessage());
        }

        return null;
    }

    /* ---------------------------------------------------------------- */
    /*  Cobrança                                                         */
    /* ---------------------------------------------------------------- */

    /**
     * txid Sicredi: 26-35 chars, minúsculas e números. Prefixo rastreável
     * erp{empresa}p{pedido} (mesma receita do jlerp/jlluna — permite
     * fan-out por prefixo se um dia a chave for compartilhada).
     */
    public function gerarTxid(int $pedidoId): string
    {
        $txid = 'erp' . $this->gateway->empresa_id . 'p' . $pedidoId
            . now()->format('ymdHis') . substr(md5(uniqid((string) $pedidoId, true)), 0, 10);
        $txid = preg_replace('/[^a-z0-9]/', '', strtolower($txid));

        return substr(str_pad($txid, 26, '0'), 0, 35);
    }

    /**
     * PUT /api/v3/cob/{txid} — cobrança imediata.
     *
     * @param array{cpf?: string, cnpj?: string, nome?: string}|null $devedor
     * @return array{success: bool, txid?: string, status?: string, copia_cola?: string, location?: string, error?: string}
     */
    public function criarCobranca(float $valor, string $txid, string $descricao, ?array $devedor = null): array
    {
        $token = $this->getAccessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'Falha ao autenticar no Sicredi (token).'];
        }

        $payload = [
            'calendario' => ['expiracao' => $this->gateway->expiracao_segundos ?: 86400],
            'valor' => [
                'original' => number_format($valor, 2, '.', ''),
                'modalidadeAlteracao' => 0,
            ],
            'chave' => $this->gateway->chave_pix,
            'solicitacaoPagador' => mb_substr($descricao, 0, 140),
        ];

        if ($devedor && (isset($devedor['cpf']) || isset($devedor['cnpj']))) {
            $payload['devedor'] = $devedor;
        }

        try {
            $response = Http::withToken($token)
                ->withOptions($this->tlsOptions())
                ->put("{$this->gateway->base_url}/api/v3/cob/{$txid}", $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::channel('integracao')->info('Sicredi PIX: cobrança criada', [
                    'empresa_id' => $this->gateway->empresa_id,
                    'txid' => $txid,
                    'status' => $data['status'] ?? '',
                ]);

                return [
                    'success' => true,
                    'txid' => $txid,
                    'status' => $data['status'] ?? 'ATIVA',
                    'copia_cola' => $data['pixCopiaECola'] ?? '',
                    'location' => $data['location'] ?? ($data['loc']['location'] ?? ''),
                ];
            }

            $erro = $response->json()['detail'] ?? mb_substr($response->body(), 0, 300);
            $this->registrarFalha("criar cobrança {$txid} HTTP {$response->status()}: {$erro}");

            return ['success' => false, 'error' => $erro];
        } catch (\Throwable $e) {
            $this->registrarFalha("criar cobrança {$txid}: " . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * GET /api/v3/cob/{txid}. Pago = CONCLUIDA **e** array `pix` presente
     * (CONCLUIDA sozinha pode ser cobrança removida — lição do JL).
     *
     * @return array{success: bool, status?: string, pago?: bool, pix?: array<int, mixed>, data?: array<string, mixed>, error?: string}
     */
    public function consultarCobranca(string $txid): array
    {
        $token = $this->getAccessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'Falha ao autenticar no Sicredi (token).'];
        }

        try {
            $response = Http::withToken($token)
                ->withOptions($this->tlsOptions())
                ->get("{$this->gateway->base_url}/api/v3/cob/{$txid}");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['status'] ?? '';
                $pagamentos = is_array($data['pix'] ?? null) ? $data['pix'] : [];

                return [
                    'success' => true,
                    'status' => $status,
                    'pago' => $status === 'CONCLUIDA' && count($pagamentos) > 0,
                    'pix' => $pagamentos,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json()['detail'] ?? mb_substr($response->body(), 0, 300),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ---------------------------------------------------------------- */
    /*  Webhook (registro é POR CHAVE PIX — um só por chave!)            */
    /* ---------------------------------------------------------------- */

    /** PUT /api/v2/webhook/{chave}. O Sicredi entrega os eventos em {url}/pix. */
    public function registrarWebhook(string $url): array
    {
        $token = $this->getAccessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'Falha ao autenticar no Sicredi (token).'];
        }

        try {
            $chave = rawurlencode($this->gateway->chave_pix);
            $response = Http::withToken($token)
                ->withOptions($this->tlsOptions())
                ->put("{$this->gateway->base_url}/api/v2/webhook/{$chave}", ['webhookUrl' => $url]);

            if ($response->successful()) {
                $this->gateway->forceFill(['webhook_registrado_em' => now(), 'ultima_falha' => null])->save();

                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $response->json()['detail'] ?? mb_substr($response->body(), 0, 300),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function consultarWebhook(): array
    {
        $token = $this->getAccessToken();

        if (! $token) {
            return ['success' => false, 'error' => 'Falha ao autenticar no Sicredi (token).'];
        }

        try {
            $chave = rawurlencode($this->gateway->chave_pix);
            $response = Http::withToken($token)
                ->withOptions($this->tlsOptions())
                ->get("{$this->gateway->base_url}/api/v2/webhook/{$chave}");

            return $response->successful()
                ? ['success' => true, 'data' => $response->json()]
                : ['success' => false, 'status_code' => $response->status(),
                    'error' => $response->json()['detail'] ?? mb_substr($response->body(), 0, 300)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ---------------------------------------------------------------- */
    /*  Internos                                                         */
    /* ---------------------------------------------------------------- */

    /** @return array<string, mixed> */
    private function tlsOptions(): array
    {
        return [
            'cert' => storage_path('app/private/' . ltrim($this->gateway->cert_path, '/')),
            'ssl_key' => storage_path('app/private/' . ltrim($this->gateway->key_path, '/')),
            'timeout' => 30,
        ];
    }

    private function registrarFalha(string $mensagem): void
    {
        Log::channel('integracao')->error('Sicredi PIX: ' . $mensagem, [
            'empresa_id' => $this->gateway->empresa_id,
        ]);

        $this->gateway->forceFill(['ultima_falha' => now()->format('d/m H:i') . ' ' . $mensagem])->save();
    }
}
