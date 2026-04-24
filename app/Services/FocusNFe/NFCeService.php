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

class NFCeService
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
     * Emite uma NFC-e a partir de uma Venda (processamento síncrono).
     */
    public function emitir(Venda $venda, ConfiguracaoFiscal $config): NotaFiscal
    {
        $venda->loadMissing(['itens.produto', 'cliente', 'unidade.empresa']);

        $ref = 'nfce-' . $venda->id . '-' . time();
        $payload = $this->buildPayload($venda, $config);

        try {
            Log::info('NFCe: Emitindo NFC-e', [
                'venda_id' => $venda->id,
                'ref' => $ref,
                'unidade_id' => $venda->unidade_id,
            ]);

            $response = $this->client->post("/v2/nfce?ref={$ref}", $payload);
            $data = $response->json();

            return DB::transaction(function () use ($venda, $config, $ref, $data, $response) {
                $nota = new NotaFiscal();
                $nota->empresa_id = $venda->empresa_id;
                $nota->unidade_id = $venda->unidade_id;
                $nota->tipo = TipoNotaFiscal::NFCe;
                $nota->venda_id = $venda->id;
                $nota->cliente_id = $venda->cliente_id;
                $nota->focus_ref = $ref;
                $nota->serie = $config->serie_nfce;
                $nota->natureza_operacao = 'Venda ao Consumidor';
                $nota->valor_total = $venda->total;
                $nota->ambiente = $config->ambiente ?? 'homologacao';

                if ($response->successful() && isset($data['status']) && $data['status'] === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->focus_status = $data['status'];
                    $nota->chave_acesso = $data['chave_nfe'] ?? null;
                    $nota->numero = $data['numero'] ?? null;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? null;
                    $nota->danfe_url = $data['caminho_danfe'] ?? null;
                    $nota->emitida_em = now();

                    Log::info('NFCe: NFC-e autorizada', [
                        'ref' => $ref,
                        'chave' => $nota->chave_acesso,
                        'numero' => $nota->numero,
                    ]);
                } else {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_status = $data['status'] ?? 'erro';
                    $nota->focus_mensagem = $data['mensagem'] ?? $data['status_sefaz'] ?? 'Erro ao emitir NFC-e';

                    Log::warning('NFCe: NFC-e rejeitada', [
                        'ref' => $ref,
                        'status' => $nota->focus_status,
                        'mensagem' => $nota->focus_mensagem,
                    ]);
                }

                $nota->save();

                return $nota;
            });
        } catch (\Throwable $e) {
            Log::error('NFCe: Erro ao emitir NFC-e', [
                'venda_id' => $venda->id,
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            $nota = new NotaFiscal();
            $nota->empresa_id = $venda->empresa_id;
            $nota->unidade_id = $venda->unidade_id;
            $nota->tipo = TipoNotaFiscal::NFCe;
            $nota->venda_id = $venda->id;
            $nota->cliente_id = $venda->cliente_id;
            $nota->focus_ref = $ref;
            $nota->serie = $config->serie_nfce;
            $nota->natureza_operacao = 'Venda ao Consumidor';
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
     * Consulta o status de uma NFC-e na API Focus NFe.
     */
    public function consultar(NotaFiscal $nota): NotaFiscal
    {
        try {
            Log::info('NFCe: Consultando NFC-e', ['ref' => $nota->focus_ref]);

            $response = $this->client->get("/v2/nfce/{$nota->focus_ref}");
            $data = $response->json();

            if ($response->successful() && $data) {
                $nota->focus_status = $data['status'] ?? $nota->focus_status;

                if (($data['status'] ?? '') === 'autorizado') {
                    $nota->status = StatusNotaFiscal::Autorizada;
                    $nota->chave_acesso = $data['chave_nfe'] ?? $nota->chave_acesso;
                    $nota->numero = $data['numero'] ?? $nota->numero;
                    $nota->xml_url = $data['caminho_xml_nota_fiscal'] ?? $nota->xml_url;
                    $nota->danfe_url = $data['caminho_danfe'] ?? $nota->danfe_url;
                    $nota->emitida_em = $nota->emitida_em ?? now();
                } elseif (in_array($data['status'] ?? '', ['cancelado', 'cancelada'])) {
                    $nota->status = StatusNotaFiscal::Cancelada;
                } elseif (($data['status'] ?? '') === 'erro_autorizacao') {
                    $nota->status = StatusNotaFiscal::Rejeitada;
                    $nota->focus_mensagem = $data['mensagem_sefaz'] ?? $data['mensagem'] ?? null;
                }

                $nota->save();
            }

            return $nota;
        } catch (\Throwable $e) {
            Log::error('NFCe: Erro ao consultar NFC-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);

            return $nota;
        }
    }

    /**
     * Cancela uma NFC-e autorizada.
     *
     * @throws \App\Exceptions\NotaFiscalCancelException em caso de erro da SEFAZ/Focus
     */
    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        Log::info('NFCe: Cancelando NFC-e', [
            'ref' => $nota->focus_ref,
            'justificativa' => $justificativa,
        ]);

        try {
            $response = $this->client->delete("/v2/nfce/{$nota->focus_ref}", [
                'justificativa' => $justificativa,
            ]);
        } catch (\Throwable $e) {
            Log::error('NFCe: Erro de comunicação ao cancelar NFC-e', [
                'ref' => $nota->focus_ref,
                'error' => $e->getMessage(),
            ]);
            throw new \App\Exceptions\NotaFiscalCancelException(
                'Não foi possível conectar à SEFAZ para cancelar. Verifique sua conexão e tente novamente.',
                0, $e
            );
        }

        $data = $response->json() ?? [];

        if ($response->successful()) {
            $nota->status = StatusNotaFiscal::Cancelada;
            $nota->focus_status = $data['status'] ?? 'cancelado';
            $nota->cancelamento_motivo = $justificativa;
            $nota->cancelamento_protocolo = $data['protocolo'] ?? null;
            $nota->cancelada_em = now();
            $nota->save();

            Log::info('NFCe: NFC-e cancelada com sucesso', ['ref' => $nota->focus_ref]);
            return $nota;
        }

        $rawMsg = $data['mensagem'] ?? $data['erros'][0]['mensagem'] ?? 'Erro desconhecido ao cancelar.';
        $friendly = $this->translateCancelError($rawMsg, $response->status());

        $nota->focus_mensagem = $rawMsg;
        $nota->save();

        Log::warning('NFCe: Erro ao cancelar NFC-e', [
            'ref' => $nota->focus_ref,
            'status' => $response->status(),
            'response' => $data,
        ]);

        throw new \App\Exceptions\NotaFiscalCancelException($friendly);
    }

    /**
     * Traduz mensagens comuns da SEFAZ para texto amigável em pt-BR.
     */
    private function translateCancelError(string $raw, int $httpStatus): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'prazo') || str_contains($lower, 'exced')) {
            return 'O prazo para cancelamento desta NFC-e foi excedido (máximo 30 minutos após autorização). Emita uma nota de devolução.';
        }
        if (str_contains($lower, 'nao autorizada') || str_contains($lower, 'não autorizada') || str_contains($lower, 'rejeit')) {
            return 'Esta nota não está autorizada na SEFAZ e portanto não pode ser cancelada.';
        }
        if (str_contains($lower, 'duplicidade') || str_contains($lower, 'já cancel') || str_contains($lower, 'ja cancel')) {
            return 'Esta NFC-e já foi cancelada anteriormente.';
        }
        if (str_contains($lower, 'certificado')) {
            return 'Certificado digital inválido ou expirado. Atualize o certificado nas configurações fiscais.';
        }
        if ($httpStatus === 401 || str_contains($lower, 'token')) {
            return 'Token da Focus NFe inválido. Verifique as configurações fiscais.';
        }
        if ($httpStatus >= 500) {
            return 'A SEFAZ está instável no momento. Aguarde alguns minutos e tente novamente.';
        }

        return "Não foi possível cancelar: {$raw}";
    }

    /**
     * Inutiliza uma faixa de numeração de NFC-e.
     */
    public function inutilizar(ConfiguracaoFiscal $config, int $serie, int $numInicial, int $numFinal, string $justificativa): array
    {
        try {
            $unidade = Unidade::with('empresa')->findOrFail($config->unidade_id);

            Log::info('NFCe: Inutilizando numeração', [
                'serie' => $serie,
                'num_inicial' => $numInicial,
                'num_final' => $numFinal,
            ]);

            $response = $this->client->post('/v2/nfce/inutilizacao', [
                'cnpj' => preg_replace('/\D/', '', $unidade->cnpj ?: $unidade->empresa->cnpj),
                'serie' => (string) $serie,
                'numero_inicial' => (string) $numInicial,
                'numero_final' => (string) $numFinal,
                'justificativa' => $justificativa,
                'modelo' => '65',
            ]);

            $data = $response->json();

            Log::info('NFCe: Resultado inutilização', ['response' => $data]);

            return [
                'success' => $response->successful(),
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('NFCe: Erro ao inutilizar numeração', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Monta o payload completo da NFC-e a partir dos dados da Venda.
     */
    private function buildPayload(Venda $venda, ConfiguracaoFiscal $config): array
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

            // ICMS - usando CST/CSOSN do produto
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

            $itens[] = $itemPayload;
        }

        // ── Payload principal ────────────────────────────────────────────
        $payload = [
            'natureza_operacao' => 'Venda ao Consumidor',
            'data_emissao' => now()->format('Y-m-d\TH:i:sP'),
            'tipo_documento' => '1', // Saída
            'presenca_comprador' => '1', // Operação presencial
            'consumidor_final' => '1',
            'finalidade_emissao' => '1', // Normal
            'modelo' => '65',

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

        // ── Destinatário (opcional na NFC-e) ─────────────────────────────
        if ($cliente) {
            $cpfCnpj = preg_replace('/\D/', '', $cliente->cpf_cnpj ?? '');

            if (strlen($cpfCnpj) === 11) {
                $payload['cpf_destinatario'] = $cpfCnpj;
            } elseif (strlen($cpfCnpj) === 14) {
                $payload['cnpj_destinatario'] = $cpfCnpj;
            }

            if ($cliente->nome_razao_social) {
                $payload['nome_destinatario'] = $cliente->nome_razao_social;
            }
        }

        // ── Desconto global ──────────────────────────────────────────────
        $descontoGlobal = (float) ($venda->desconto_valor ?? 0);
        if ($descontoGlobal > 0) {
            $payload['valor_desconto'] = number_format($descontoGlobal, 2, '.', '');
        }

        // ── Formas de Pagamento ──────────────────────────────────────────
        $payload['formas_pagamento'] = $this->buildFormasPagamento($venda);

        // ── Informações adicionais ───────────────────────────────────────
        $informacoesAdicionais = [];
        if ($venda->observacoes) {
            $informacoesAdicionais[] = $venda->observacoes;
        }
        if (count($informacoesAdicionais) > 0) {
            $payload['informacoes_adicionais_contribuinte'] = implode(' | ', $informacoesAdicionais);
        }

        return $payload;
    }

    /**
     * Mapeia as formas de pagamento da venda para o formato da API.
     */
    private function buildFormasPagamento(Venda $venda): array
    {
        $formas = [];

        // Se tem detalhes de pagamento, usar os detalhes
        if (is_array($venda->pagamento_detalhes) && count($venda->pagamento_detalhes) > 0) {
            foreach ($venda->pagamento_detalhes as $pagamento) {
                $forma = [
                    'forma_pagamento' => $this->mapFormaPagamento($pagamento['forma'] ?? $venda->forma_pagamento),
                    'valor_pagamento' => number_format((float) ($pagamento['valor'] ?? $venda->total), 2, '.', ''),
                ];

                // Bandeira e CNPJ para cartões
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
            // Forma de pagamento simples
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
            default => '99', // Outros
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
