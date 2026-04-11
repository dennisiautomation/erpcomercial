<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaPagar extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'contas_pagar';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'fornecedor_id',
        'descricao',
        'valor',
        'valor_pago',
        'vencimento',
        'pago_em',
        'categoria',
        'centro_custo',
        'forma_pagamento',
        'parcela',
        'total_parcelas',
        'recorrente',
        'recorrencia_tipo',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'vencimento' => 'date',
            'pago_em' => 'date',
            'recorrente' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    public function extratos(): HasMany
    {
        return $this->hasMany(ExtratoBancario::class, 'conta_pagar_id');
    }
}
