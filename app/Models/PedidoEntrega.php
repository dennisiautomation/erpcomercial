<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrega de um pedido via provedor externo (hoje: Uber Direct).
 * SEM BelongsToEmpresa — acessado por job/webhook fora de sessão; o
 * isolamento vem do pedido (que carrega empresa_id).
 */
class PedidoEntrega extends Model
{
    protected $table = 'pedido_entregas';

    protected $fillable = [
        'pedido_id',
        'provedor',
        'quote_id',
        'delivery_id',
        'status',
        'tracking_url',
        'valor',
        'courier',
        'erro',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'courier' => 'array',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
