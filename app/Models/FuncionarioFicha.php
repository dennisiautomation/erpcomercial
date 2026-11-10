<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Salário e vale de um funcionário — o que a folha lança todo mês no Contas a Pagar. */
class FuncionarioFicha extends Model
{
    use BelongsToEmpresa;

    protected $table = 'funcionario_fichas';

    protected $fillable = [
        'empresa_id', 'user_id', 'cargo', 'admissao', 'demissao', 'salario', 'dia_pagamento',
        'paga_mes_seguinte', 'vale_valor', 'vale_dia', 'unidade_id', 'centro_custo_id',
        'na_folha', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'admissao' => 'date',
            'demissao' => 'date',
            'salario' => 'decimal:2',
            'vale_valor' => 'decimal:2',
            'paga_mes_seguinte' => 'boolean',
            'na_folha' => 'boolean',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    /** O que sobra para pagar no dia do salário, depois do vale. */
    public function getSaldoSalarioAttribute(): float
    {
        return max(0, round((float) $this->salario - (float) $this->vale_valor, 2));
    }
}
