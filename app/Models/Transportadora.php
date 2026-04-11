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
        'cpf_cnpj',
        'nome_razao_social',
        'nome_fantasia',
        'ie',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'telefone',
        'email',
        'status',
        'observacoes',
    ];
}
