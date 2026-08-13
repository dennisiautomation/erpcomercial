<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobrança PIX de um pedido do Agente IA.
 *
 * SEM BelongsToEmpresa (acesso máquina-a-máquina/webhook); todo acesso
 * filtra empresa_id explicitamente. O txid identifica a cobrança no
 * PSP e no webhook.
 */
class PedidoCobranca extends Model
{
    protected $table = 'pedido_cobrancas';

    protected $fillable = [
        'empresa_id',
        'pedido_id',
        'provedor',
        'txid',
        'valor',
        'chave',
        'status',
        'copia_cola',
        'location',
        'e2eid',
        'expira_em',
        'pago_em',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'expira_em' => 'datetime',
            'pago_em' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function paga(): bool
    {
        return $this->pago_em !== null;
    }

    /** Ainda serve para o cliente pagar (ativa, com copia-e-cola, não vencida)? */
    public function reutilizavel(): bool
    {
        return $this->status === 'ATIVA'
            && filled($this->copia_cola)
            && $this->expira_em?->isFuture() === true;
    }
}
