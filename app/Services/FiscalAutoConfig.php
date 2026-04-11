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
                'cofins_aliquota' => 0,
                'ipi_aliquota' => 0,
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
                'cofins_aliquota' => 3.0,
                'ipi_aliquota' => 0,
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
                'cofins_aliquota' => 7.6,
                'ipi_aliquota' => 0,
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
                'cofins_aliquota' => 0,
                'ipi_aliquota' => 0,
                'cfop_venda_interna' => '5102',
                'cfop_venda_interestadual' => '6102',
                'origem' => 0,
                'label' => '',
                'help' => '',
            ],
        };
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
