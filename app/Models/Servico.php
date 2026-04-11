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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor_padrao' => 'decimal:2',
            'iss_aliquota' => 'decimal:2',
        ];
    }
}
