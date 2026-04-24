<?php

namespace App\Services\FocusNFe;

use App\Enums\TipoManifestacao;
use App\Exceptions\ManifestacaoException;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\NFeRecebida;
use App\Models\Unidade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Integra com Focus NFe para:
 *  - Listar NFes em que a empresa é destinatária
 *  - Enviar manifestação (ciência/confirmação/não realizada/desconhecimento)
 *
 * Todos os métodos respeitam multi-tenant: o token vem da ConfiguracaoFiscal
 * da unidade, e os registros criados em nfes_recebidas carregam empresa_id
 * e unidade_id.
 */
class ManifestacaoService
{
    public function __construct(private FocusNFeClient $client) {}

    /**
     * Busca NFes destinadas a esta empresa na Focus e persiste as novas
     * em nfes_recebidas. Retorna a quantidade que foi importada.
     *
     * @throws ManifestacaoException
     */
    public function sincronizar(Empresa $empresa, Unidade $unidade): int
    {
        $cnpj = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);
        if (strlen($cnpj) !== 14) {
            throw new ManifestacaoException(
                'A unidade precisa ter CNPJ válido para consultar NFes recebidas.'
            );
        }

        Log::info('[Manifestacao] sincronizando NFes recebidas', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'cnpj'       => $cnpj,
        ]);

        try {
            $response = $this->client->get('/v2/nfes_recebidas', ['cnpj' => $cnpj]);
        } catch (\Throwable $e) {
            Log::error('[Manifestacao] erro de comunicação', ['error' => $e->getMessage()]);
            throw new ManifestacaoException(
                'Não foi possível conectar ao Focus NFe. Tente sincronizar novamente em instantes.',
                0, $e
            );
        }

        if (! $response->successful()) {
            $msg = $response->json('mensagem') ?? "HTTP {$response->status()}";
            throw new ManifestacaoException(
                $this->translateError($msg, $response->status())
            );
        }

        $itens = (array) ($response->json() ?? []);
        $importadas = 0;

        foreach ($itens as $item) {
            $chave = $item['chave_nfe'] ?? $item['chave'] ?? null;
            if (! $chave || strlen($chave) !== 44) {
                continue;
            }

            $criada = NFeRecebida::withoutGlobalScopes()->firstOrCreate(
                ['chave_acesso' => $chave],
                [
                    'empresa_id'     => $empresa->id,
                    'unidade_id'     => $unidade->id,
                    'cnpj_emitente'  => preg_replace('/\D/', '', $item['cnpj_emitente'] ?? ''),
                    'nome_emitente'  => $item['razao_social_emitente'] ?? $item['nome_emitente'] ?? 'Emitente desconhecido',
                    'numero'         => $item['numero'] ?? null,
                    'serie'          => $item['serie'] ?? null,
                    'valor_total'    => (float) ($item['valor_total'] ?? 0),
                    'data_emissao'   => $this->parseData($item['data_emissao'] ?? null),
                    'xml_url'        => $item['caminho_xml_nota_fiscal'] ?? null,
                    'danfe_url'      => $item['caminho_danfe'] ?? null,
                    'sincronizada_em' => now(),
                ]
            );

            if ($criada->wasRecentlyCreated) {
                $importadas++;
            }
        }

        Log::info('[Manifestacao] sincronização concluída', [
            'empresa_id'      => $empresa->id,
            'total_recebidas' => count($itens),
            'novas'           => $importadas,
        ]);

        return $importadas;
    }

    /**
     * Envia uma manifestação para uma NFe recebida.
     *
     * @throws ManifestacaoException
     */
    public function manifestar(
        NFeRecebida $nfe,
        TipoManifestacao $tipo,
        ?string $justificativa = null,
        ?int $userId = null
    ): NFeRecebida {
        // Desconhecimento e Não Realizada exigem justificativa (Receita)
        if (in_array($tipo, [TipoManifestacao::NaoRealizada, TipoManifestacao::Desconhecimento])
            && mb_strlen(trim((string) $justificativa)) < 15) {
            throw new ManifestacaoException(
                'Manifestação de ' . $tipo->label() . ' exige justificativa com pelo menos 15 caracteres.'
            );
        }

        $payload = ['tipo' => $tipo->focusSlug()];
        if ($justificativa) {
            $payload['justificativa'] = $justificativa;
        }

        Log::info('[Manifestacao] enviando manifestação', [
            'nfe_id' => $nfe->id,
            'chave'  => $nfe->chave_acesso,
            'tipo'   => $tipo->value,
        ]);

        try {
            $response = $this->client->post("/v2/nfes_recebidas/{$nfe->chave_acesso}/manifestacao", $payload);
        } catch (\Throwable $e) {
            Log::error('[Manifestacao] erro de comunicação', [
                'nfe_id' => $nfe->id,
                'error'  => $e->getMessage(),
            ]);
            throw new ManifestacaoException(
                'Não foi possível conectar à SEFAZ para enviar a manifestação. Tente novamente.',
                0, $e
            );
        }

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $rawMsg = $data['mensagem'] ?? $data['erros'][0]['mensagem'] ?? 'Erro desconhecido.';
            throw new ManifestacaoException($this->translateError($rawMsg, $response->status()));
        }

        $nfe->forceFill([
            'tipo_ultima_manifestacao' => $tipo,
            'protocolo_manifestacao'   => $data['numero_protocolo'] ?? $data['protocolo'] ?? null,
            'manifestada_em'           => now(),
            'manifestada_por'          => $userId,
        ])->save();

        return $nfe->fresh();
    }

    private function parseData(?string $raw): ?string
    {
        if (empty($raw)) return null;
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function translateError(string $raw, int $httpStatus): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'prazo') || str_contains($lower, 'excedid')) {
            return 'O prazo para manifestação desta NF-e foi excedido.';
        }
        if (str_contains($lower, 'j\u00e1 manifest') || str_contains($lower, 'ja manifest') || str_contains($lower, 'duplicidade')) {
            return 'Esta NF-e já possui uma manifestação registrada.';
        }
        if (str_contains($lower, 'chave') && str_contains($lower, 'inv')) {
            return 'Chave de acesso inválida — a NF-e pode não estar mais disponível.';
        }
        if (str_contains($lower, 'certificado')) {
            return 'Certificado digital inválido ou expirado. Atualize nas Configurações Fiscais.';
        }
        if ($httpStatus === 401) {
            return 'Token Focus NFe inválido. Verifique as Configurações Fiscais.';
        }
        if ($httpStatus === 403) {
            return 'Sua conta Focus NFe não tem permissão para esta operação.';
        }
        if ($httpStatus >= 500) {
            return 'A SEFAZ está instável. Tente novamente em alguns minutos.';
        }

        return "Não foi possível concluir: {$raw}";
    }
}
