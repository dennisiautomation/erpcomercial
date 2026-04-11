<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegraICMS extends Model
{
    protected $table = 'regras_icms';

    protected $fillable = [
        'uf_origem',
        'uf_destino',
        'aliquota_interna',
        'aliquota_interestadual',
        'mva',
        'fcp',
        'tem_st',
    ];

    protected $casts = [
        'aliquota_interna'       => 'decimal:2',
        'aliquota_interestadual' => 'decimal:2',
        'mva'                    => 'decimal:2',
        'fcp'                    => 'decimal:2',
        'tem_st'                 => 'boolean',
    ];

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeForOrigem($query, string $uf)
    {
        return $query->where('uf_origem', strtoupper($uf));
    }

    public function scopeForDestino($query, string $uf)
    {
        return $query->where('uf_destino', strtoupper($uf));
    }

    /* ------------------------------------------------------------------ */
    /*  Static helper                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Calcula ICMS-ST para uma operação interestadual.
     *
     * @return array{icms_proprio: float, base_st: float, icms_st: float, total_st: float, fcp: float}
     */
    public static function calcularST(
        string $ufOrigem,
        string $ufDestino,
        float $valorProduto,
        ?float $mvaOverride = null,
    ): array {
        $result = \App\Services\ICMSCalculator::calcular($ufOrigem, $ufDestino, $valorProduto, $mvaOverride);

        return [
            'icms_proprio' => $result['icms_proprio'],
            'base_st'      => $result['base_st'],
            'icms_st'      => $result['icms_st'],
            'total_st'     => $result['total'],
            'fcp'          => $result['fcp'],
        ];
    }
}
