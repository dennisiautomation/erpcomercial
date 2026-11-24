<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NFeEntradaDevolucaoItem extends Model
{
    protected $table = 'nfe_entrada_devolucao_itens';

    protected $fillable = [
        'devolucao_id',
        'nfe_entrada_item_id',
        'produto_id',
        'quantidade_nota',
        'quantidade_estoque',
        'valor',
        'estoque_movimentacao_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_nota'    => 'decimal:4',
            'quantidade_estoque' => 'decimal:3',
            'valor'              => 'decimal:2',
        ];
    }

    public function devolucao(): BelongsTo
    {
        return $this->belongsTo(NFeEntradaDevolucao::class, 'devolucao_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(NFeEntradaItem::class, 'nfe_entrada_item_id');
    }
}
