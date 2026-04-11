<?php

namespace App\Services\FocusNFe;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NFSeService
{
    public function __construct(private FocusNFeClient $client) {}

    /**
     * Cria uma instância configurada para uma unidade específica.
     */
    public static function forUnidade(Unidade $unidade): static
    {
        return new static(FocusNFeClient::forUnidade($unidade));
    }

    /**
     * Emite uma NFS-e (processamento assíncrono).
     *
     * @param array $dadosServico Dados do serviço prestado (valor_servicos, discriminacao, item_lista_servico, etc.)
     * @param ConfiguracaoFiscal $config Configuração fiscal da unidade
     * @param Cliente|null $cliente Tomador do serviço (opcional)
     */
    public function emitir(array $dadosServico, ConfiguracaoFiscal $config, ?Cliente $cliente = null): NotaFiscal
    {
        $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);

        $ref = 'nfse-' . ($dadosServico['venda_id'] ?? $unidade->id) . '-' . time();
        $payload = $this->buildPayload($dadosServico, $config, $cliente);

        try {
            Log::info('NFSe: Emitindo NFS-e', [
                'ref' => $ref,
                'unidade_id' => $unidade->id,
                'valor' => $dadosServico['valor_servicos'] ?? 0,
            ]);

            $response = $this->client->post("/v2/nfse?ref={$ref}", $payload);
            $data = $response->json();

            return DB::transaction(function () use ($unidade, $config, $ref, $data, $response, $dadosServico, $cliente) {
                $nota = new NotaFiscal();
                $nota->empresa_id = $unidade->empresa_id;
                $nota->unidade_id = $unidade->id;
                $nota->tipo = TipoNotaFiscal::NFSe;
                $nota->venda_id = $dadosServico['venda_id'] ?? null;
                $nota->cliente_id = $cliente?->id;
                $nota->focus_ref = $ref;
                $nota->natureza_operacao = $dadosServico['natureza_operacao'] ?? 'Prestação de Serviços';
                $nota->valor_total = $dadosServico['valor_servicos'] ?? 0;
                $nota->ambiente = $config->ambiente ?? 'homologacao';

                if ($response->status() === 202 || ($response->successful() && in_array($data['status'] ?? '', ['processando_autorizacao', 'processando']))) {
                    $nota->status = StatusNotaFiscal::Pendente;
                    $nota->focus_status = $data['status'] ?? 'processando_autorizacao';

                    Log::info('NFSe: NFS-e enviada para processamento', [
                        'ref' => $ref,
                        'status' => $nota->focus_status,
                    ]);
                } elseif ($response->successful() && ($data['status'] ?? '') === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->focus_status = 'autorizado';
                    $nota->numero = $data['numero'] ?? null;
                    $nota->chave_acesso = $data['codigo_verificacao'] ?? null;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? null;
                    $nota->pdf_url = $data['caminho_pdf_nota_fiscal'] ?? $data['url'] ?? null;
                    $nota->emitida_em = now();

                    Log::info('NFSe: NFS-e autorizada diretamente', [
                        'ref' => $ref,
                        'numero' => $nota->numero,
                    ]);
                } else {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_status = $data['status'] ?? 'erro';
                    $nota->focus_mensagem = $data['mensagem'] ?? $data['erros']?? 'Erro ao emitir NFS-e';

                    if (is_array($nota->focus_mensagem)) {
                        $nota->focus_mensagem = json_encode($nota->focus_mensagem);
                    }

                    Log::warning('NFSe: NFS-e rejeitada', [
                        'ref' => $ref,
                        'status' => $nota->focus_status,
                        'mensagem' => $nota->focus_mensagem,
                    ]);
                }

                $nota->save();

                return $nota;
            });
        } catch (\Throwable $e) {
            Log::error('NFSe: Erro ao emitir NFS-e', [
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            $nota = new NotaFiscal();
            $nota->empresa_id = $unidade->empresa_id;
            $nota->unidade_id = $unidade->id;
            $nota->tipo = TipoNotaFiscal::NFSe;
            $nota->venda_id = $dadosServico['venda_id'] ?? null;
            $nota->cliente_id = $cliente?->id;
            $nota->focus_ref = $ref;
            $nota->natureza_operacao = $dadosServico['natureza_operacao'] ?? 'Prestação de Serviços';
            $nota->valor_total = $dadosServico['valor_servicos'] ?? 0;
            $nota->ambiente = $config->ambiente ?? 'homologacao';
            $nota->status = StatusNotaFiscal::Rejeitada;
            $nota->focus_status = 'erro_interno';
            $nota->focus_mensagem = $e->getMessage();
            $nota->save();

            return $nota;
        }
    }

    /**
     * Consulta o status de uma NFS-e na API Focus NFe.
     */
    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        try {
            Log::info('NFSe: Consultando NFS-e', ['ref' => $nota->focus_ref]);

            $response = $this->client->get("/v2/nfse/{$nota->focus_ref}");
            $data = $response->json();

            if ($response->successful() && $data) {
                $nota->focus_status = $data['status'] ?? $nota->focus_status;

                if (($data['status'] ?? '') === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->numero = $data['numero'] ?? $nota->numero;
                    $nota->chave_acesso = $data['codigo_verificacao'] ?? $nota->chave_acesso;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? $nota->xml_url;
                    $nota->pdf_url = $data['caminho_pdf_nota_fiscal'] ?? $data['url'] ?? $nota->pdf_url;
                    $nota->emitida_em = $nota->emitida_em ?? now();

                    Log::info('NFSe: NFS-e autorizada', [
                        'ref' => $nota->focus_ref,
                        'numero' => $nota->numero,
                    ]);
                } elseif (in_array($data['status'] ?? '', ['cancelado', 'cancelada'])) {
                    $nota->status = StatusNotaFiscal::Cancelada;
                } elseif (($data['status'] ?? '') === 'erro_autorizacao') {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_mensagem = $data['mensagem'] ?? $data['erros'] ?? null;
                    if (is_array($nota->focus_mensagem)) {
                        $nota->focus_mensagem = json_encode($nota->focus_mensagem);
                    }
                }

                $nota->save();
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFSe: Erro ao consultar NFS-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return $nota;
        }
    }

    /**
     * Cancela uma NFS-e autorizada.
     */
    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        try {
            Log::info('NFSe: Cancelando NFS-e', [
                'ref' => $nota->focus_ref,
                'justificativa' => $justificativa,
            ]);

            $response = $this->client->delete("/v2/nfse/{$nota->focus_ref}", [
                'justificativa' => $justificativa,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                $nota->status = StatusNotaFiscal::Cancelada;
                $nota->focus_status = $data['status'] ?? 'cancelado';
                $nota->cancelamento_motivo = $justificativa;
                $nota->cancelada_em = now();
                $nota->save();

                Log::info('NFSe: NFS-e cancelada com sucesso', ['ref' => $nota->focus_ref]);
            } else {
                $nota->focus_mensagem = $data['mensagem'] ?? 'Erro ao cancelar NFS-e';
                if (is_array($nota->focus_mensagem)) {
                    $nota->focus_mensagem = json_encode($nota->focus_mensagem);
                }
                $nota->save();

                Log::warning('NFSe: Erro ao cancelar NFS-e', [
                    'ref' => $nota->focus_ref,
                    'response' => $data,
                ]);
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFSe: Erro ao cancelar NFS-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return $nota;
        }
    }

    /**
     * Monta o payload completo da NFS-e.
     */
    private function buildPayload(array $dadosServico, ConfiguracaoFiscal $config, ?Cliente $cliente): array
    {
        $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);
        $empresa = $unidade->empresa;

        $cnpjPrestador = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);
        $imPrestador = preg_replace('/\D/', '', $unidade->im ?: $empresa->im ?? '');

        $payload = [
            // Prestador
            'razao_social_prestador' => $empresa->razao_social,
            'cnpj_prestador' => $cnpjPrestador,
            'inscricao_municipal_prestador' => $imPrestador,

            // Serviço
            'data_emissao' => now()->format('Y-m-d\TH:i:sP'),
            'valor_servicos' => number_format((float) ($dadosServico['valor_servicos'] ?? 0), 2, '.', ''),
            'iss_retido' => $dadosServico['iss_retido'] ?? 'false',
            'item_lista_servico' => $dadosServico['item_lista_servico'] ?? '',
            'discriminacao' => $dadosServico['discriminacao'] ?? '',
            'codigo_tributario_municipio' => $dadosServico['codigo_tributario_municipio'] ?? '',
        ];

        // Alíquota ISS
        if (isset($dadosServico['aliquota_iss'])) {
            $payload['aliquota'] = number_format((float) $dadosServico['aliquota_iss'], 2, '.', '');
        }

        // Valor ISS
        if (isset($dadosServico['valor_iss'])) {
            $payload['valor_iss'] = number_format((float) $dadosServico['valor_iss'], 2, '.', '');
        }

        // Valor deduções
        if (isset($dadosServico['valor_deducoes']) && (float) $dadosServico['valor_deducoes'] > 0) {
            $payload['valor_deducoes'] = number_format((float) $dadosServico['valor_deducoes'], 2, '.', '');
        }

        // Valor PIS
        if (isset($dadosServico['valor_pis']) && (float) $dadosServico['valor_pis'] > 0) {
            $payload['valor_pis'] = number_format((float) $dadosServico['valor_pis'], 2, '.', '');
        }

        // Valor COFINS
        if (isset($dadosServico['valor_cofins']) && (float) $dadosServico['valor_cofins'] > 0) {
            $payload['valor_cofins'] = number_format((float) $dadosServico['valor_cofins'], 2, '.', '');
        }

        // Valor INSS
        if (isset($dadosServico['valor_inss']) && (float) $dadosServico['valor_inss'] > 0) {
            $payload['valor_inss'] = number_format((float) $dadosServico['valor_inss'], 2, '.', '');
        }

        // Valor IR
        if (isset($dadosServico['valor_ir']) && (float) $dadosServico['valor_ir'] > 0) {
            $payload['valor_ir'] = number_format((float) $dadosServico['valor_ir'], 2, '.', '');
        }

        // Valor CSLL
        if (isset($dadosServico['valor_csll']) && (float) $dadosServico['valor_csll'] > 0) {
            $payload['valor_csll'] = number_format((float) $dadosServico['valor_csll'], 2, '.', '');
        }

        // Natureza da operação / tributação
        if (isset($dadosServico['natureza_operacao'])) {
            $payload['natureza_operacao'] = $dadosServico['natureza_operacao'];
        }

        // Código CNAE
        if (isset($dadosServico['codigo_cnae'])) {
            $payload['codigo_cnae'] = $dadosServico['codigo_cnae'];
        }

        // Regime especial de tributação
        if (isset($dadosServico['regime_especial_tributacao'])) {
            $payload['regime_especial_tributacao'] = $dadosServico['regime_especial_tributacao'];
        }

        // Optante Simples Nacional
        if ($empresa->regime_tributario?->value === 'simples_nacional') {
            $payload['optante_simples_nacional'] = 'true';
        } else {
            $payload['optante_simples_nacional'] = 'false';
        }

        // Incentivador cultural
        $payload['incentivador_cultural'] = $dadosServico['incentivador_cultural'] ?? 'false';

        // ── Tomador (cliente) ────────────────────────────────────────────
        if ($cliente) {
            $cpfCnpj = preg_replace('/\D/', '', $cliente->cpf_cnpj ?? '');

            if (strlen($cpfCnpj) === 11) {
                $payload['cpf_tomador'] = $cpfCnpj;
            } elseif (strlen($cpfCnpj) === 14) {
                $payload['cnpj_tomador'] = $cpfCnpj;
            }

            if ($cliente->nome_razao_social) {
                $payload['razao_social_tomador'] = $cliente->nome_razao_social;
            }

            if ($cliente->email) {
                $payload['email_tomador'] = $cliente->email;
            }

            if ($cliente->telefone) {
                $payload['telefone_tomador'] = preg_replace('/\D/', '', $cliente->telefone);
            }

            // Endereço do tomador
            if ($cliente->logradouro) {
                $payload['logradouro_tomador'] = $cliente->logradouro;
                $payload['numero_tomador'] = $cliente->numero ?? 'S/N';
                $payload['bairro_tomador'] = $cliente->bairro ?? '';
                $payload['municipio_tomador'] = $cliente->cidade ?? '';
                $payload['uf_tomador'] = $cliente->uf ?? '';
                $payload['cep_tomador'] = preg_replace('/\D/', '', $cliente->cep ?? '');
            }

            if ($cliente->complemento) {
                $payload['complemento_tomador'] = $cliente->complemento;
            }

            // Inscrição Municipal do tomador (se PJ)
            if ($cliente->tipo_pessoa === 'juridica' && $cliente->ie) {
                $payload['inscricao_municipal_tomador'] = preg_replace('/\D/', '', $cliente->ie);
            }
        }

        return $payload;
    }
}
