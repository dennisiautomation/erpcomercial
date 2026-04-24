<?php

namespace App\Services\FocusNFe;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * APIs de referência da Focus NFe — bases fiscais compartilhadas.
 *
 * Todas usam o token master (são recursos da plataforma, não de uma empresa):
 *   GET  /v2/ncms?descricao={q}             lista NCMs (classificação fiscal)
 *   GET  /v2/cfops                          lista CFOPs (operações fiscais)
 *   GET  /v2/cnaes?descricao={q}            lista CNAEs (atividade econômica)
 *   GET  /v2/municipios/{uf}                lista municípios por UF
 *   GET  /v2/cnpjs/{cnpj}                   consulta CNPJ (Receita Federal)
 *
 * Todas as respostas são cacheadas (ver TTLs nas constantes) — essas bases
 * mudam muito raramente e economizam cota de rate-limit do token master.
 */
class FocusReferenciasService
{
    private const CACHE_PREFIX = 'focus:referencias';
    private const TTL_NCM       = 86400;      // 1 dia
    private const TTL_CFOP      = 604800;     // 7 dias (mudam quase nunca)
    private const TTL_CNAE      = 86400;      // 1 dia
    private const TTL_MUNICIPIO = 2592000;    // 30 dias
    private const TTL_CNPJ      = 3600;       // 1 hora (CNPJ muda mais)

    public function __construct(
        private readonly FocusNFeClient $client,
    ) {}

    public static function make(): static
    {
        return new static(FocusNFeClient::master());
    }

    // ─── NCM ───────────────────────────────────────────────────────────

    /**
     * Busca NCMs por texto livre na descrição.
     * Limita resultados (Focus pagina). A Focus tem ~15 mil NCMs.
     *
     * @return array<int, array{codigo: string, descricao: string}>
     */
    public function ncms(string $busca = '', int $limit = 20): array
    {
        $busca = trim($busca);
        if (mb_strlen($busca) < 2) {
            return [];
        }

        $chave = self::CACHE_PREFIX . ':ncm:' . md5(strtolower($busca)) . ":{$limit}";

        return Cache::remember($chave, self::TTL_NCM, function () use ($busca, $limit) {
            $response = $this->safeGet('/v2/ncms', ['descricao' => $busca]);
            return $this->normalizarLista($response, 'codigo', 'descricao', $limit);
        });
    }

    // ─── CFOP ──────────────────────────────────────────────────────────

    /**
     * Lista CFOPs. Como são ~350 códigos fixos, cacheamos a lista inteira.
     *
     * @return array<int, array{codigo: string, descricao: string}>
     */
    public function cfops(string $busca = ''): array
    {
        $lista = Cache::remember(
            self::CACHE_PREFIX . ':cfops:all',
            self::TTL_CFOP,
            function () {
                $response = $this->safeGet('/v2/cfops');
                return $this->normalizarLista($response, 'codigo', 'descricao', 500);
            }
        );

        if ($busca === '') {
            return $lista;
        }

        $busca = mb_strtolower(trim($busca));
        return array_values(array_filter($lista, function ($item) use ($busca) {
            return str_contains(mb_strtolower($item['codigo']), $busca)
                || str_contains(mb_strtolower($item['descricao']), $busca);
        }));
    }

    // ─── CNAE ──────────────────────────────────────────────────────────

    /**
     * @return array<int, array{codigo: string, descricao: string}>
     */
    public function cnaes(string $busca = '', int $limit = 20): array
    {
        $busca = trim($busca);
        if (mb_strlen($busca) < 2) {
            return [];
        }

        $chave = self::CACHE_PREFIX . ':cnae:' . md5(strtolower($busca)) . ":{$limit}";

        return Cache::remember($chave, self::TTL_CNAE, function () use ($busca, $limit) {
            $response = $this->safeGet('/v2/cnaes', ['descricao' => $busca]);
            return $this->normalizarLista($response, 'codigo', 'descricao', $limit);
        });
    }

    // ─── Municípios ────────────────────────────────────────────────────

    /**
     * Lista municípios de uma UF (cache 30 dias). Retorna código IBGE + nome.
     *
     * @return array<int, array{codigo: string, nome: string, uf: string}>
     */
    public function municipios(string $uf, string $busca = ''): array
    {
        $uf = strtoupper(trim($uf));
        if (strlen($uf) !== 2) {
            return [];
        }

        $lista = Cache::remember(
            self::CACHE_PREFIX . ":municipios:{$uf}",
            self::TTL_MUNICIPIO,
            function () use ($uf) {
                $response = $this->safeGet("/v2/municipios/{$uf}");
                $raw = $response?->json() ?? [];
                return array_map(function ($m) use ($uf) {
                    return [
                        'codigo' => (string) ($m['codigo_ibge'] ?? $m['codigo'] ?? ''),
                        'nome' => $m['nome'] ?? $m['municipio'] ?? '',
                        'uf' => $uf,
                    ];
                }, $raw);
            }
        );

        if ($busca === '') {
            return $lista;
        }

        $buscaNorm = mb_strtolower(trim($busca));
        return array_values(array_filter($lista, function ($item) use ($buscaNorm) {
            return str_contains(mb_strtolower($item['nome']), $buscaNorm);
        }));
    }

    // ─── CNPJ ──────────────────────────────────────────────────────────

    /**
     * Consulta dados cadastrais de um CNPJ na Receita via Focus.
     * Retorna null se CNPJ inválido ou não encontrado.
     *
     * @return array<string, mixed>|null
     */
    public function cnpj(string $cnpj): ?array
    {
        $cnpjLimpo = preg_replace('/\D+/', '', $cnpj);
        if (strlen($cnpjLimpo) !== 14) {
            return null;
        }

        return Cache::remember(
            self::CACHE_PREFIX . ":cnpj:{$cnpjLimpo}",
            self::TTL_CNPJ,
            function () use ($cnpjLimpo) {
                $response = $this->safeGet("/v2/cnpjs/{$cnpjLimpo}");

                if (! $response || ! $response->successful()) {
                    return null;
                }

                return $response->json();
            }
        );
    }

    // ─── Invalidação manual ────────────────────────────────────────────

    /**
     * Força refresh — útil após mudanças raras na base da Focus ou em testes.
     */
    public function invalidarCache(string $tipo = 'all'): void
    {
        $chaves = match ($tipo) {
            'ncm'        => [self::CACHE_PREFIX . ':ncm:*'],
            'cfop'       => [self::CACHE_PREFIX . ':cfops:all'],
            'cnae'       => [self::CACHE_PREFIX . ':cnae:*'],
            'municipios' => [self::CACHE_PREFIX . ':municipios:*'],
            default      => [self::CACHE_PREFIX . ':*'],
        };

        // Laravel's cache não tem wildcard nativo — usamos forget direto
        // para chaves conhecidas. Wildcards requerem Redis/Memcached específico.
        foreach ($chaves as $c) {
            if (! str_contains($c, '*')) {
                Cache::forget($c);
            }
        }
    }

    // ─── Helpers privados ──────────────────────────────────────────────

    /**
     * Wrapper de GET que nunca quebra a UI: em falha retorna null e logga.
     */
    private function safeGet(string $endpoint, array $query = []): ?\Illuminate\Http\Client\Response
    {
        try {
            return $this->client->get($endpoint, $query);
        } catch (\Throwable $e) {
            Log::warning("[FocusReferencias] erro GET {$endpoint}", [
                'query' => $query,
                'erro' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normaliza resposta da Focus para {codigo, descricao} limitado a N itens.
     *
     * @return array<int, array{codigo: string, descricao: string}>
     */
    private function normalizarLista(
        ?\Illuminate\Http\Client\Response $response,
        string $chaveCodigo,
        string $chaveDesc,
        int $limit,
    ): array {
        if (! $response || ! $response->successful()) {
            return [];
        }

        $raw = (array) ($response->json() ?? []);

        $out = array_map(fn ($item) => [
            'codigo' => (string) ($item[$chaveCodigo] ?? ''),
            'descricao' => (string) ($item[$chaveDesc] ?? ''),
        ], $raw);

        return array_slice($out, 0, $limit);
    }
}
