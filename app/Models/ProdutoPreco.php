<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoPreco extends Model
{
    use BelongsToEmpresa;

    protected $table = 'produto_precos';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'modalidade',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
