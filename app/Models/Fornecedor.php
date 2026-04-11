<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use SoftDeletes, BelongsToEmpresa;

    protected $table = 'fornecedores';

    protected $fillable = [
        'empresa_id',
        'cpf_cnpj',
        'razao_social',
        'nome_fantasia',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'contato_representante',
        'telefone',
        'email',
        'condicoes_comerciais',
    ];

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class);
    }
}
