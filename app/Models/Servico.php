<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Servico extends Model
{
    use SoftDeletes, BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'codigo_lc116',
        'descricao',
        'valor_padrao',
        'cnae',
        'iss_aliquota',
        // Reforma Tributária (EC 132/2023)
        'ibs_aliquota',
        'cbs_aliquota',
        'cst_ibs_cbs',
        'classificacao_ibs',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor_padrao' => 'decimal:2',
            'iss_aliquota' => 'decimal:2',
            'ibs_aliquota' => 'decimal:4',
            'cbs_aliquota' => 'decimal:4',
        ];
    }
}
