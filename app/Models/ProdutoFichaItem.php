<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma linha da ficha técnica: quanto do insumo sai por UMA unidade do produto. */
class ProdutoFichaItem extends Model
{
    use BelongsToEmpresa;

    protected $table = 'produto_ficha_itens';

    protected $fillable = ['empresa_id', 'produto_id', 'insumo_id', 'quantidade'];

    protected function casts(): array
    {
        return ['quantidade' => 'decimal:4'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'insumo_id');
    }
}
