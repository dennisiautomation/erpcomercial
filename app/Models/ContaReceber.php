<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaReceber extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes;

    protected $table = 'contas_receber';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
        'venda_id',
        'descricao',
        'valor',
        'valor_pago',
        'vencimento',
        'pago_em',
        'forma_pagamento',
        'parcela',
        'total_parcelas',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
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
        return $this->hasMany(ExtratoBancario::class, 'conta_receber_id');
    }

    public function boletos(): HasMany
    {
        return $this->hasMany(Boleto::class, 'conta_receber_id');
    }
}
