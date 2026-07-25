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
     * Emite uma Carta de Correção para uma NF-e autorizada e persiste o histórico.
     *
     * @throws \App\Exceptions\CartaCorrecaoException
     */
    public function cartaCorrecao(NotaFiscal $nota, string $correcao, ?int $userId = null): \App\Models\CartaCorrecao
    {
        $sequencia = ((int) $nota->cartasCorrecao()->max('numero_sequencia')) + 1;

        if ($sequencia > 20) {
            throw new \App\Exceptions\CartaCorrecaoException(
                'Esta NF-e já atingiu o limite de 20 Cartas de Correção previsto pela SEFAZ.'
            );
        }

        $carta = \App\Models\CartaCorrecao::create([
            'nota_fiscal_id'   => $nota->id,
            'empresa_id'       => $nota->empresa_id,
            'unidade_id'       => $nota->unidade_id,
            'user_id'          => $userId,
            'numero_sequencia' => $sequencia,
            'correcao'         => $correcao,
            'status'           => 'pendente',
            'enviada_em'       => now(),
        ]);

        Log::info('NFe: Emitindo Carta de Correção', [
            'ref'        => $nota->focus_ref,
            'sequencia'  => $sequencia,
            'carta_id'   => $carta->id,
        ]);

        try {
            $response = $this->client->post("/v2/nfe/{$nota->focus_ref}/carta_correcao", [
                'correcao'         => $correcao,
                'numero_sequencia' => $sequencia,
            ]);
        } catch (\Throwable $e) {
            Log::error('NFe: Erro de comunicação ao emitir CC-e', [
                'ref'      => $nota->focus_ref,
                'error'    => $e->getMessage(),
                'carta_id' => $carta->id,
            ]);
            $carta->update(['status' => 'rejeitada', 'mensagem_sefaz' => 'Falha de conexão: ' . $e->getMessage()]);
            throw new \App\Exceptions\CartaCorrecaoException(
                'Não foi possível conectar à SEFAZ para enviar a Carta de Correção. Verifique sua conexão e tente novamente.',
                0, $e
            );
        }

        $data = $response->json() ?? [];

        Log::info('NFe: Resultado CC-e', [
            'ref'      => $nota->focus_ref,
            'status'   => $response->status(),
            'response' => $data,
        ]);

        if (! $response->successful()) {
            $rawMsg = $data['mensagem'] ?? $data['erros'][0]['mensagem'] ?? 'Erro desconhecido.';
            $friendly = $this->translateCCeError($rawMsg, $response->status());

            $carta->update([
                'status'         => 'rejeitada',
                'mensagem_sefaz' => $rawMsg,
            ]);

            throw new \App\Exceptions\CartaCorrecaoException($friendly);
        }

        $carta->update([
            'status'         => 'autorizada',
            'protocolo'      => $data['numero_protocolo'] ?? $data['protocolo'] ?? null,
            'mensagem_sefaz' => $data['mensagem_sefaz'] ?? null,
            'xml_url'        => $data['caminho_xml_carta_correcao'] ?? null,
            'pdf_url'        => $data['caminho_pdf_carta_correcao'] ?? null,
        ]);

        return $carta->fresh();
    }

    /**
     * Traduz erros comuns de CC-e para texto amigável pt-BR.
     */
    private function translateCCeError(string $raw, int $httpStatus): string
    {
        $lower = mb_strtolower($raw);

        if (str_contains($lower, 'prazo')) {
            return 'O prazo para emitir Carta de Correção foi excedido (720h após autorização).';
        }
        if (str_contains($lower, 'limite')) {
            return 'Esta NF-e já atingiu o limite de 20 Cartas de Correção.';
        }
        if (str_contains($lower, 'nao autorizada') || str_contains($lower, 'não autorizada') || str_contains($lower, 'cancelad')) {
            return 'Só é possível emitir Carta de Correção em NF-e autorizada — esta não está autorizada ou foi cancelada.';
        }
        if (str_contains($lower, 'certificado')) {
            return 'Certificado digital inválido ou expirado. Verifique as configurações fiscais.';
        }
        if ($httpStatus === 401 || str_contains($lower, 'token')) {
            return 'Token Focus NFe inválido. Verifique as configurações fiscais.';
        }
        if ($httpStatus >= 500) {
            return 'A SEFAZ está instável no momento. Tente novamente em alguns minutos.';
        }

        return "Não foi possível emitir a Carta de Correção: {$raw}";
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
                'cnpj' => \App\Support\Cnpj::limpar($unidade->cnpj ?: $unidade->empresa->cnpj),
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
     * Usa FiscalPayloadBuilder para os blocos (emitente, destinatário, itens, totais).
     * Inclui responsável técnico, ICMS-ST, IBS/CBS/IS, DI quando aplicável.
     */
    private function buildPayload(Venda $venda, ConfiguracaoFiscal $config, array $dadosAdicionais = []): array
    {
        $unidade = $venda->unidade;
        $empresa = $unidade->empresa;
        $cliente = $venda->cliente;

        $builder = new FiscalPayloadBuilder($empresa, $unidade, $config);
        $builder->validarVendaParaNFe($venda);

        // Automático para regime normal (rejeição SEFAZ a partir de 03/08/2026 — NT 2025.002)
        $reforma = ReformaTributariaCalculator::paraEmissao($config, $empresa);

        // ── Itens ────────────────────────────────────────────────────────
        $itens = [];
        $valorTotalProdutos = 0;
        foreach ($venda->itens as $index => $item) {
            $itens[] = $builder->itemNFe($item, $index + 1, $reforma, incluiIpi: true);
            $valorTotalProdutos += (float) $item->total;
        }

        // ── Header ───────────────────────────────────────────────────────
        $ufDestino = $cliente?->uf;
        $payload = array_merge(
            [
                'natureza_operacao' => $dadosAdicionais['natureza_operacao'] ?? 'Venda de Mercadoria',
                'data_emissao' => now()->format('Y-m-d\TH:i:sP'),
                'tipo_documento' => '1',
                'local_destino' => $builder->localDestino($ufDestino),
                'finalidade_emissao' => $dadosAdicionais['finalidade_emissao'] ?? '1',
                'consumidor_final' => $dadosAdicionais['consumidor_final'] ?? '0',
                'presenca_comprador' => $dadosAdicionais['presenca_comprador'] ?? '1',
                'indicador_intermediario' => $dadosAdicionais['indicador_intermediario'] ?? '0',
                'modelo' => '55',
            ],
            $builder->emitentePayload(),
            $builder->destinatarioNFePayload($cliente),
            ['items' => $itens],
            $builder->totaisPorItens($itens, $venda),
            $builder->responsavelTecnicoPayload(),
        );

        $payload['valor_produtos'] = number_format($valorTotalProdutos, 2, '.', '');
        $payload['valor_total'] = number_format((float) $venda->total, 2, '.', '');

        // ── valor_total_tributos (IBPT — LC 165/2018) ────────────────────
        $valorTributos = $builder->valorTotalTributos($itens);
        if ($valorTributos > 0) {
            $payload['valor_total_tributos'] = number_format($valorTributos, 2, '.', '');
        }

        // ── Desconto global ──────────────────────────────────────────────
        $descontoGlobal = (float) ($venda->desconto_valor ?? 0);
        if ($descontoGlobal > 0) {
            $payload['valor_desconto'] = number_format($descontoGlobal, 2, '.', '');
        }

        // ── Pagamentos + duplicatas (boleto/crediário a prazo) ───────────
        $payload['formas_pagamento'] = $builder->formasPagamento($venda);

        $formasPrazo = ['boleto', 'crediario', 'credito_loja'];
        $temPrazo = in_array($venda->forma_pagamento, $formasPrazo, true) || (
            is_array($venda->pagamento_detalhes) &&
            collect($venda->pagamento_detalhes)->contains(fn ($p) => in_array($p['forma'] ?? '', $formasPrazo, true))
        );
        if ($temPrazo) {
            $payload['numero_fatura'] = sprintf('%06d', $venda->id);
            $payload['valor_original_fatura'] = number_format((float) $venda->total, 2, '.', '');
            $payload['valor_desconto_fatura'] = number_format($descontoGlobal, 2, '.', '');
            $payload['valor_liquido_fatura'] = number_format((float) $venda->total - $descontoGlobal, 2, '.', '');
            $payload['duplicatas'] = $this->buildDuplicatas($venda);
        }

        // ── Transporte ───────────────────────────────────────────────────
        $payload['modalidade_frete'] = $dadosAdicionais['modalidade_frete'] ?? '9';
        if (isset($dadosAdicionais['transportadora'])) {
            $transp = $dadosAdicionais['transportadora'];
            $map = [
                'cnpj' => 'cnpj_transportador',
                'razao_social' => 'nome_transportador',
                'ie' => 'inscricao_estadual_transportador',
                'endereco' => 'endereco_transportador',
                'municipio' => 'municipio_transportador',
                'uf' => 'uf_transportador',
            ];
            foreach ($map as $src => $dst) {
                if (! empty($transp[$src])) {
                    $payload[$dst] = in_array($src, ['cnpj', 'ie'], true)
                        ? preg_replace('/\D/', '', $transp[$src])
                        : $transp[$src];
                }
            }
        }
        if (isset($dadosAdicionais['volumes'])) {
            $payload['volumes'] = $dadosAdicionais['volumes'];
        }

        // ── Informações adicionais ───────────────────────────────────────
        $infos = array_filter([
            $dadosAdicionais['informacoes_complementares'] ?? null,
            $venda->observacoes ?: null,
        ]);
        if ($infos) {
            $payload['informacoes_adicionais_contribuinte'] = implode(' | ', $infos);
        }
        if (isset($dadosAdicionais['informacoes_fisco'])) {
            $payload['informacoes_adicionais_fisco'] = $dadosAdicionais['informacoes_fisco'];
        }

        return $payload;
    }

    /**
     * Monta duplicatas a partir das parcelas da venda (se houver) ou
     * gera uma única duplicata com vencimento em 30 dias.
     */
    private function buildDuplicatas(Venda $venda): array
    {
        if (is_array($venda->pagamento_detalhes) && count($venda->pagamento_detalhes) > 1) {
            $dups = [];
            foreach ($venda->pagamento_detalhes as $i => $pg) {
                $dups[] = [
                    'numero' => sprintf('%03d', $i + 1),
                    'data_vencimento' => $pg['data_vencimento'] ?? now()->addDays(30 * ($i + 1))->format('Y-m-d'),
                    'valor' => number_format((float) ($pg['valor'] ?? 0), 2, '.', ''),
                ];
            }
            return $dups;
        }
        return [[
            'numero' => '001',
            'data_vencimento' => now()->addDays(30)->format('Y-m-d'),
            'valor' => number_format((float) $venda->total, 2, '.', ''),
        ]];
    }
}
