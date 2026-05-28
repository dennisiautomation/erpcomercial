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
        $dadosServico = $this->normalizarDadosServico($dadosServico);
        $this->validarDadosObrigatorios($dadosServico);

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
    /**
     * Aceita tanto o vocabulário do controller quanto o da Focus NFe.
     * Traduz descricao→discriminacao, valor_servico→valor_servicos etc.
     * e aplica defaults a partir da ConfiguracaoFiscal quando faz sentido.
     */
    private function normalizarDadosServico(array $dados): array
    {
        // Aliases amigáveis → nomes da Focus
        if (! isset($dados['discriminacao']) && isset($dados['descricao'])) {
            $dados['discriminacao'] = $dados['descricao'];
        }
        if (! isset($dados['valor_servicos']) && isset($dados['valor_servico'])) {
            $dados['valor_servicos'] = $dados['valor_servico'];
        }

        // Limpa espaços e trunca (Focus aceita até 2000 chars)
        if (isset($dados['discriminacao'])) {
            $dados['discriminacao'] = trim(preg_replace('/\s+/', ' ', (string) $dados['discriminacao']));
            $dados['discriminacao'] = mb_substr($dados['discriminacao'], 0, 2000);
        }

        return $dados;
    }

    /**
     * Valida campos obrigatórios antes de gastar uma chamada à Focus.
     *
     * @throws \App\Exceptions\NotaFiscalEmissaoException
     */
    private function validarDadosObrigatorios(array $dados): void
    {
        $discriminacao = trim((string) ($dados['discriminacao'] ?? ''));
        if (mb_strlen($discriminacao) < 3) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Informe a descrição do serviço (discriminação). É exigida pela prefeitura e aparece na NFS-e.'
            );
        }

        $valor = (float) ($dados['valor_servicos'] ?? 0);
        if ($valor <= 0) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Valor do serviço precisa ser maior que zero.'
            );
        }
    }

    /**
     * Monta payload NFS-e no formato aninhado oficial da Focus
     * (prestador, tomador, servico). Mantém retrocompat com aliases para os
     * municípios que ainda esperam payload flat (gateway interno da Focus
     * normaliza, mas o aninhado é o canônico).
     */
    private function buildPayload(array $dadosServico, ConfiguracaoFiscal $config, ?Cliente $cliente): array
    {
        $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);
        $empresa = $unidade->empresa;

        $digits = fn (?string $v) => preg_replace('/\D/', '', $v ?? '');
        $fmt = fn ($v, int $c = 2) => number_format((float) $v, $c, '.', '');

        $cnpjPrestador = $digits($unidade->cnpj ?: $empresa->cnpj);
        $imPrestador = $digits($unidade->im ?: $empresa->im ?? '');
        $codigoMunicipioPrestador = $unidade->codigo_municipio
            ?? $empresa->codigo_municipio
            ?? $dadosServico['codigo_municipio_prestador']
            ?? null;

        $prestador = array_filter([
            'cnpj' => $cnpjPrestador,
            'inscricao_municipal' => $imPrestador ?: null,
            'codigo_municipio' => $codigoMunicipioPrestador,
        ]);

        $tomador = [];
        if ($cliente) {
            $cpfCnpj = $digits($cliente->cpf_cnpj);
            $tomador = array_filter([
                'cpf' => strlen($cpfCnpj) === 11 ? $cpfCnpj : null,
                'cnpj' => strlen($cpfCnpj) === 14 ? $cpfCnpj : null,
                'razao_social' => $cliente->nome_razao_social,
                'email' => $cliente->email,
                'telefone' => $digits($cliente->telefone) ?: null,
                'inscricao_municipal' => $cliente->tipo_pessoa === 'juridica' && $cliente->im
                    ? $digits($cliente->im)
                    : null,
                'endereco' => $cliente->logradouro ? array_filter([
                    'logradouro' => $cliente->logradouro,
                    'numero' => $cliente->numero ?: 'S/N',
                    'complemento' => $cliente->complemento,
                    'bairro' => $cliente->bairro,
                    'codigo_municipio' => $cliente->codigo_municipio
                        ?? $dadosServico['codigo_municipio_tomador']
                        ?? null,
                    'uf' => $cliente->uf,
                    'cep' => $digits($cliente->cep) ?: null,
                ]) : null,
            ]);
        }

        $servico = array_filter([
            'discriminacao' => $dadosServico['discriminacao'] ?? '',
            'codigo_tributario_municipio' => $dadosServico['codigo_tributario_municipio'] ?? null,
            'item_lista_servico' => $dadosServico['item_lista_servico'] ?? $config->nfse_item_lista_servico,
            'valor_servicos' => $fmt($dadosServico['valor_servicos'] ?? 0),
            'aliquota' => isset($dadosServico['aliquota_iss']) ? $fmt($dadosServico['aliquota_iss']) : null,
            'valor_iss' => isset($dadosServico['valor_iss']) ? $fmt($dadosServico['valor_iss']) : null,
            'valor_deducoes' => isset($dadosServico['valor_deducoes']) && (float) $dadosServico['valor_deducoes'] > 0
                ? $fmt($dadosServico['valor_deducoes']) : null,
            'valor_pis' => isset($dadosServico['valor_pis']) && (float) $dadosServico['valor_pis'] > 0
                ? $fmt($dadosServico['valor_pis']) : null,
            'valor_cofins' => isset($dadosServico['valor_cofins']) && (float) $dadosServico['valor_cofins'] > 0
                ? $fmt($dadosServico['valor_cofins']) : null,
            'valor_inss' => isset($dadosServico['valor_inss']) && (float) $dadosServico['valor_inss'] > 0
                ? $fmt($dadosServico['valor_inss']) : null,
            'valor_ir' => isset($dadosServico['valor_ir']) && (float) $dadosServico['valor_ir'] > 0
                ? $fmt($dadosServico['valor_ir']) : null,
            'valor_csll' => isset($dadosServico['valor_csll']) && (float) $dadosServico['valor_csll'] > 0
                ? $fmt($dadosServico['valor_csll']) : null,
            'iss_retido' => $dadosServico['iss_retido'] ?? 'false',
            'codigo_cnae' => $dadosServico['codigo_cnae'] ?? null,
            'codigo_municipio' => $dadosServico['codigo_municipio'] ?? $codigoMunicipioPrestador,
            'natureza_operacao' => $dadosServico['natureza_operacao'] ?? null,
            'regime_especial_tributacao' => $dadosServico['regime_especial_tributacao'] ?? $config->nfse_regime_especial,
            'percentual_total_tributos' => $dadosServico['percentual_total_tributos'] ?? null,
            'fonte_total_tributos' => $dadosServico['fonte_total_tributos'] ?? null,
        ]);

        $payload = [
            'data_emissao' => now()->format('Y-m-d\TH:i:s'),
            'prestador' => $prestador,
            'servico' => $servico,
            'optante_simples_nacional' => $empresa->regime_tributario?->value === 'simples_nacional' ? 'true' : 'false',
            'incentivador_cultural' => $dadosServico['incentivador_cultural'] ?? ($config->nfse_incentivador_cultural ? 'true' : 'false'),
        ];
        if ($tomador) {
            $payload['tomador'] = $tomador;
        }

        return $payload;
    }
}
