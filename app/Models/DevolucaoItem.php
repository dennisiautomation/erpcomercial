<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DevolucaoItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'devolucao_itens';

    protected $fillable = [
        'devolucao_id',
        'venda_item_id',
        'produto_id',
        'estoque_id',
        'retorna_estoque',
        'condicao',
        'quantidade',
        'valor_unitario',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantidade'      => 'decimal:3',
            'valor_unitario'  => 'decimal:2',
            'total'           => 'decimal:2',
            'retorna_estoque' => 'boolean',
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

    public function estoque(): BelongsTo
    {
        return $this->belongsTo(Estoque::class)->withoutGlobalScopes();
    }
}
