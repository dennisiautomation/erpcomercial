<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unidade extends Model
{
    use SoftDeletes, BelongsToEmpresa;

    protected $fillable = [
        'empresa_id',
        'nome',
        'cnpj',
        'ie',
        'im',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'telefone',
        'gerente_id',
        'logo',
        'status',
    ];

    /**
     * Toda loja nasce com um estoque "Principal".
     *
     * Sem isso, loja criada depois de 12/08 ficaria sem estoque nenhum e o PDV
     * não teria de onde baixar (`SaldoEstoque::estoqueDeVendaId` devolveria
     * null). Fica no model de propósito: pega o admin, o Minhas Lojas, o
     * seeder e qualquer caminho futuro de uma vez só.
     */
    protected static function booted(): void
    {
        static::created(function (self $unidade) {
            Estoque::withoutGlobalScopes()->firstOrCreate(
                ['unidade_id' => $unidade->id, 'nome' => 'Principal'],
                [
                    'empresa_id'    => $unidade->empresa_id,
                    'codigo'        => 'PRINCIPAL',
                    'permite_venda' => true,
                    'is_padrao'     => true,
                    'status'        => 'ativo',
                ]
            );
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function gerente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerente_id');
    }

    public function caixas(): HasMany
    {
        return $this->hasMany(Caixa::class);
    }

    public function estoques(): HasMany
    {
        return $this->hasMany(Estoque::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }

    public function configuracaoFiscal(): HasOne
    {
        return $this->hasOne(ConfiguracaoFiscal::class);
    }
}
