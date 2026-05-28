<?php

namespace App\Services\FocusNFe;

use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\ICMSCalculator;

/**
 * Builder centralizado para os blocos de payload Focus NFe (NF-e e NFC-e).
 *
 * Centraliza:
 *  - Construção do item (campos comuns + ICMS + ST + PIS + COFINS + IPI + IBS/CBS/IS + DI)
 *  - Construção do bloco emitente
 *  - Construção do destinatário
 *  - Mapeamento de formas de pagamento e regime tributário
 *  - Cálculo dos totais (icms_*, fcp_*, valor_*)
 *  - Pre-flight validation (rejeitar antes do POST)
 *
 * Usa o **snapshot fiscal do VendaItem** quando disponível, com fallback no Produto.
 */
class FiscalPayloadBuilder
{
    /** Códigos SEFAZ de forma de pagamento (Tabela 38 NT 2020.06). */
    private const FORMAS_PAGAMENTO = [
        'dinheiro'          => '01',
        'cheque'            => '02',
        'cartao_credito'    => '03',
        'credito'           => '03',
        'cartao_debito'     => '04',
        'debito'            => '04',
        'crediario'         => '05',
        'credito_loja'      => '05',
        'vale_alimentacao'  => '10',
        'vale_refeicao'     => '11',
        'vale_presente'     => '12',
        'vale_combustivel'  => '13',
        'boleto'            => '15',
        'deposito'          => '16',
        'pix'               => '17',
        'transferencia'     => '18',
        'sem_pagamento'     => '90',
    ];

    private const REGIME_TRIBUTARIO = [
        'simples_nacional' => '1',
        'lucro_presumido'  => '3',
        'lucro_real'       => '3',
    ];

    /** CSTs que indicam ST devida na operação. */
    private const CSTS_ICMS_ST = ['10', '30', '60', '70', '90', '201', '202', '203', '500'];

    public function __construct(
        private readonly Empresa $empresa,
        private readonly Unidade $unidade,
        private readonly ConfiguracaoFiscal $config,
    ) {}

    // ─── Pre-flight validation ──────────────────────────────────────────

    /**
     * Valida dados mínimos antes de chamar a Focus. Lança exceção se faltar.
     *
     * @throws \App\Exceptions\NotaFiscalEmissaoException
     */
    public function validarVendaParaNFe(Venda $venda): void
    {
        $erros = [];

        if (! $venda->cliente_id) {
            $erros[] = 'NF-e exige destinatário identificado — informe o cliente.';
        }

        $cliente = $venda->cliente;
        if ($cliente && (! $cliente->logradouro || ! $cliente->cidade || ! $cliente->uf || ! $cliente->cep)) {
            $erros[] = "Endereço incompleto do cliente {$cliente->nome_razao_social}.";
        }

        foreach ($venda->itens as $idx => $item) {
            $produto = $item->produto;
            $ncm = $item->snapshot_ncm ?: $produto?->ncm;
            $cfop = $item->snapshot_cfop ?: $produto?->cfop;
            if (! $ncm || $ncm === '00000000') {
                $erros[] = "Item #" . ($idx + 1) . " ({$produto?->descricao}): NCM inválido.";
            }
            if (! $cfop) {
                $erros[] = "Item #" . ($idx + 1) . " ({$produto?->descricao}): CFOP não definido.";
            }
        }

        if (! $this->config->responsavel_tecnico_cnpj) {
            $erros[] = 'Responsável técnico não cadastrado — obrigatório desde a NT 2018/003.';
        }

        if ($erros) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Não foi possível emitir a NF-e: ' . PHP_EOL . '• ' . implode(PHP_EOL . '• ', $erros)
            );
        }
    }

    public function validarVendaParaNFCe(Venda $venda): void
    {
        $erros = [];
        foreach ($venda->itens as $idx => $item) {
            $ncm = $item->snapshot_ncm ?: $item->produto?->ncm;
            if (! $ncm || $ncm === '00000000') {
                $erros[] = "Item #" . ($idx + 1) . " ({$item->produto?->descricao}): NCM inválido.";
            }
        }
        if ($erros) {
            throw new \App\Exceptions\NotaFiscalEmissaoException(
                'Não foi possível emitir a NFC-e: ' . PHP_EOL . '• ' . implode(PHP_EOL . '• ', $erros)
            );
        }
    }

    // ─── Emitente / Destinatário / Resp. técnico ────────────────────────

    public function emitentePayload(): array
    {
        $cnpj = $this->digits($this->unidade->cnpj ?: $this->empresa->cnpj);

        $payload = [
            'cnpj_emitente' => $cnpj,
            'nome_emitente' => $this->empresa->razao_social,
            'nome_fantasia_emitente' => $this->empresa->nome_fantasia ?? $this->empresa->razao_social,
            'inscricao_estadual_emitente' => $this->digits($this->unidade->ie ?: $this->empresa->ie ?? ''),
            'logradouro_emitente' => $this->unidade->logradouro ?: $this->empresa->logradouro,
            'numero_emitente' => $this->unidade->numero ?: $this->empresa->numero,
            'bairro_emitente' => $this->unidade->bairro ?: $this->empresa->bairro,
            'municipio_emitente' => $this->unidade->cidade ?: $this->empresa->cidade,
            'uf_emitente' => $this->unidade->uf ?: $this->empresa->uf,
            'cep_emitente' => $this->digits($this->unidade->cep ?: $this->empresa->cep ?? ''),
            'regime_tributario_emitente' => self::REGIME_TRIBUTARIO[$this->empresa->regime_tributario?->value] ?? '1',
        ];

        $complemento = $this->unidade->complemento ?: $this->empresa->complemento;
        if ($complemento) {
            $payload['complemento_emitente'] = $complemento;
        }

        $telefone = $this->unidade->telefone ?: $this->empresa->telefone;
        if ($telefone) {
            $payload['telefone_emitente'] = $this->digits($telefone);
        }

        $im = $this->unidade->im ?: $this->empresa->im;
        if ($im) {
            $payload['inscricao_municipal_emitente'] = $this->digits($im);
        }

        return $payload;
    }

    /**
     * Bloco do responsável técnico (NT 2018/003).
     */
    public function responsavelTecnicoPayload(): array
    {
        if (! $this->config->responsavel_tecnico_cnpj) {
            return [];
        }
        return [
            'cnpj_responsavel_tecnico' => $this->digits($this->config->responsavel_tecnico_cnpj),
            'contato_responsavel_tecnico' => $this->config->responsavel_tecnico_nome,
            'email_responsavel_tecnico' => $this->config->responsavel_tecnico_email,
            'telefone_responsavel_tecnico' => $this->digits($this->config->responsavel_tecnico_telefone),
        ];
    }

    public function destinatarioNFePayload(?\App\Models\Cliente $cliente): array
    {
        if (! $cliente) {
            return [];
        }
        $cpfCnpj = $this->digits($cliente->cpf_cnpj ?? '');
        $payload = ['nome_destinatario' => $cliente->nome_razao_social];

        if (strlen($cpfCnpj) === 11) {
            $payload['cpf_destinatario'] = $cpfCnpj;
        } elseif (strlen($cpfCnpj) === 14) {
            $payload['cnpj_destinatario'] = $cpfCnpj;
        }

        if ($cliente->ie) {
            $payload['inscricao_estadual_destinatario'] = $this->digits($cliente->ie);
            $payload['indicador_inscricao_estadual_destinatario'] = '1';
        } else {
            $payload['indicador_inscricao_estadual_destinatario'] = '9';
        }

        if ($cliente->logradouro) {
            $payload['logradouro_destinatario'] = $cliente->logradouro;
            $payload['numero_destinatario'] = $cliente->numero ?: 'S/N';
            $payload['bairro_destinatario'] = $cliente->bairro ?: '';
            $payload['municipio_destinatario'] = $cliente->cidade ?: '';
            $payload['uf_destinatario'] = $cliente->uf ?: '';
            $payload['cep_destinatario'] = $this->digits($cliente->cep ?: '');
        }
        if ($cliente->complemento) {
            $payload['complemento_destinatario'] = $cliente->complemento;
        }
        if ($cliente->telefone) {
            $payload['telefone_destinatario'] = $this->digits($cliente->telefone);
        }
        if ($cliente->email) {
            $payload['email_destinatario'] = $cliente->email;
        }

        return $payload;
    }

    /** Destinatário simplificado para NFC-e (opcional, só CPF + nome). */
    public function destinatarioNFCePayload(?\App\Models\Cliente $cliente): array
    {
        if (! $cliente) {
            return [];
        }
        $cpfCnpj = $this->digits($cliente->cpf_cnpj ?? '');
        $payload = [];
        if (strlen($cpfCnpj) === 11) {
            $payload['cpf_destinatario'] = $cpfCnpj;
        } elseif (strlen($cpfCnpj) === 14) {
            $payload['cnpj_destinatario'] = $cpfCnpj;
        }
        if ($cliente->nome_razao_social) {
            $payload['nome_destinatario'] = $cliente->nome_razao_social;
        }
        return $payload;
    }

    // ─── Item NF-e (completo) ───────────────────────────────────────────

    /**
     * Monta um item completo para NF-e (modelo 55).
     * Inclui: ICMS, ICMS-ST (via ICMSCalculator), PIS, COFINS, IPI, IBS/CBS/IS (Reforma), DI.
     */
    public function itemNFe(VendaItem $item, int $numeroItem, ?ReformaTributariaCalculator $reforma = null, bool $incluiIpi = true): array
    {
        $produto = $item->produto;
        $valorUnitario = $this->fmt($item->preco_unitario, 4);
        $quantidade = $this->fmt($item->quantidade, 4);
        $valorTotal = $this->fmt($item->total, 2);
        $valorDesconto = (float) ($item->desconto_valor ?? 0);

        $ncm = $item->fiscal('ncm', '00000000');
        $cfop = $item->fiscal('cfop', '5102');
        $cstCsosn = $item->fiscal('cst_csosn', '102');
        $origem = $item->fiscal('origem', '0');
        $cstPis = $item->fiscal('cst_pis', '99');
        $cstCofins = $item->fiscal('cst_cofins', '99');
        $cstIpi = $item->fiscal('cst_ipi', '50');
        $icmsAliq = (float) $item->fiscal('icms_aliquota', 0);
        $pisAliq = (float) $item->fiscal('pis_aliquota', 0);
        $cofinsAliq = (float) $item->fiscal('cofins_aliquota', 0);
        $ipiAliq = (float) $item->fiscal('ipi_aliquota', 0);
        $unidade = $item->fiscal('unidade_medida', 'UN');

        $payload = [
            'numero_item' => $numeroItem,
            'codigo_produto' => $produto->codigo_interno ?? $produto->codigo_barras ?? (string) $produto->id,
            'descricao' => $item->descricao ?: $produto->descricao,
            'codigo_ncm' => $ncm,
            'cfop' => $cfop,
            'unidade_comercial' => $unidade,
            'quantidade_comercial' => $quantidade,
            'valor_unitario_comercial' => $valorUnitario,
            'valor_bruto' => $valorTotal,
            'unidade_tributavel' => $unidade,
            'quantidade_tributavel' => $quantidade,
            'valor_unitario_tributavel' => $valorUnitario,
            'origem' => (string) $origem,
            'inclui_no_total' => '1',
        ];

        if ($valorDesconto > 0) {
            $payload['valor_desconto'] = $this->fmt($valorDesconto, 2);
        }
        if ($produto->codigo_barras) {
            $payload['codigo_barras_comercial'] = $produto->codigo_barras;
            $payload['codigo_barras_tributavel'] = $produto->codigo_barras;
        } else {
            $payload['codigo_barras_comercial'] = 'SEM GTIN';
            $payload['codigo_barras_tributavel'] = 'SEM GTIN';
        }
        if ($item->fiscal('cest')) {
            $payload['cest'] = $item->fiscal('cest');
        }
        if ($produto->peso_liquido) {
            $payload['peso_liquido'] = $this->fmt($produto->peso_liquido, 3);
        }
        if ($produto->peso_bruto) {
            $payload['peso_bruto'] = $this->fmt($produto->peso_bruto, 3);
        }

        // ─── ICMS ───
        $payload['icms_situacao_tributaria'] = (string) $cstCsosn;
        $payload['icms_origem'] = (string) $origem;
        $modBc = $produto->icms_modalidade_bc ?: '3';

        $isSimples = $this->empresa->regime_tributario?->value === 'simples_nacional';

        if (! $isSimples && $icmsAliq > 0) {
            $payload['icms_aliquota'] = $this->fmt($icmsAliq, 2);
            $payload['icms_base_calculo'] = $valorTotal;
            $payload['icms_modalidade_base_calculo'] = $modBc;
        }

        // ─── ICMS-ST (interestadual) ───
        $stData = $this->calcularST($item, $cstCsosn);
        if ($stData) {
            $payload += $stData;
        }

        // ─── PIS ───
        $valorPis = round($item->total * ($pisAliq / 100), 2);
        $payload['pis_situacao_tributaria'] = (string) $cstPis;
        $payload['pis_base_calculo'] = $valorTotal;
        $payload['pis_aliquota_porcentual'] = $this->fmt($pisAliq, 2);
        $payload['pis_valor'] = $this->fmt($valorPis, 2);

        // ─── COFINS ───
        $valorCofins = round($item->total * ($cofinsAliq / 100), 2);
        $payload['cofins_situacao_tributaria'] = (string) $cstCofins;
        $payload['cofins_base_calculo'] = $valorTotal;
        $payload['cofins_aliquota_porcentual'] = $this->fmt($cofinsAliq, 2);
        $payload['cofins_valor'] = $this->fmt($valorCofins, 2);

        // ─── IPI (NF-e apenas) ───
        if ($incluiIpi && $ipiAliq > 0) {
            $valorIpi = round($item->total * ($ipiAliq / 100), 2);
            $payload['ipi_situacao_tributaria'] = (string) $cstIpi;
            $payload['ipi_base_calculo'] = $valorTotal;
            $payload['ipi_aliquota'] = $this->fmt($ipiAliq, 2);
            $payload['ipi_valor'] = $this->fmt($valorIpi, 2);
        }

        // ─── DI (importação) ───
        if ($produto->ehImportado() && $produto->di_numero) {
            $payload['declaracoes_importacao'] = [[
                'numero_di' => $produto->di_numero,
                'data_registro' => optional($produto->di_data)->format('Y-m-d'),
                'local_desembaraco' => $produto->di_local_desembaraco,
                'uf_desembaraco' => $produto->di_uf_desembaraco,
                'data_desembaraco' => optional($produto->di_data_desembaraco)->format('Y-m-d'),
                'codigo_exportador' => $produto->di_adicao_numero,
                'via_transporte_internacional' => (string) ($produto->di_via_transp ?: '1'),
                'valor_afrmm' => $produto->di_valor_afrmm ? $this->fmt($produto->di_valor_afrmm, 2) : null,
                'forma_importacao_intermediacao' => (string) ($produto->di_forma_importacao ?: '1'),
                'adicoes' => [[
                    'numero_adicao' => $produto->di_adicao_numero ?: '1',
                    'numero_sequencial_adicao' => '1',
                ]],
            ]];
            // Remove null
            $payload['declaracoes_importacao'][0] = array_filter(
                $payload['declaracoes_importacao'][0],
                fn ($v) => $v !== null && $v !== ''
            );
        }

        // ─── Reforma Tributária (IBS/CBS/IS) ───
        if ($reforma) {
            $bloco = $reforma->blocoPayload((float) $item->total, [
                'ibs_aliquota' => $produto->ibs_aliquota,
                'cbs_aliquota' => $produto->cbs_aliquota,
                'is_aliquota' => $produto->is_aliquota,
                'cst_ibs_cbs' => $produto->cst_ibs_cbs,
                'classificacao_ibs' => $produto->classificacao_ibs,
            ]);
            foreach ($bloco as $k => $v) {
                $payload[$k] = $v;
            }
        }

        return $payload;
    }

    /**
     * Calcula ST quando aplicável. Retorna campos de ICMS-ST para mesclar no item.
     */
    private function calcularST(VendaItem $item, string $cst): ?array
    {
        if (! in_array($cst, self::CSTS_ICMS_ST, true)) {
            return null;
        }
        $ufOrigem = $this->unidade->uf ?: $this->empresa->uf;
        $ufDestino = $item->venda?->cliente?->uf;
        if (! $ufOrigem || ! $ufDestino || $ufOrigem === $ufDestino) {
            return null; // ST interestadual só faz sentido entre UFs diferentes
        }
        $produto = $item->produto;
        $calc = ICMSCalculator::calcular($ufOrigem, $ufDestino, (float) $item->total, $produto?->mva_st);
        if (! ($calc['tem_st'] ?? false)) {
            return null;
        }
        return [
            'icms_modalidade_base_calculo_st' => $produto?->icms_modalidade_bc_st ?: '4',
            'icms_base_calculo_st' => $this->fmt($calc['base_st'], 2),
            'icms_aliquota_st' => $this->fmt($calc['aliquota_interna'], 2),
            'icms_valor_st' => $this->fmt($calc['icms_st'], 2),
            'icms_valor_margem_valor_adicionado_st' => $this->fmt($calc['mva'], 4),
            'fcp_aliquota_st' => $this->fmt($calc['fcp'] > 0 ? ($calc['fcp'] / max($calc['base_st'], 1)) * 100 : 0, 4),
            'fcp_valor_st' => $this->fmt($calc['fcp'], 2),
        ];
    }

    // ─── Item NFC-e ─────────────────────────────────────────────────────

    /** NFC-e: como itemNFe mas sem IPI (modelo 65 não aceita). */
    public function itemNFCe(VendaItem $item, int $numeroItem, ?ReformaTributariaCalculator $reforma = null): array
    {
        return $this->itemNFe($item, $numeroItem, $reforma, incluiIpi: false);
    }

    // ─── Totais ─────────────────────────────────────────────────────────

    /**
     * Soma os campos de tributo dos itens para o bloco de totais.
     */
    public function totaisPorItens(array $itens, Venda $venda): array
    {
        $somaCampo = fn (string $k) => array_sum(array_map(fn ($i) => (float) ($i[$k] ?? 0), $itens));

        return [
            'icms_base_calculo' => $this->fmt($somaCampo('icms_base_calculo'), 2),
            'icms_valor_total' => $this->fmt($somaCampo('icms_valor'), 2), // alias
            'icms_base_calculo_st' => $this->fmt($somaCampo('icms_base_calculo_st'), 2),
            'icms_valor_total_st' => $this->fmt($somaCampo('icms_valor_st'), 2),
            'fcp_valor_total' => $this->fmt($somaCampo('fcp_valor'), 2),
            'fcp_valor_total_st' => $this->fmt($somaCampo('fcp_valor_st'), 2),
            'valor_ipi' => $this->fmt($somaCampo('ipi_valor'), 2),
            'valor_pis' => $this->fmt($somaCampo('pis_valor'), 2),
            'valor_cofins' => $this->fmt($somaCampo('cofins_valor'), 2),
            'valor_frete' => $this->fmt($venda->valor_frete ?? 0, 2),
            'valor_seguro' => $this->fmt($venda->valor_seguro ?? 0, 2),
            'valor_outras_despesas' => $this->fmt($venda->outras_despesas ?? 0, 2),
        ];
    }

    // ─── Pagamentos ─────────────────────────────────────────────────────

    public function formasPagamento(Venda $venda): array
    {
        $formas = [];
        if (is_array($venda->pagamento_detalhes) && count($venda->pagamento_detalhes) > 0) {
            foreach ($venda->pagamento_detalhes as $pg) {
                $forma = [
                    'forma_pagamento' => self::FORMAS_PAGAMENTO[$pg['forma'] ?? $venda->forma_pagamento] ?? '99',
                    'valor_pagamento' => $this->fmt($pg['valor'] ?? $venda->total, 2),
                ];
                if (in_array($pg['forma'] ?? '', ['credito', 'debito', 'cartao_credito', 'cartao_debito'])) {
                    $forma['bandeira_operadora'] = $pg['bandeira'] ?? '99';
                    if (! empty($pg['cnpj_credenciadora'])) {
                        $forma['cnpj_credenciadora'] = $this->digits($pg['cnpj_credenciadora']);
                    }
                    if (! empty($pg['autorizacao'])) {
                        $forma['numero_autorizacao'] = $pg['autorizacao'];
                    }
                }
                $formas[] = $forma;
            }
        } else {
            $formas[] = [
                'forma_pagamento' => self::FORMAS_PAGAMENTO[$venda->forma_pagamento] ?? '99',
                'valor_pagamento' => $this->fmt($venda->total, 2),
            ];
        }
        return $formas;
    }

    /**
     * Valor de troco (NFC-e) — quando há pagamento em dinheiro > total.
     */
    public function valorTroco(Venda $venda): float
    {
        if (! is_array($venda->pagamento_detalhes)) {
            return 0.0;
        }
        $totalPago = array_sum(array_map(fn ($p) => (float) ($p['valor'] ?? 0), $venda->pagamento_detalhes));
        return max(0, round($totalPago - (float) $venda->total, 2));
    }

    /**
     * Soma valor_total_tributos (IBPT — LC 165/2018) — obrigatório no rodapé do cupom.
     */
    public function valorTotalTributos(array $itens): float
    {
        // Soma percentual_tributos_ibpt * total do item / 100, com fallback estimado de 25% sobre valor.
        $total = 0;
        foreach ($itens as $i) {
            $perc = $i['__percentual_ibpt'] ?? 25.0;
            $valorBruto = (float) ($i['valor_bruto'] ?? 0);
            $total += round($valorBruto * ($perc / 100), 2);
        }
        return round($total, 2);
    }

    // ─── Local destino (1/2/3) ──────────────────────────────────────────

    /**
     * 1 = interna (mesma UF), 2 = interestadual, 3 = exterior.
     */
    public function localDestino(?string $ufDestino): string
    {
        $ufOrigem = strtoupper($this->unidade->uf ?: $this->empresa->uf ?: '');
        $ufDestino = strtoupper($ufDestino ?: '');
        if (! $ufDestino) return '1';
        if ($ufDestino === 'EX') return '3';
        return $ufOrigem === $ufDestino ? '1' : '2';
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function digits(?string $v): string
    {
        return preg_replace('/\D/', '', $v ?? '') ?? '';
    }

    private function fmt(float|int|string|null $v, int $casas = 2): string
    {
        return number_format((float) $v, $casas, '.', '');
    }
}
