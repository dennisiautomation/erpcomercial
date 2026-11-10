<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContaPagar extends Model
{
    // BelongsToUnidade: contas a pagar por loja, igual às contas a receber.
    // Perfis restritos veem só a loja ativa; dono/admin veem todas.
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes;

    protected $table = 'contas_pagar';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'fornecedor_id',
        'funcionario_id',
        'favorecido_nome',
        'descricao',
        'valor',
        'valor_pago',
        'vencimento',
        'competencia',
        'pago_em',
        'categoria',
        'centro_custo',
        'plano_conta_id',
        'centro_custo_id',
        'forma_pagamento',
        'parcela',
        'total_parcelas',
        'serie_id',
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
            'competencia' => 'date',
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

    /** Funcionário favorecido (salário, vale, benefício) — é um usuário da empresa. */
    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    /** Quem recebe: fornecedor, funcionário ou o nome livre (INSS, FGTS, Receita...). */
    public function getFavorecidoLabelAttribute(): string
    {
        return $this->fornecedor?->razao_social
            ?? $this->funcionario?->name
            ?? ($this->favorecido_nome ?: '-');
    }

    public function getFavorecidoTipoAttribute(): string
    {
        if ($this->funcionario_id) {
            return 'funcionario';
        }

        return $this->fornecedor_id ? 'fornecedor' : 'outro';
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
