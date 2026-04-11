<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;
use App\Models\Unidade;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FocusNFeClient
{
    private string $token;
    private string $baseUrl;
    private string $ambiente;

    private const URL_HOMOLOGACAO = 'https://homologacao.focusnfe.com.br';
    private const URL_PRODUCAO = 'https://api.focusnfe.com.br';

    public function __construct(string $token, string $ambiente = 'homologacao')
    {
        $this->token = $token;
        $this->ambiente = $ambiente;
        $this->baseUrl = $ambiente === 'producao'
            ? self::URL_PRODUCAO
            : self::URL_HOMOLOGACAO;
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

        if (! $config || ! $config->focus_token) {
            throw new \RuntimeException(
                "Configuração fiscal não encontrada ou token não definido para a unidade {$unidade->nome} (ID: {$unidade->id})"
            );
        }

        return new static($config->focus_token, $config->ambiente ?? 'homologacao');
    }

    /**
     * Cria client a partir do config fiscal direto.
     */
    public static function fromConfig(ConfiguracaoFiscal $config): static
    {
        if (! $config->focus_token) {
            throw new \RuntimeException('Token Focus NFe não configurado.');
        }

        return new static($config->focus_token, $config->ambiente ?? 'homologacao');
    }

    // ─── HTTP Methods ────────────────────────────────────────────────

    public function get(string $endpoint, array $query = []): Response
    {
        return $this->request()->get($this->url($endpoint), $query);
    }

    public function post(string $endpoint, array $data = []): Response
    {
        return $this->request()->post($this->url($endpoint), $data);
    }

    public function put(string $endpoint, array $data = []): Response
    {
        return $this->request()->put($this->url($endpoint), $data);
    }

    public function delete(string $endpoint, array $data = []): Response
    {
        return $this->request()->delete($this->url($endpoint), $data);
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
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    public function getAmbiente(): string
    {
        return $this->ambiente;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
