<?php

namespace App\Services;

use App\Models\RegraICMS;

class ICMSCalculator
{
    public static function calcular(string $ufOrigem, string $ufDestino, float $valorProduto, ?float $mvaCustom = null): array
    {
        $regra = RegraICMS::where('uf_origem', strtoupper($ufOrigem))
            ->where('uf_destino', strtoupper($ufDestino))
            ->first();

        if (!$regra || !$regra->tem_st) {
            return [
                'tem_st'       => false,
                'icms_proprio' => 0,
                'base_st'      => 0,
                'icms_st'      => 0,
                'fcp'          => 0,
                'total'        => 0,
            ];
        }

        $mva = $mvaCustom ?? $regra->mva;

        // ICMS próprio (interestadual)
        $icmsProprio = $valorProduto * ($regra->aliquota_interestadual / 100);

        // Base de cálculo ST = valor * (1 + MVA/100)
        $baseST = $valorProduto * (1 + $mva / 100);

        // ICMS ST = (Base ST * alíquota interna destino) - ICMS próprio
        $icmsST = ($baseST * ($regra->aliquota_interna / 100)) - $icmsProprio;
        if ($icmsST < 0) {
            $icmsST = 0;
        }

        // FCP
        $fcp = $baseST * ($regra->fcp / 100);

        return [
            'tem_st'                 => true,
            'uf_origem'              => strtoupper($ufOrigem),
            'uf_destino'             => strtoupper($ufDestino),
            'aliquota_interestadual' => (float) $regra->aliquota_interestadual,
            'aliquota_interna'       => (float) $regra->aliquota_interna,
            'mva'                    => $mva,
            'icms_proprio'           => round($icmsProprio, 2),
            'base_st'                => round($baseST, 2),
            'icms_st'                => round($icmsST, 2),
            'fcp'                    => round($fcp, 2),
            'total'                  => round($icmsST + $fcp, 2),
        ];
    }

    public static function tabelaPorEstado(string $ufOrigem, float $valorProduto = 100): array
    {
        $ufs = [
            'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
            'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
            'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
        ];

        $tabela = [];
        foreach ($ufs as $uf) {
            if ($uf === strtoupper($ufOrigem)) {
                continue;
            }
            $tabela[$uf] = self::calcular($ufOrigem, $uf, $valorProduto);
        }

        return $tabela;
    }
}
