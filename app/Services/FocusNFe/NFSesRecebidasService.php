<?php

namespace App\Services\FocusNFe;

use App\Models\Empresa;
use App\Models\NFSeRecebida;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza as NFS-es em que a empresa é TOMADORA (contratou o serviço).
 *
 * Focus NFe expõe /v2/nfses_tomadas?cnpj={cnpj} no padrão municipal e
 * /v2/nfse_nacional/tomadas?cnpj={cnpj} no padrão nacional. O service
 * tenta primeiro o endpoint nacional (mais completo) e cai no municipal.
 *
 * Identidade composta: (cnpj_prestador, codigo_verificacao). Se a Focus
 * não mandar código, usa (cnpj_prestador, numero, serie) como fallback.
 */
class NFSesRecebidasService
{
    public function __construct(private readonly FocusNFeClient $client) {}

    public static function forUnidade(Unidade $unidade): static
    {
        return new static(FocusNFeClient::forUnidade($unidade));
    }

    /**
     * Busca NFS-es tomadas e persiste as novas.
     *
     * @return int quantidade de NFS-es novas importadas
     */
    public function sincronizar(Empresa $empresa, Unidade $unidade): int
    {
        $cnpj = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);
        if (strlen($cnpj) !== 14) {
            throw new \RuntimeException('Unidade precisa de CNPJ válido para consultar NFS-es recebidas.');
        }

        Log::info('[NFSesRecebidas] sincronizando', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'cnpj' => $cnpj,
        ]);

        $itens = $this->buscar($cnpj);
        $novas = 0;

        foreach ($itens as $item) {
            $cnpjPrestador = preg_replace('/\D/', '', $item['cnpj_prestador'] ?? '');
            $codigoVerificacao = $item['codigo_verificacao']
                ?? $item['numero_substituta']
                ?? ($item['numero'] ?? '') . '-' . ($item['serie'] ?? '');

            if (! $cnpjPrestador || ! $codigoVerificacao) {
                continue;
            }

            $criada = NFSeRecebida::withoutGlobalScopes()->firstOrCreate(
                [
                    'cnpj_prestador' => $cnpjPrestador,
                    'codigo_verificacao' => $codigoVerificacao,
                ],
                [
                    'empresa_id' => $empresa->id,
                    'unidade_id' => $unidade->id,
                    'nome_prestador' => $item['razao_social_prestador']
                        ?? $item['nome_prestador']
                        ?? 'Prestador desconhecido',
                    'municipio_prestador' => $item['municipio_prestador'] ?? null,
                    'numero' => $item['numero'] ?? null,
                    'serie' => $item['serie'] ?? null,
                    'discriminacao' => $item['discriminacao'] ?? null,
                    'item_lista_servico' => $item['item_lista_servico'] ?? null,
                    'codigo_servico' => $item['codigo_servico'] ?? $item['codigo_tributario_municipio'] ?? null,
                    'padrao' => $item['_padrao'] ?? 'municipal',
                    'valor_servicos' => (float) ($item['valor_servicos'] ?? 0),
                    'valor_iss' => (float) ($item['valor_iss'] ?? 0),
                    'aliquota_iss' => (float) ($item['aliquota'] ?? $item['aliquota_iss'] ?? 0),
                    'iss_retido' => in_array($item['iss_retido'] ?? null, [true, 'true', '1', 1], true),
                    'data_emissao' => $this->parseData($item['data_emissao'] ?? null),
                    'data_competencia' => $this->parseData($item['data_competencia'] ?? null),
                    'status' => $item['status'] ?? 'autorizada',
                    'xml_url' => $item['caminho_xml_nota_fiscal'] ?? null,
                    'pdf_url' => $item['caminho_pdf_nota_fiscal'] ?? $item['url'] ?? null,
                    'sincronizada_em' => now(),
                ]
            );

            if ($criada->wasRecentlyCreated) {
                $novas++;
            }
        }

        Log::info('[NFSesRecebidas] sincronização concluída', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'total_baixado' => count($itens),
            'novas' => $novas,
        ]);

        return $novas;
    }

    /**
     * Tenta endpoint nacional primeiro; se 404/501, cai para municipal.
     * Ambos retornam arrays; taggeia com `_padrao` pra persistência.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buscar(string $cnpj): array
    {
        // Tenta nacional
        try {
            $response = $this->client->get('/v2/nfse_nacional/tomadas', ['cnpj' => $cnpj]);
            if ($response->successful()) {
                return array_map(
                    fn ($i) => $i + ['_padrao' => 'nacional'],
                    (array) ($response->json() ?? [])
                );
            }
        } catch (\Throwable $e) {
            Log::notice('[NFSesRecebidas] endpoint nacional falhou, caindo para municipal', [
                'erro' => $e->getMessage(),
            ]);
        }

        // Fallback municipal
        $response = $this->client->get('/v2/nfses_tomadas', ['cnpj' => $cnpj]);
        if (! $response->successful()) {
            return [];
        }

        return array_map(
            fn ($i) => $i + ['_padrao' => 'municipal'],
            (array) ($response->json() ?? [])
        );
    }

    private function parseData(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
