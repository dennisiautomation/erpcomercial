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
        'codigo',
        'descricao',
        'valor_padrao',
        'codigo_servico_municipal',
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
