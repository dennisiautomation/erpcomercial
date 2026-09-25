<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link de pagamento (cartão) de um pedido do Agente IA — Asaas ou InfinitePay
 * (25/09/2026).
 *
 * SEM BelongsToEmpresa (acesso máquina-a-máquina/webhook/cron); todo acesso
 * filtra empresa_id explicitamente, igual ao PedidoCobranca do PIX.
 */
class PedidoLinkCartao extends Model
{
    protected $table = 'pedido_links_cartao';

    public const STATUS_ATIVO = 'ativo';
    public const STATUS_PAGO = 'pago';
    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'empresa_id',
        'pedido_id',
        'provedor',
        'referencia',
        'link',
        'slug',
        'valor',
        'status',
        'transaction_nsu',
        'capture_method',
        'parcelas',
        'valor_pago',
        'receipt_url',
        'pago_em',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'parcelas' => 'integer',
            'pago_em' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function pago(): bool
    {
        return $this->status === self::STATUS_PAGO;
    }
}
