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

/**
 * NFS-e Nacional — padrão unificado via Portal Nacional (Receita Federal).
 *
 * Substitui o endpoint /v2/nfse (municipal) por /v2/nfse_nacional, que fala
 * com o Portal Nacional da RFB em vez de cada prefeitura. Todas as cidades
 * novas já são obrigadas a usar o padrão nacional; as existentes estão em
 * migração até 2033.
 *
 * Payload difere do modelo municipal:
 *   - Usa "servico" (singular) com código nacional;
 *   - Os tributos IBS/CBS passam a ser explícitos (Reforma Tributária);
 *   - Campo `nnfse_nacional` como referência;
 *
 * A ConfiguracaoFiscal.nfse_padrao decide qual service cada unidade usa.
 */
class NFSeNacionalService
{
    public function __construct(
        private readonly FocusNFeClient $client,
        private readonly ReformaTributariaCalculator $reforma,
    ) {}

    public static function forUnidade(Unidade $unidade): static
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $unidade->empresa_id)
            ->where('unidade_id', $unidade->id)
            ->firstOrFail();

        return new static(
            FocusNFeClient::forUnidade($unidade),
            new ReformaTributariaCalculator($config),
        );
    }

    /**
     * Emite uma NFS-e pelo padrão nacional.
     *
     * @param  array<string, mixed>  $dadosServico
     */
    public function emitir(array $dadosServico, ConfiguracaoFiscal $config, ?Cliente $cliente = null): NotaFiscal
    {
        $dadosServico = $this->normalizarDados($dadosServico);
        $this->validarObrigatorios($dadosServico);

        $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);
        $ref = 'nfse-nac-' . ($dadosServico['venda_id'] ?? $unidade->id) . '-' . time();
        $payload = $this->buildPayload($dadosServico, $config, $cliente);

        try {
            Log::info('NFSeNacional: emitindo', [
                'ref' => $ref,
                'unidade_id' => $unidade->id,
                'valor' => $dadosServico['valor_servicos'] ?? 0,
            ]);

            $response = $this->client->post("/v2/nfse_nacional?ref={$ref}", $payload);
            $data = $response->json() ?? [];

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

                $statusFocus = $data['status'] ?? '';

                if ($response->status() === 202 || in_array($statusFocus, ['processando_autorizacao', 'processando'], true)) {
                    $nota->status = StatusNotaFiscal::Pendente;
                    $nota->focus_status = $statusFocus ?: 'processando_autorizacao';
                } elseif ($response->successful() && $statusFocus === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->focus_status = 'autorizado';
                    $nota->numero = $data['numero'] ?? null;
                    $nota->chave_acesso = $data['chave_acesso'] ?? $data['codigo_verificacao'] ?? null;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? null;
                    $nota->pdf_url = $data['caminho_pdf_nota_fiscal'] ?? $data['url'] ?? null;
                    $nota->emitida_em = now();
                } else {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_status = $statusFocus ?: 'erro';
                    $mensagem = $data['mensagem'] ?? $data['erros'] ?? 'Erro ao emitir NFS-e Nacional.';
                    $nota->focus_mensagem = is_array($mensagem) ? json_encode($mensagem) : $mensagem;
                }

                $nota->save();
                return $nota;
            });
        } catch (\Throwable $e) {
            Log::error('NFSeNacional: falha na emissão', [
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

    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        try {
            $response = $this->client->get("/v2/nfse_nacional/{$nota->focus_ref}");
            $data = $response->json();

            if ($response->successful() && $data) {
                $nota->focus_status = $data['status'] ?? $nota->focus_status;

                if (($data['status'] ?? '') === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->numero = $data['numero'] ?? $nota->numero;
                    $nota->chave_acesso = $data['chave_acesso'] ?? $data['codigo_verificacao'] ?? $nota->chave_acesso;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? $nota->xml_url;
                    $nota->pdf_url = $data['caminho_pdf_nota_fiscal'] ?? $data['url'] ?? $nota->pdf_url;
                    $nota->emitida_em = $nota->emitida_em ?? now();
                } elseif (in_array($data['status'] ?? '', ['cancelado', 'cancelada'], true)) {
                    $nota->status = StatusNotaFiscal::Cancelada;
                } elseif (($data['status'] ?? '') === 'erro_autorizacao') {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $msg = $data['mensagem'] ?? $data['erros'] ?? null;
                    $nota->focus_mensagem = is_array($msg) ? json_encode($msg) : $msg;
                }

                $nota->save();
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFSeNacional: erro ao consultar', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);
            return $nota;
        }
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        try {
            $response = $this->client->delete("/v2/nfse_nacional/{$nota->focus_ref}", [
                'justificativa' => $justificativa,
            ]);
            $data = $response->json();

            if ($response->successful()) {
                $nota->status = StatusNotaFiscal::Cancelada;
                $nota->focus_status = $data['status'] ?? 'cancelado';
                $nota->cancelamento_motivo = $justificativa;
                $nota->cancelada_em = now();
                $nota->save();
            } else {
                $nota->focus_mensagem = is_array($data['mensagem'] ?? null)
                    ? json_encode($data['mensagem'])
                    : ($data['mensagem'] ?? 'Erro ao cancelar.');
                $nota->save();
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFSeNacional: erro ao cancelar', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);
            return $nota;
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function normalizarDados(array $dados): array
    {
        if (! isset($dados['discriminacao']) && isset($dados['descricao'])) {
            $dados['discriminacao'] = $dados['descricao'];
        }
        if (! isset($dados['valor_servicos']) && isset($dados['valor_servico'])) {
            $dados['valor_servicos'] = $dados['valor_servico'];
        }
        if (isset($dados['discriminacao'])) {
            $dados['discriminacao'] = mb_substr(
                trim(preg_replace('/\s+/', ' ', (string) $dados['discriminacao'])),
                0,
                2000,
            );
        }
        return $dados;
    }

    /**
     * @throws \App\Exceptions\NotaFiscalEmissaoException
     */
    private function validarObrigatorios(array $dados): void
    {
        $discriminacao = trim((string) ($dados['discriminacao'] ?? ''));
        if (mb_strlen($discriminacao) < 3) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Informe a descrição do serviço (discriminação). É exigida pela Receita e aparece na NFS-e.'
            );
        }

        if ((float) ($dados['valor_servicos'] ?? 0) <= 0) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Valor do serviço precisa ser maior que zero.'
            );
        }

        if (empty($dados['codigo_servico_nacional'] ?? $dados['item_lista_servico'] ?? null)) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Informe o código do serviço no padrão nacional (cServ) ou o item da lista LC 116.'
            );
        }
    }

    private function buildPayload(array $dados, ConfiguracaoFiscal $config, ?Cliente $cliente): array
    {
        $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);
        $empresa = $unidade->empresa;

        $cnpjPrestador = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);
        $valor = (float) ($dados['valor_servicos'] ?? 0);

        $payload = [
            'prestador' => [
                'cnpj' => $cnpjPrestador,
                'razao_social' => $empresa->razao_social,
                'inscricao_municipal' => preg_replace('/\D/', '', $unidade->im ?: ($empresa->im ?? '')),
                'optante_simples_nacional' => $empresa->regime_tributario?->value === 'simples_nacional',
            ],
            'servico' => [
                'data_competencia' => $dados['data_competencia'] ?? now()->format('Y-m-d'),
                'codigo_servico_nacional' => $dados['codigo_servico_nacional'] ?? null,
                'item_lista_servico' => $dados['item_lista_servico'] ?? null,
                'codigo_cnae' => $dados['codigo_cnae'] ?? null,
                'discriminacao' => $dados['discriminacao'] ?? '',
                'valor_servicos' => number_format($valor, 2, '.', ''),
                'iss_retido' => (bool) ($dados['iss_retido'] ?? false),
                'aliquota_iss' => isset($dados['aliquota_iss'])
                    ? number_format((float) $dados['aliquota_iss'], 4, '.', '')
                    : null,
                'natureza_operacao' => $dados['natureza_operacao'] ?? 'Tributação no município do prestador',
            ],
        ];

        // Tributos da Reforma Tributária (quando flags ligadas em config)
        $rt = $this->reforma->blocoPayload($valor, $dados);
        if (! empty($rt)) {
            $payload['servico']['tributos_reforma'] = $rt;
        }

        // Tomador
        if ($cliente) {
            $cpfCnpj = preg_replace('/\D/', '', $cliente->cpf_cnpj ?? '');
            $payload['tomador'] = [
                'razao_social' => $cliente->nome_razao_social,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone ? preg_replace('/\D/', '', $cliente->telefone) : null,
                strlen($cpfCnpj) === 11 ? 'cpf' : 'cnpj' => $cpfCnpj,
                'endereco' => array_filter([
                    'logradouro' => $cliente->logradouro,
                    'numero' => $cliente->numero ?? 'S/N',
                    'complemento' => $cliente->complemento,
                    'bairro' => $cliente->bairro,
                    'municipio' => $cliente->cidade,
                    'uf' => $cliente->uf,
                    'cep' => $cliente->cep ? preg_replace('/\D/', '', $cliente->cep) : null,
                ]),
            ];
            $payload['tomador'] = array_filter($payload['tomador'], fn ($v) => $v !== null && $v !== '');
        }

        return $payload;
    }
}
