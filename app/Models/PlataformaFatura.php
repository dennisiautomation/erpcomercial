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
        'origem',
        'caixa_ref',
        'competencia',
        'descricao',
        'valor',
        'vencimento',
        'status',
        'pago_em',
        'forma_pagamento',
        'observacao',
        'link',
        'pix_copia_cola',
        'linha_digitavel',
        'tem_nota',
        'avisos',
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
            'tem_nota'               => 'boolean',
            'avisos'                 => 'array',
            'cartao_gerado_em'       => 'datetime',
            'cartao_pago_em'         => 'datetime',
            'caixa_baixa_pendente'   => 'boolean',
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

    /** Veio do caixa.ia365 (lá nasce a cobrança e lá se dá a baixa). */
    public function doCaixa(): bool
    {
        return $this->origem === 'caixa';
    }

    public function titulo(): string
    {
        return $this->descricao ?: 'Fatura ' . $this->competencia;
    }

    /** Dias até o vencimento (negativo = em atraso). */
    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->vencimento, false);
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
