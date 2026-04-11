<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucaoItem extends Model
{
    use HasFactory;

    protected $table = 'devolucao_itens';

    protected $fillable = [
        'devolucao_id',
        'venda_item_id',
        'produto_id',
        'quantidade',
        'valor_unitario',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(Devolucao::class);
    }

    public function vendaItem(): BelongsTo
    {
        return $this->belongsTo(VendaItem::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
