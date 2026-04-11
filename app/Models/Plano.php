<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'preco_mensal',
        'preco_anual',
        'max_unidades',
        'max_usuarios',
        'max_produtos',
        'max_notas_mes',
        'pdv_habilitado',
        'fiscal_habilitado',
        'multilojas_habilitado',
        'os_habilitado',
        'contratos_habilitado',
        'conciliacao_habilitada',
        'dre_habilitado',
        'boletos_habilitado',
        'api_habilitada',
        'dias_trial',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'preco_mensal'           => 'decimal:2',
            'preco_anual'            => 'decimal:2',
            'pdv_habilitado'         => 'boolean',
            'fiscal_habilitado'      => 'boolean',
            'multilojas_habilitado'  => 'boolean',
            'os_habilitado'          => 'boolean',
            'contratos_habilitado'   => 'boolean',
            'conciliacao_habilitada' => 'boolean',
            'dre_habilitado'         => 'boolean',
            'boletos_habilitado'     => 'boolean',
            'api_habilitada'         => 'boolean',
            'ativo'                  => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'plano_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Check if a feature is enabled on this plan.
     *
     * Accepted feature keys: pdv, fiscal, multilojas, os, contratos,
     * conciliacao, dre, boletos, api.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $map = [
            'pdv'         => 'pdv_habilitado',
            'fiscal'      => 'fiscal_habilitado',
            'multilojas'  => 'multilojas_habilitado',
            'os'          => 'os_habilitado',
            'contratos'   => 'contratos_habilitado',
            'conciliacao' => 'conciliacao_habilitada',
            'dre'         => 'dre_habilitado',
            'boletos'     => 'boletos_habilitado',
            'api'         => 'api_habilitada',
        ];

        $column = $map[$feature] ?? null;

        return $column ? (bool) $this->{$column} : false;
    }

    /**
     * Get a numeric limit for a resource.
     *
     * Accepted resource keys: unidades, usuarios, produtos, notas.
     */
    public function getLimit(string $resource): int
    {
        $map = [
            'unidades' => 'max_unidades',
            'usuarios' => 'max_usuarios',
            'produtos' => 'max_produtos',
            'notas'    => 'max_notas_mes',
        ];

        $column = $map[$resource] ?? null;

        return $column ? (int) $this->{$column} : 0;
    }
}
