<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes, BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'tipo_pessoa',
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
        'whatsapp',
        'email',
        'limite_credito',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'limite_credito' => 'decimal:2',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function orcamentos(): HasMany
    {
        return $this->hasMany(Orcamento::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class);
    }
}
