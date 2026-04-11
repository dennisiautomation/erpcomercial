<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transportadora extends Model
{
    use SoftDeletes, BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'cnpj',
        'razao_social',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'contato',
        'modalidade_frete',
    ];
}
