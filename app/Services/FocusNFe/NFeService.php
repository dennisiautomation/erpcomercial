<?php

namespace App\Services\FocusNFe;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NFeService
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
     * Emite uma NF-e a partir de uma Venda (processamento assíncrono).
     * A API retorna 202 (processando) — a nota fica como pendente até consulta posterior.
     */
    public function emitir(Venda $venda, ConfiguracaoFiscal $config, array $dadosAdicionais = []): NotaFiscal
    {
        $venda->loadMissing(['itens.produto', 'cliente', 'unidade.empresa']);

        $ref = 'nfe-' . $venda->id . '-' . time();
        $payload = $this->buildPayload($venda, $config, $dadosAdicionais);

        try {
            Log::info('NFe: Emitindo NF-e', [
                'venda_id' => $venda->id,
                'ref' => $ref,
                'unidade_id' => $venda->unidade_id,
            ]);

            $response = $this->client->post("/v2/nfe?ref={$ref}", $payload);
            $data = $response->json();

            return DB::transaction(function () use ($venda, $config, $ref, $data, $response) {
                $nota = new NotaFiscal();
                $nota->empresa_id = $venda->empresa_id;
                $nota->unidade_id = $venda->unidade_id;
                $nota->tipo = TipoNotaFiscal::NFe;
                $nota->venda_id = $venda->id;
                $nota->cliente_id = $venda->cliente_id;
                $nota->focus_ref = $ref;
                $nota->serie = $config->serie_nfe;
                $nota->natureza_operacao = 'Venda de Mercadoria';
                $nota->valor_total = $venda->total;
                $nota->ambiente = $config->ambiente ?? 'homologacao';

                if ($response->status() === 202 || ($response->successful() && ($data['status'] ?? '') === 'processando_autorizacao')) {
                    // NF-e é assíncrona — fica pendente até polling
                    $nota->status = StatusNotaFiscal::Pendente;
                    $nota->focus_status = $data['status'] ?? 'processando_autorizacao';

                    Log::info('NFe: NF-e enviada para processamento', [
                        'ref' => $ref,
                        'status' => $nota->focus_status,
                    ]);
                } elseif ($response->successful() && ($data['status'] ?? '') === 'autorizado') {
                    // Em alguns casos pode já retornar autorizado
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->focus_status = 'autorizado';
                    $nota->chave_acesso = $data['chave_nfe'] ?? null;
                    $nota->numero = $data['numero'] ?? null;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? null;
                    $nota->danfe_url = $data['caminho_danfe'] ?? null;
                    $nota->emitida_em = now();

                    Log::info('NFe: NF-e autorizada diretamente', [
                        'ref' => $ref,
                        'chave' => $nota->chave_acesso,
                    ]);
                } else {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_status = $data['status'] ?? 'erro';
                    $nota->focus_mensagem = $data['mensagem'] ?? $data['status_sefaz'] ?? 'Erro ao emitir NF-e';

                    Log::warning('NFe: NF-e rejeitada', [
                        'ref' => $ref,
                        'status' => $nota->focus_status,
                        'mensagem' => $nota->focus_mensagem,
                    ]);
                }

                $nota->save();

                return $nota;
            });
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao emitir NF-e', [
                'venda_id' => $venda->id,
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            $nota = new NotaFiscal();
            $nota->empresa_id = $venda->empresa_id;
            $nota->unidade_id = $venda->unidade_id;
            $nota->tipo = TipoNotaFiscal::NFe;
            $nota->venda_id = $venda->id;
            $nota->cliente_id = $venda->cliente_id;
            $nota->focus_ref = $ref;
            $nota->serie = $config->serie_nfe;
            $nota->natureza_operacao = 'Venda de Mercadoria';
            $nota->valor_total = $venda->total;
            $nota->ambiente = $config->ambiente ?? 'homologacao';
            $nota->status = StatusNotaFiscal::Rejeitada;
            $nota->focus_status = 'erro_interno';
            $nota->focus_mensagem = $e->getMessage();
            $nota->save();

            return $nota;
        }
    }

    /**
     * Consulta o status de uma NF-e na API Focus NFe (polling para resultado assíncrono).
     */
    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        try {
            Log::info('NFe: Consultando NF-e', ['ref' => $nota->focus_ref]);

            $response = $this->client->get("/v2/nfe/{$nota->focus_ref}", ['completa' => '1']);
            $data = $response->json();

            if ($response->successful() && $data) {
                $nota->focus_status = $data['status'] ?? $nota->focus_status;

                if (($data['status'] ?? '') === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->chave_acesso = $data['chave_nfe'] ?? $nota->chave_acesso;
                    $nota->numero = $data['numero'] ?? $nota->numero;
                    $nota->serie = $data['serie'] ?? $nota->serie;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? $nota->xml_url;
                    $nota->danfe_url = $data['caminho_danfe'] ?? $nota->danfe_url;
                    $nota->emitida_em = $nota->emitida_em ?? now();

                    Log::info('NFe: NF-e autorizada', [
                        'ref' => $nota->focus_ref,
                        'chave' => $nota->chave_acesso,
                        'numero' => $nota->numero,
                    ]);
                } elseif (in_array($data['status'] ?? '', ['cancelado', 'cancelada'])) {
                    $nota->status = StatusNotaFiscal::Cancelada;
                } elseif (($data['status'] ?? '') === 'erro_autorizacao') {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_mensagem = $data['mensagem_sefaz'] ?? $data['mensagem'] ?? null;
                }
                // Se ainda está processando, mantém status pendente

                $nota->save();
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao consultar NF-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return $nota;
        }
    }

    /**
     * Cancela uma NF-e autorizada.
     */
    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        try {
            Log::info('NFe: Cancelando NF-e', [
                'ref' => $nota->focus_ref,
                'justificativa' => $justificativa,
            ]);

            $response = $this->client->delete("/v2/nfe/{$nota->focus_ref}", [
                'justificativa' => $justificativa,
            ]);

            $data = $response->json();

            if ($response->successful()) {
                $nota->status = StatusNotaFiscal::Cancelada;
                $nota->focus_status = $data['status'] ?? 'cancelado';
                $nota->cancelamento_motivo = $justificativa;
                $nota->cancelamento_protocolo = $data['protocolo'] ?? null;
                $nota->cancelada_em = now();
                $nota->save();

                Log::info('NFe: NF-e cancelada com sucesso', ['ref' => $nota->focus_ref]);
            } else {
                $nota->focus_mensagem = $data['mensagem'] ?? 'Erro ao cancelar NF-e';
                $nota->save();

                Log::warning('NFe: Erro ao cancelar NF-e', [
                    'ref' => $nota->focus_ref,
                    'response' => $data,
                ]);
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao cancelar NF-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return $nota;
        }
    }

    /**
     * Emite uma Carta de Correção para uma NF-e autorizada.
     */
    public function cartaCorrecao(NotaFiscal $nota, string $correcao): array
    {
        try {
            Log::info('NFe: Emitindo Carta de Correção', [
                'ref' => $nota->focus_ref,
                'correcao' => $correcao,
            ]);

            $response = $this->client->post("/v2/nfe/{$nota->focus_ref}/carta_correcao", [
                'correcao' => $correcao,
            ]);

            $data = $response->json();

            Log::info('NFe: Resultado Carta de Correção', [
                'ref' => $nota->focus_ref,
                'response' => $data,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao emitir Carta de Correção', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Inutiliza uma faixa de numeração de NF-e.
     */
    public function inutilizar(ConfiguracaoFiscal $config, int $serie, int $numInicial, int $numFinal, string $justificativa): array
    {
        try {
            $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);

            Log::info('NFe: Inutilizando numeração', [
                'serie' => $serie,
                'num_inicial' => $numInicial,
                'num_final' => $numFinal,
            ]);

            $response = $this->client->post('/v2/nfe/inutilizacao', [
                'cnpj' => preg_replace('/\D/', '', $unidade->cnpj ?: $unidade->empresa->cnpj),
                'serie' => (string) $serie,
                'numero_inicial' => (string) $numInicial,
                'numero_final' => (string) $numFinal,
                'justificativa' => $justificativa,
                'modelo' => '55',
            ]);

            $data = $response->json();

            Log::info('NFe: Resultado inutilização', ['response' => $data]);

            return [
                'success' => $response->successful(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao inutilizar numeração', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reenvia o e-mail da NF-e para os destinatários informados.
     */
    public function reenviarEmail(NotaFiscal $nota, array $emails): array
    {
        try {
            Log::info('NFe: Reenviando e-mail', [
                'ref' => $nota->focus_ref,
                'emails' => $emails,
            ]);

            $response = $this->client->post("/v2/nfe/{$nota->focus_ref}/email", [
                'emails' => $emails,
            ]);

            $data = $response->json();

            return [
                'success' => $response->successful(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('NFe: Erro ao reenviar e-mail', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monta o payload completo da NF-e a partir dos dados da Venda.
     */
    private function buildPayload(Venda $venda, ConfiguracaoFiscal $config, array $dadosAdicionais = []): array
    {
        $unidade = $venda->unidade;
        $empresa = $unidade->empresa;
        $cliente = $venda->cliente;

        $cnpjEmitente = preg_replace('/\D/', '', $unidade->cnpj ?: $empresa->cnpj);

        // ── Itens ────────────────────────────────────────────────────────
        $itens = [];
        $valorTotalProdutos = 0;

        foreach ($venda->itens as $index => $item) {
            $produto = $item->produto;
            $valorUnitario = number_format((float) $item->preco_unitario, 2, '.', '');
            $quantidade = number_format((float) $item->quantidade, 3, '.', '');
            $valorTotal = number_format((float) $item->total, 2, '.', '');
            $valorDesconto = number_format((float) ($item->desconto_valor ?? 0), 2, '.', '');

            $valorTotalProdutos += (float) $item->total;

            $itemPayload = [
                'numero_item' => $index + 1,
                'codigo_produto' => $produto->codigo_interno ?? $produto->codigo_barras ?? (string) $produto->id,
                'descricao' => $item->descricao ?: $produto->descricao,
                'codigo_ncm' => $produto->ncm ?? '00000000',
                'cfop' => $produto->cfop ?? '5102',
                'unidade_comercial' => $produto->unidade_medida ?? 'UN',
                'quantidade_comercial' => $quantidade,
                'valor_unitario_comercial' => $valorUnitario,
                'valor_bruto' => $valorTotal,
                'unidade_tributavel' => $produto->unidade_medida ?? 'UN',
                'quantidade_tributavel' => $quantidade,
                'valor_unitario_tributavel' => $valorUnitario,
                'origem' => $produto->origem ?? '0',
                'inclui_no_total' => '1',
            ];

            if ((float) $valorDesconto > 0) {
                $itemPayload['valor_desconto'] = $valorDesconto;
            }

            if ($produto->codigo_barras) {
                $itemPayload['codigo_barras_comercial'] = $produto->codigo_barras;
                $itemPayload['codigo_barras_tributavel'] = $produto->codigo_barras;
            }

            if ($produto->cest) {
                $itemPayload['cest'] = $produto->cest;
            }

            if ($produto->peso_liquido) {
                $itemPayload['peso_liquido'] = number_format((float) $produto->peso_liquido, 3, '.', '');
            }
            if ($produto->peso_bruto) {
                $itemPayload['peso_bruto'] = number_format((float) $produto->peso_bruto, 3, '.', '');
            }

            // ICMS
            $cstCsosn = $produto->cst_csosn ?? '102';
            if ($empresa->regime_tributario?->value === 'simples_nacional') {
                $itemPayload['icms_situacao_tributaria'] = $cstCsosn;
                $itemPayload['icms_origem'] = $produto->origem ?? '0';
            } else {
                $itemPayload['icms_situacao_tributaria'] = $cstCsosn;
                $itemPayload['icms_origem'] = $produto->origem ?? '0';
                if ((float) ($produto->icms_aliquota ?? 0) > 0) {
                    $itemPayload['icms_aliquota'] = number_format((float) $produto->icms_aliquota, 2, '.', '');
                    $itemPayload['icms_base_calculo'] = $valorTotal;
                    $itemPayload['icms_modalidade_base_calculo'] = '3';
                }
            }

            // PIS
            $itemPayload['pis_situacao_tributaria'] = '99';
            $itemPayload['pis_base_calculo'] = $valorTotal;
            $itemPayload['pis_aliquota_porcentual'] = number_format((float) ($produto->pis_aliquota ?? 0), 2, '.', '');
            $itemPayload['pis_valor'] = number_format((float) $item->total * ((float) ($produto->pis_aliquota ?? 0) / 100), 2, '.', '');

            // COFINS
            $itemPayload['cofins_situacao_tributaria'] = '99';
            $itemPayload['cofins_base_calculo'] = $valorTotal;
            $itemPayload['cofins_aliquota_porcentual'] = number_format((float) ($produto->cofins_aliquota ?? 0), 2, '.', '');
            $itemPayload['cofins_valor'] = number_format((float) $item->total * ((float) ($produto->cofins_aliquota ?? 0) / 100), 2, '.', '');

            // IPI (NF-e pode ter IPI, diferente da NFC-e)
            if ((float) ($produto->ipi_aliquota ?? 0) > 0) {
                $itemPayload['ipi_situacao_tributaria'] = '50'; // Saída tributada
                $itemPayload['ipi_base_calculo'] = $valorTotal;
                $itemPayload['ipi_aliquota'] = number_format((float) $produto->ipi_aliquota, 2, '.', '');
                $itemPayload['ipi_valor'] = number_format((float) $item->total * ((float) $produto->ipi_aliquota / 100), 2, '.', '');
            }

            $itens[] = $itemPayload;
        }

        // ── Payload principal ────────────────────────────────────────────
        $payload = [
            'natureza_operacao' => $dadosAdicionais['natureza_operacao'] ?? 'Venda de Mercadoria',
            'data_emissao' => now()->format('Y-m-d\TH:i:sP'),
            'tipo_documento' => '1', // Saída
            'finalidade_emissao' => $dadosAdicionais['finalidade_emissao'] ?? '1', // 1=Normal
            'consumidor_final' => $dadosAdicionais['consumidor_final'] ?? '0',
            'presenca_comprador' => $dadosAdicionais['presenca_comprador'] ?? '1',
            'modelo' => '55',

            // Emitente
            'cnpj_emitente' => $cnpjEmitente,
            'nome_emitente' => $empresa->razao_social,
            'nome_fantasia_emitente' => $empresa->nome_fantasia ?? $empresa->razao_social,
            'inscricao_estadual_emitente' => preg_replace('/\D/', '', $unidade->ie ?: $empresa->ie ?? ''),
            'logradouro_emitente' => $unidade->logradouro ?: $empresa->logradouro,
            'numero_emitente' => $unidade->numero ?: $empresa->numero,
            'bairro_emitente' => $unidade->bairro ?: $empresa->bairro,
            'municipio_emitente' => $unidade->cidade ?: $empresa->cidade,
            'uf_emitente' => $unidade->uf ?: $empresa->uf,
            'cep_emitente' => preg_replace('/\D/', '', $unidade->cep ?: $empresa->cep ?? ''),
            'regime_tributario_emitente' => $this->mapRegimeTributario($empresa->regime_tributario?->value),

            // Itens
            'items' => $itens,

            // Totais
            'valor_produtos' => number_format($valorTotalProdutos, 2, '.', ''),
            'valor_total' => number_format((float) $venda->total, 2, '.', ''),
        ];

        // Complemento endereço emitente
        $complemento = $unidade->complemento ?: $empresa->complemento;
        if ($complemento) {
            $payload['complemento_emitente'] = $complemento;
        }

        // Telefone emitente
        $telefone = $unidade->telefone ?: $empresa->telefone;
        if ($telefone) {
            $payload['telefone_emitente'] = preg_replace('/\D/', '', $telefone);
        }

        // Inscrição Municipal
        $im = $unidade->im ?: $empresa->im;
        if ($im) {
            $payload['inscricao_municipal_emitente'] = preg_replace('/\D/', '', $im);
        }

        // ── Destinatário (obrigatório na NF-e) ──────────────────────────
        if ($cliente) {
            $cpfCnpj = preg_replace('/\D/', '', $cliente->cpf_cnpj ?? '');

            if (strlen($cpfCnpj) === 11) {
                $payload['cpf_destinatario'] = $cpfCnpj;
            } elseif (strlen($cpfCnpj) === 14) {
                $payload['cnpj_destinatario'] = $cpfCnpj;
            }

            $payload['nome_destinatario'] = $cliente->nome_razao_social;

            if ($cliente->ie) {
                $payload['inscricao_estadual_destinatario'] = preg_replace('/\D/', '', $cliente->ie);
                $payload['indicador_inscricao_estadual_destinatario'] = '1'; // Contribuinte ICMS
            } else {
                $payload['indicador_inscricao_estadual_destinatario'] = '9'; // Não contribuinte
            }

            // Endereço completo do destinatário (obrigatório na NF-e)
            if ($cliente->logradouro) {
                $payload['logradouro_destinatario'] = $cliente->logradouro;
                $payload['numero_destinatario'] = $cliente->numero ?? 'S/N';
                $payload['bairro_destinatario'] = $cliente->bairro ?? '';
                $payload['municipio_destinatario'] = $cliente->cidade ?? '';
                $payload['uf_destinatario'] = $cliente->uf ?? '';
                $payload['cep_destinatario'] = preg_replace('/\D/', '', $cliente->cep ?? '');
            }

            if ($cliente->complemento) {
                $payload['complemento_destinatario'] = $cliente->complemento;
            }

            if ($cliente->telefone) {
                $payload['telefone_destinatario'] = preg_replace('/\D/', '', $cliente->telefone);
            }

            if ($cliente->email) {
                $payload['email_destinatario'] = $cliente->email;
            }
        }

        // ── Desconto global ──────────────────────────────────────────────
        $descontoGlobal = (float) ($venda->desconto_valor ?? 0);
        if ($descontoGlobal > 0) {
            $payload['valor_desconto'] = number_format($descontoGlobal, 2, '.', '');
        }

        // ── Formas de Pagamento ──────────────────────────────────────────
        $payload['formas_pagamento'] = $this->buildFormasPagamento($venda);

        // ── Transporte ───────────────────────────────────────────────────
        $payload['modalidade_frete'] = $dadosAdicionais['modalidade_frete'] ?? '9'; // 9 = Sem frete

        if (isset($dadosAdicionais['transportadora'])) {
            $transp = $dadosAdicionais['transportadora'];
            if (isset($transp['cnpj'])) {
                $payload['cnpj_transportador'] = preg_replace('/\D/', '', $transp['cnpj']);
            }
            if (isset($transp['razao_social'])) {
                $payload['nome_transportador'] = $transp['razao_social'];
            }
            if (isset($transp['ie'])) {
                $payload['inscricao_estadual_transportador'] = preg_replace('/\D/', '', $transp['ie']);
            }
            if (isset($transp['endereco'])) {
                $payload['endereco_transportador'] = $transp['endereco'];
            }
            if (isset($transp['municipio'])) {
                $payload['municipio_transportador'] = $transp['municipio'];
            }
            if (isset($transp['uf'])) {
                $payload['uf_transportador'] = $transp['uf'];
            }
        }

        // Volumes
        if (isset($dadosAdicionais['volumes'])) {
            $payload['volumes'] = $dadosAdicionais['volumes'];
        }

        // ── Informações adicionais ───────────────────────────────────────
        $informacoesAdicionais = [];
        if (isset($dadosAdicionais['informacoes_complementares'])) {
            $informacoesAdicionais[] = $dadosAdicionais['informacoes_complementares'];
        }
        if ($venda->observacoes) {
            $informacoesAdicionais[] = $venda->observacoes;
        }
        if (count($informacoesAdicionais) > 0) {
            $payload['informacoes_adicionais_contribuinte'] = implode(' | ', $informacoesAdicionais);
        }

        if (isset($dadosAdicionais['informacoes_fisco'])) {
            $payload['informacoes_adicionais_fisco'] = $dadosAdicionais['informacoes_fisco'];
        }

        return $payload;
    }

    /**
     * Mapeia as formas de pagamento da venda para o formato da API.
     */
    private function buildFormasPagamento(Venda $venda): array
    {
        $formas = [];

        if (is_array($venda->pagamento_detalhes) && count($venda->pagamento_detalhes) > 0) {
            foreach ($venda->pagamento_detalhes as $pagamento) {
                $forma = [
                    'forma_pagamento' => $this->mapFormaPagamento($pagamento['forma'] ?? $venda->forma_pagamento),
                    'valor_pagamento' => number_format((float) ($pagamento['valor'] ?? $venda->total), 2, '.', ''),
                ];

                if (in_array($pagamento['forma'] ?? '', ['credito', 'debito', 'cartao_credito', 'cartao_debito'])) {
                    $forma['bandeira_operadora'] = $pagamento['bandeira'] ?? '99';
                    if (isset($pagamento['cnpj_credenciadora'])) {
                        $forma['cnpj_credenciadora'] = preg_replace('/\D/', '', $pagamento['cnpj_credenciadora']);
                    }
                    if (isset($pagamento['autorizacao'])) {
                        $forma['numero_autorizacao'] = $pagamento['autorizacao'];
                    }
                }

                $formas[] = $forma;
            }
        } else {
            $formas[] = [
                'forma_pagamento' => $this->mapFormaPagamento($venda->forma_pagamento),
                'valor_pagamento' => number_format((float) $venda->total, 2, '.', ''),
            ];
        }

        return $formas;
    }

    /**
     * Mapeia forma de pagamento interna para código SEFAZ.
     */
    private function mapFormaPagamento(?string $forma): string
    {
        return match ($forma) {
            'dinheiro' => '01',
            'cheque' => '02',
            'cartao_credito', 'credito' => '03',
            'cartao_debito', 'debito' => '04',
            'crediario', 'credito_loja' => '05',
            'vale_alimentacao' => '10',
            'vale_refeicao' => '11',
            'vale_presente' => '12',
            'vale_combustivel' => '13',
            'pix' => '17',
            'transferencia', 'deposito' => '18',
            'boleto' => '15',
            'sem_pagamento' => '90',
            default => '99',
        };
    }

    /**
     * Mapeia regime tributário para código Focus NFe.
     */
    private function mapRegimeTributario(?string $regime): string
    {
        return match ($regime) {
            'simples_nacional' => '1',
            'lucro_presumido' => '3',
            'lucro_real' => '3',
            default => '1',
        };
    }
}
