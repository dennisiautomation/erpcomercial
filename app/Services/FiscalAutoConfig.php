<?php
namespace App\Services;

class FiscalAutoConfig
{
    /**
     * Returns default fiscal values based on empresa's regime tributario.
     * These are sensible defaults — users can override.
     */
    public static function defaults(string $regimeTributario): array
    {
        return match($regimeTributario) {
            'simples_nacional' => [
                'cst_csosn' => '102',  // CSOSN 102 = Tributada sem crédito
                'icms_aliquota' => 0,
                'pis_aliquota' => 0,   // Simples não destaca PIS/COFINS
                'cst_pis' => '49',     // Outras operações de saída
                'cofins_aliquota' => 0,
                'cst_cofins' => '49',
                'ipi_aliquota' => 0,
                'cst_ipi' => '53',     // Saída não tributada
                'icms_modalidade_bc' => '3',
                'icms_modalidade_bc_st' => '4',
                'cfop_venda_interna' => '5102',  // Venda mercadoria adquirida
                'cfop_venda_interestadual' => '6102',
                'origem' => 0, // Nacional
                'label' => 'Simples Nacional — CSOSN 102 (sem crédito)',
                'help' => 'No Simples Nacional, os impostos são recolhidos via DAS. Não é necessário destacar ICMS, PIS e COFINS na nota.',
            ],
            'lucro_presumido' => [
                'cst_csosn' => '00',   // CST 00 = Tributada integralmente
                'icms_aliquota' => 18, // SP default
                'pis_aliquota' => 0.65,
                'cst_pis' => '01',     // Operação tributável com alíquota normal
                'cofins_aliquota' => 3.0,
                'cst_cofins' => '01',
                'ipi_aliquota' => 0,
                'cst_ipi' => '53',
                'icms_modalidade_bc' => '3',
                'icms_modalidade_bc_st' => '4',
                'cfop_venda_interna' => '5102',
                'cfop_venda_interestadual' => '6102',
                'origem' => 0,
                'label' => 'Lucro Presumido — CST 00 (tributação integral)',
                'help' => 'PIS 0,65% e COFINS 3,0% no regime cumulativo. ICMS varia por estado (18% em SP).',
            ],
            'lucro_real' => [
                'cst_csosn' => '00',
                'icms_aliquota' => 18,
                'pis_aliquota' => 1.65,
                'cst_pis' => '01',
                'cofins_aliquota' => 7.6,
                'cst_cofins' => '01',
                'ipi_aliquota' => 0,
                'cst_ipi' => '53',
                'icms_modalidade_bc' => '3',
                'icms_modalidade_bc_st' => '4',
                'cfop_venda_interna' => '5102',
                'cfop_venda_interestadual' => '6102',
                'origem' => 0,
                'label' => 'Lucro Real — CST 00 (não cumulativo)',
                'help' => 'PIS 1,65% e COFINS 7,60% no regime não cumulativo. Alíquotas maiores mas permitem crédito.',
            ],
            default => [
                'cst_csosn' => '',
                'icms_aliquota' => 0,
                'pis_aliquota' => 0,
                'cst_pis' => '49',
                'cofins_aliquota' => 0,
                'cst_cofins' => '49',
                'ipi_aliquota' => 0,
                'cst_ipi' => '53',
                'icms_modalidade_bc' => '3',
                'icms_modalidade_bc_st' => '4',
                'cfop_venda_interna' => '5102',
                'cfop_venda_interestadual' => '6102',
                'origem' => 0,
                'label' => '',
                'help' => '',
            ],
        };
    }

    /**
     * CSTs PIS/COFINS aceitos pela SEFAZ (Tabela II — IN RFB 1.234/2012).
     * Útil para popular dropdown na tela de cadastro do produto.
     */
    public static function cstPisCofinsOptions(): array
    {
        return [
            '01' => '01 — Operação Tributável (alíquota normal)',
            '02' => '02 — Operação Tributável (alíquota diferenciada)',
            '03' => '03 — Operação Tributável (alíquota por unidade)',
            '04' => '04 — Operação Tributável Monofásica (revenda)',
            '05' => '05 — Tributação Concentrada (substituição tributária)',
            '06' => '06 — Alíquota Zero',
            '07' => '07 — Isenta da Contribuição',
            '08' => '08 — Sem Incidência da Contribuição',
            '09' => '09 — Suspensão da Contribuição',
            '49' => '49 — Outras Operações de Saída',
            '99' => '99 — Outras Operações',
        ];
    }

    /**
     * CSTs IPI mais comuns.
     */
    public static function cstIpiOptions(): array
    {
        return [
            '50' => '50 — Saída Tributada',
            '51' => '51 — Saída Tributável com alíquota zero',
            '52' => '52 — Saída Isenta',
            '53' => '53 — Saída Não Tributada',
            '54' => '54 — Saída Imune',
            '55' => '55 — Saída com Suspensão',
            '99' => '99 — Outras saídas',
        ];
    }

    /**
     * Modalidade da base de cálculo do ICMS (Tabela 17 NT 2020).
     */
    public static function modalidadeBcOptions(): array
    {
        return [
            '0' => '0 — Margem de Valor Agregado (%)',
            '1' => '1 — Pauta (valor)',
            '2' => '2 — Preço Tabelado Máximo (valor)',
            '3' => '3 — Valor da Operação',
        ];
    }

    /**
     * Modalidade da base de cálculo do ICMS-ST.
     */
    public static function modalidadeBcStOptions(): array
    {
        return [
            '0' => '0 — Preço Tabelado ou Máximo Sugerido',
            '1' => '1 — Lista Negativa',
            '2' => '2 — Lista Positiva',
            '3' => '3 — Lista Neutra',
            '4' => '4 — Margem Valor Agregado (MVA)',
            '5' => '5 — Pauta (valor)',
        ];
    }

    /**
     * Common CFOP options for dropdown.
     */
    public static function cfopOptions(): array
    {
        return [
            '5102' => '5102 — Venda de mercadoria adquirida (dentro do estado)',
            '5405' => '5405 — Venda de mercadoria com ST (Simples Nacional)',
            '5403' => '5403 — Venda com ST (indústria)',
            '5101' => '5101 — Venda de produção própria',
            '5949' => '5949 — Outra saída não especificada',
            '6102' => '6102 — Venda interestadual de mercadoria adquirida',
            '6108' => '6108 — Venda interestadual a consumidor final',
        ];
    }

    /**
     * Origem options (0-8).
     */
    public static function origemOptions(): array
    {
        return [
            0 => '0 — Nacional',
            1 => '1 — Estrangeira (importação direta)',
            2 => '2 — Estrangeira (mercado interno)',
            3 => '3 — Nacional com conteúdo importado 40-70%',
            4 => '4 — Nacional por processos básicos',
            5 => '5 — Nacional com conteúdo importado <40%',
            6 => '6 — Estrangeira sem similar nacional',
            7 => '7 — Estrangeira com similar nacional',
            8 => '8 — Nacional com conteúdo importado >70%',
        ];
    }
}
