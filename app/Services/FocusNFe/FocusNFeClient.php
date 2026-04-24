<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;
use App\Models\Unidade;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP da Focus NFe. Suporta três modos de uso:
 *
 *  1. Per-empresa (emitir notas): new FocusNFeClient($tokenEmpresa, $ambiente)
 *     - Usa token específico da empresa para emitir/consultar/cancelar
 *
 *  2. Per-empresa via config: FocusNFeClient::fromConfig($configFiscal)
 *     - Escolhe automaticamente token produção ou homologação conforme $config->ambiente
 *
 *  3. Master (revenda): FocusNFeClient::master()
 *     - Usa FOCUS_MASTER_TOKEN do .env
 *     - Para POST /v2/empresas, GET /v2/hooks, APIs acessórias (NCM/CFOP/CEP/CNAE/municipios/cnpjs)
 *
 * Handling de rate limit (100 req/min por token padrão):
 *  - Lê headers Rate-Limit-Remaining/Reset
 *  - Se 429, lança FocusRateLimitException com retryAfterSeconds
 */
class FocusNFeClient
{
    private string $token;
    private string $baseUrl;
    private string $ambiente;
    private bool $isMaster;

    private const URL_HOMOLOGACAO = 'https://homologacao.focusnfe.com.br';
    private const URL_PRODUCAO = 'https://api.focusnfe.com.br';

    public function __construct(string $token, string $ambiente = 'homologacao', bool $isMaster = false)
    {
        $this->token = $token;
        $this->ambiente = $ambiente;
        $this->isMaster = $isMaster;
        $this->baseUrl = $ambiente === 'producao'
            ? self::URL_PRODUCAO
            : self::URL_HOMOLOGACAO;
    }

    /**
     * Client master para operações de revenda (criação de empresas, APIs acessórias).
     * Usa o Token Principal de Produção da conta Focus NFe da IA365.
     * O master sempre opera em produção (é assim que a Focus expõe essas APIs).
     */
    public static function master(): static
    {
        $token = config('services.focus_nfe.master_token') ?: env('FOCUS_MASTER_TOKEN');

        if (empty($token)) {
            throw new \RuntimeException(
                'Token master Focus NFe não configurado. Defina FOCUS_MASTER_TOKEN no .env.'
            );
        }

        return new static($token, 'producao', isMaster: true);
    }

    /**
     * True se a plataforma tem token master configurado (opera como revenda).
     */
    public static function masterDisponivel(): bool
    {
        return ! empty(config('services.focus_nfe.master_token') ?: env('FOCUS_MASTER_TOKEN'));
    }

    /**
     * Cria um client configurado para uma unidade específica (multi-tenant).
     */
    public static function forUnidade(Unidade $unidade): static
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $unidade->empresa_id)
            ->where('unidade_id', $unidade->id)
            ->first();

        if (! $config) {
            throw new \RuntimeException(
                "Configuração fiscal não encontrada para a unidade {$unidade->nome} (ID: {$unidade->id})"
            );
        }

        return self::fromConfig($config);
    }

    /**
     * Cria client a partir do config fiscal direto.
     * Prefere os tokens por-ambiente (modelo revenda); cai no focus_token legado se necessário.
     */
    public static function fromConfig(ConfiguracaoFiscal $config): static
    {
        $token = $config->tokenFocusAmbienteAtual();

        if (empty($token)) {
            throw new \RuntimeException(
                'Token Focus NFe não configurado para esta unidade (ambiente: ' . ($config->ambiente ?? 'homologacao') . ').'
            );
        }

        return new static($token, $config->ambiente ?? 'homologacao');
    }

    // ─── HTTP Methods ────────────────────────────────────────────────

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->handleResponse(
            $this->request()->get($this->url($endpoint), $query)
        );
    }

    public function post(string $endpoint, array $data = []): Response
    {
        return $this->handleResponse(
            $this->request()->post($this->url($endpoint), $data)
        );
    }

    public function put(string $endpoint, array $data = []): Response
    {
        return $this->handleResponse(
            $this->request()->put($this->url($endpoint), $data)
        );
    }

    public function delete(string $endpoint, array $data = []): Response
    {
        return $this->handleResponse(
            $this->request()->delete($this->url($endpoint), $data)
        );
    }

    /**
     * Upload multipart (usado para certificado .pfx).
     *
     * @param array<int, array{name:string, contents:string|resource, filename?:string}> $parts
     */
    public function postMultipart(string $endpoint, array $parts): Response
    {
        $req = Http::withBasicAuth($this->token, '')
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 1000, throw: false);

        $first = array_shift($parts);
        $req = $req->attach(
            $first['name'],
            $first['contents'],
            $first['filename'] ?? null
        );
        foreach ($parts as $p) {
            $req = $req->attach($p['name'], $p['contents'], $p['filename'] ?? null);
        }

        return $this->handleResponse($req->post($this->url($endpoint)));
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->token, '')
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(2, 1000, throw: false);
    }

    private function url(string $endpoint): string
    {
        // master sempre produção (api.focusnfe.com.br), ignorando self::$baseUrl
        $base = $this->isMaster ? self::URL_PRODUCAO : $this->baseUrl;
        return rtrim($base, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Inspeciona rate-limit e lança exception amigável em HTTP 429.
     */
    private function handleResponse(Response $response): Response
    {
        $remaining = (int) $response->header('Rate-Limit-Remaining', -1);
        $reset = (int) $response->header('Rate-Limit-Reset', 60);

        if ($remaining >= 0 && $remaining < 5) {
            Log::warning('[FocusNFe] rate limit quase esgotado', [
                'remaining' => $remaining,
                'reset' => $reset,
                'is_master' => $this->isMaster,
            ]);
        }

        if ($response->status() === 429) {
            throw new FocusRateLimitException(
                "Focus NFe recusou (rate limit). Aguarde {$reset}s antes de novas requisições.",
                $reset
            );
        }

        return $response;
    }

    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function isMaster(): bool
    {
        return $this->isMaster;
    }
}
