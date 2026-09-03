<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cada abatimento de um vale: numa venda do PDV ou devolvido em dinheiro. */
class ValeUso extends Model
{
    protected $table = 'vale_usos';

    protected $fillable = ['vale_id', 'venda_id', 'user_id', 'tipo', 'valor'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2'];
    }

    public function vale(): BelongsTo
    {
        return $this->belongsTo(Vale::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class)->withoutGlobalScopes();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
