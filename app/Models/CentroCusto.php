<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentroCusto extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'centros_custo';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nome',
        'descricao',
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

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class, 'centro_custo_id');
    }

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class, 'centro_custo_id');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
