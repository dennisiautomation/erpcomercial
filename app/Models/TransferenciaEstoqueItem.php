<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaEstoqueItem extends Model
{
    protected $table = 'transferencia_itens';

    protected $fillable = [
        'transferencia_estoque_id',
        'produto_id',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(TransferenciaEstoque::class, 'transferencia_estoque_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
