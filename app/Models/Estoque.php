<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um local de estoque dentro de uma loja (salão, depósito, avaria, vitrine).
 *
 * Toda unidade tem pelo menos um, criado na migration como "Principal".
 * Exatamente um por unidade tem `permite_venda` — é dele que o PDV baixa.
 *
 * NÃO usa BelongsToUnidade: o dono/gerente precisa enxergar os estoques de
 * outras lojas para transferir entre elas.
 */
class Estoque extends Model
{
    use AuditableModel, BelongsToEmpresa;

    protected $auditFields = ['nome', 'codigo', 'permite_venda', 'is_padrao', 'status'];

    protected $table = 'estoques';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'nome',
        'codigo',
        'permite_venda',
        'is_padrao',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'permite_venda' => 'boolean',
            'is_padrao' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(EstoqueMovimentacao::class);
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes                                                             */
    /* ------------------------------------------------------------------ */

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('status', 'ativo');
    }

    public function scopeDaUnidade(Builder $query, int $unidadeId): Builder
    {
        return $query->where('unidade_id', $unidadeId);
    }
}
