<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fatura da PLATAFORMA (IA365 cobra a empresa-cliente diretamente, sem
 * gateway). Sem traits de tenant de propósito: é dado do admin da IA365.
 */
class PlataformaFatura extends Model
{
    protected $table = 'plataforma_faturas';

    protected $fillable = [
        'empresa_id',
        'competencia',
        'descricao',
        'valor',
        'vencimento',
        'status',
        'pago_em',
        'forma_pagamento',
        'observacao',
        'gerada_automaticamente',
        'marcada_por',
    ];

    protected function casts(): array
    {
        return [
            'valor'                  => 'decimal:2',
            'vencimento'             => 'date',
            'pago_em'                => 'date',
            'gerada_automaticamente' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                          */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function marcadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marcada_por');
    }

    /* ------------------------------------------------------------------ */
    /*  Scopes / helpers                                                   */
    /* ------------------------------------------------------------------ */

    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function isVencida(): bool
    {
        return $this->isPendente() && $this->vencimento->lt(now()->startOfDay());
    }

    /** Vencida além da tolerância configurada na empresa → sujeita a bloqueio. */
    public function passouDaTolerancia(): bool
    {
        $tolerancia = (int) ($this->empresa->cobranca_tolerancia_dias ?? 0);

        return $this->isPendente()
            && $this->vencimento->copy()->addDays($tolerancia)->lt(now()->startOfDay());
    }

    public function statusBadge(): array
    {
        if ($this->status === 'paga') {
            return ['label' => 'Paga', 'cor' => 'success'];
        }
        if ($this->status === 'cancelada') {
            return ['label' => 'Cancelada', 'cor' => 'secondary'];
        }

        return $this->isVencida()
            ? ['label' => 'Em atraso', 'cor' => 'danger']
            : ['label' => 'Pendente', 'cor' => 'warning'];
    }
}
