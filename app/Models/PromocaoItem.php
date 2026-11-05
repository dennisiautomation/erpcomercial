<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromocaoItem extends Model
{
    protected $table = 'promocao_itens';

    protected $fillable = ['promocao_id', 'produto_id', 'tipo', 'valor', 'preco_referencia'];

    protected $casts = [
        'valor' => 'decimal:2',
        'preco_referencia' => 'decimal:2',
    ];

    public function promocao(): BelongsTo
    {
        return $this->belongsTo(Promocao::class, 'promocao_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withoutGlobalScopes();
    }

    /** Preço à vista promocional a partir do preço à vista normal da loja. */
    public function precoPromocional(float $precoAvista): float
    {
        if ($this->tipo === 'percentual') {
            $pct = max(0.0, min(100.0, (float) $this->valor));

            return round($precoAvista * (1 - $pct / 100), 2);
        }

        return round((float) $this->valor, 2);
    }
}
