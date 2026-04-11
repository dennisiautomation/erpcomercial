<?php

namespace App\Models;

use App\Enums\TipoMovimentacaoEstoque;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EstoqueMovimentacao extends Model
{
    use BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'estoque_movimentacoes';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'produto_id',
        'tipo',
        'quantidade',
        'quantidade_anterior',
        'quantidade_posterior',
        'custo_unitario',
        'origem_tipo',
        'origem_id',
        'user_id',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacaoEstoque::class,
            'quantidade' => 'decimal:3',
            'quantidade_anterior' => 'decimal:3',
            'quantidade_posterior' => 'decimal:3',
            'custo_unitario' => 'decimal:2',
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

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }
}
