<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanoContas extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'plano_contas';

    protected $fillable = [
        'empresa_id',
        'parent_id',
        'codigo',
        'nome',
        'tipo',
        'natureza',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PlanoContas::class, 'parent_id');
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'plano_conta_id');
    }

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class, 'plano_conta_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeAnaliticas($query)
    {
        return $query->where('natureza', 'analitica');
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }
}
