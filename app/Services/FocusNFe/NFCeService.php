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
                    $nota->qrcode_url = $data['qrcode_url'] ?? null;
                    $nota->url_consulta = $data['url_consulta_nf'] ?? null;
                    $nota->protocolo = $data['protocolo'] ?? null;
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
                    $nota->qrcode_url = $data['qrcode_url'] ?? $nota->qrcode_url;
                    $nota->url_consulta = $data['url_consulta_nf'] ?? $nota->url_consulta;
                    $nota->protocolo = $data['protocolo'] ?? $nota->protocolo;
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

        // HTTP 200 NÃO significa cancelado: a Focus responde 200 com status
        // "erro_cancelamento"/"autorizado" quando a SEFAZ recusa (ex.: prazo de
        // 30 min vencido). Só marcamos cancelada com confirmação explícita —
        // marcar sem confirmar deixa nota VIVA na SEFAZ com venda cancelada aqui.
        $statusFocus = strtolower((string) ($data['status'] ?? ''));

        if ($response->successful() && in_array($statusFocus, ['cancelado', 'cancelada'], true)) {
            $nota->status = StatusNotaFiscal::Cancelada;
            $nota->focus_status = $data['status'];
            $nota->cancelamento_motivo = $justificativa;
            $nota->cancelamento_protocolo = $data['protocolo'] ?? null;
            $nota->cancelada_em = now();
            $nota->save();

            Log::info('NFCe: NFC-e cancelada com sucesso', ['ref' => $nota->focus_ref]);
            return $nota;
        }

        $rawMsg = $data['mensagem_sefaz']
            ?? $data['mensagem']
            ?? $data['erros'][0]['mensagem']
            ?? ($statusFocus !== '' ? "SEFAZ não confirmou o cancelamento (status: {$statusFocus})." : 'Erro desconhecido ao cancelar.');
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
                'cnpj' => \App\Support\Cnpj::limpar($unidade->cnpj ?: $unidade->empresa->cnpj),
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
     * Monta o payload completo da NFC-e (modelo 65, síncrona) a partir da Venda.
     * Usa FiscalPayloadBuilder. Inclui local_destino, valor_troco e valor_total_tributos.
     */
    private function buildPayload(Venda $venda, ConfiguracaoFiscal $config): array
    {
        $unidade = $venda->unidade;
        $empresa = $unidade->empresa;
        $cliente = $venda->cliente;

        $builder = new FiscalPayloadBuilder($empresa, $unidade, $config);
        $builder->validarVendaParaNFCe($venda);

        // Automático para regime normal (rejeição SEFAZ a partir de 03/08/2026 — NT 2025.002)
        $reforma = ReformaTributariaCalculator::paraEmissao($config, $empresa);

        // Itens
        $itens = [];
        $valorTotalProdutos = 0; // Σ vProd (bruto) — SEFAZ valida contra os itens
        $descontoItens = 0;
        foreach ($venda->itens as $index => $item) {
            $itens[] = $builder->itemNFCe($item, $index + 1, $reforma);
            $valorTotalProdutos += round((float) $item->preco_unitario * (float) $item->quantidade, 2);
            $descontoItens += (float) ($item->desconto_valor ?? 0);
        }

        $payload = array_merge(
            [
                'natureza_operacao' => 'Venda ao Consumidor',
                'data_emissao' => now()->format('Y-m-d\TH:i:sP'),
                'tipo_documento' => '1',
                'local_destino' => $builder->localDestino($cliente?->uf),
                'presenca_comprador' => '1',
                'consumidor_final' => '1',
                'finalidade_emissao' => '1',
                'modelo' => '65',
                'modalidade_frete' => '9',
            ],
            $builder->emitentePayload(),
            $builder->destinatarioNFCePayload($cliente, $venda->cpf_cnpj_nota),
            ['items' => $itens],
            // Totais (NFC-e usa subset)
            ['valor_produtos' => number_format($valorTotalProdutos, 2, '.', '')],
            ['valor_total' => number_format((float) $venda->total, 2, '.', '')],
        );

        // ICMS totais (a Focus aceita opcional, mas SEFAZ valida coerência)
        $totais = $builder->totaisPorItens($itens, $venda);
        $payload['icms_base_calculo'] = $totais['icms_base_calculo'];
        $payload['icms_valor_total'] = $totais['icms_valor_total'];

        // Totais IBS/CBS/IS (grupo IBSCBSTot — NT 2025.002, vale p/ NFC-e também)
        foreach (['ibs_cbs_base_calculo', 'ibs_uf_valor_total', 'ibs_mun_valor_total',
                  'ibs_valor_total', 'cbs_valor_total', 'is_valor_total'] as $campoReforma) {
            if (isset($totais[$campoReforma])) {
                $payload[$campoReforma] = $totais[$campoReforma];
            }
        }

        // IBPT — LC 165/2018: rodapé do cupom precisa do valor aproximado
        $valorTributos = $builder->valorTotalTributos($itens);
        if ($valorTributos > 0) {
            $payload['valor_total_tributos'] = number_format($valorTributos, 2, '.', '');
        }

        // Troco — quando dinheiro recebido > total
        $troco = $builder->valorTroco($venda);
        if ($troco > 0) {
            $payload['valor_troco'] = number_format($troco, 2, '.', '');
        }

        // Desconto (vDesc total = descontos dos itens + desconto global)
        // Necessário porque vProd é bruto: vNF = vProd − vDesc.
        $descontoGlobal = (float) ($venda->desconto_valor ?? 0);
        $descontoTotal = $descontoGlobal + $descontoItens;
        if ($descontoTotal > 0) {
            $payload['valor_desconto'] = number_format($descontoTotal, 2, '.', '');
        }

        // Outras despesas (vOutro) = juros do parcelamento cobrado do cliente.
        // Sem isso a conta vNF = vProd − vDesc + vOutro não fecha e a SEFAZ
        // rejeita a venda parcelada com juros.
        $outrasDespesas = (float) ($venda->outras_despesas ?? 0);
        if ($outrasDespesas > 0) {
            $payload['valor_outras_despesas'] = number_format($outrasDespesas, 2, '.', '');
        }

        // Formas de pagamento
        $payload['formas_pagamento'] = $builder->formasPagamento($venda);

        // Informações adicionais: mensagem fixa da Configuração Fiscal + observações da venda
        $infos = array_filter([
            $config->informacoes_complementares ?: null,
            $venda->observacoes ?: null,
        ]);
        if ($infos) {
            $payload['informacoes_adicionais_contribuinte'] = implode(' | ', $infos);
        }

        // Responsável técnico se configurado (opcional na NFC-e mas recomendado)
        $payload = array_merge($payload, $builder->responsavelTecnicoPayload());

        return $payload;
    }
}
