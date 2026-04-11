<?php

namespace Database\Seeders;

use App\Models\RegraICMS;
use Illuminate\Database\Seeder;

class RegraICMSSeeder extends Seeder
{
    /**
     * Alíquotas internas de ICMS por estado (simplificado).
     */
    private const ALIQUOTAS_INTERNAS = [
        'AC' => 19,   'AL' => 19,   'AM' => 20,   'AP' => 18,
        'BA' => 20.5, 'CE' => 20,   'DF' => 18,   'ES' => 17,
        'GO' => 19,   'MA' => 22,   'MG' => 18,   'MS' => 17,
        'MT' => 17,   'PA' => 19,   'PB' => 20,   'PE' => 20.5,
        'PI' => 21,   'PR' => 19.5, 'RJ' => 20,   'RN' => 20,
        'RO' => 19.5, 'RR' => 20,   'RS' => 17.5, 'SC' => 17,
        'SE' => 19,   'SP' => 18,   'TO' => 20,
    ];

    /**
     * FCP por estado (simplificado).
     */
    private const FCP = [
        'RJ' => 2,
        'MG' => 1,
        'MT' => 1,
    ];

    /**
     * Estados do Sul/Sudeste (exceto ES para regra da alíquota interestadual).
     */
    private const SUL_SUDESTE = ['SP', 'RJ', 'MG', 'PR', 'SC', 'RS'];

    /**
     * Estados de origem que serão semeados (principais corredores).
     */
    private const ORIGENS = ['SP', 'RJ', 'MG', 'PR'];

    /**
     * MVA padrão (%).
     */
    private const MVA_PADRAO = 40;

    public function run(): void
    {
        $allUfs = array_keys(self::ALIQUOTAS_INTERNAS);
        $rows = [];
        $now = now();

        foreach (self::ORIGENS as $origem) {
            foreach ($allUfs as $destino) {
                if ($origem === $destino) {
                    continue;
                }

                $rows[] = [
                    'uf_origem'              => $origem,
                    'uf_destino'             => $destino,
                    'aliquota_interna'       => self::ALIQUOTAS_INTERNAS[$destino],
                    'aliquota_interestadual' => $this->aliquotaInterestadual($origem, $destino),
                    'mva'                    => self::MVA_PADRAO,
                    'fcp'                    => self::FCP[$destino] ?? 0,
                    'tem_st'                 => true,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }
        }

        // Usar upsert para evitar duplicatas em re-seed
        RegraICMS::upsert($rows, ['uf_origem', 'uf_destino'], [
            'aliquota_interna',
            'aliquota_interestadual',
            'mva',
            'fcp',
            'tem_st',
            'updated_at',
        ]);
    }

    /**
     * Determina a alíquota interestadual conforme as regras brasileiras:
     *
     * - Sul/Sudeste → Norte/Nordeste/Centro-Oeste = 7%
     * - Norte/Nordeste/Centro-Oeste → qualquer = 12%
     * - Sul/Sudeste → Sul/Sudeste = 12%
     * - Qualquer → ES = 12% (exceção)
     */
    private function aliquotaInterestadual(string $origem, string $destino): float
    {
        // ES sempre recebe 12% (exceção)
        if ($destino === 'ES') {
            return 12;
        }

        $origemSulSudeste = in_array($origem, self::SUL_SUDESTE, true) || $origem === 'ES';
        $destinoSulSudeste = in_array($destino, self::SUL_SUDESTE, true);

        // Sul/Sudeste → Norte/Nordeste/Centro-Oeste = 7%
        if ($origemSulSudeste && !$destinoSulSudeste) {
            return 7;
        }

        // Todos os demais casos = 12%
        return 12;
    }
}
