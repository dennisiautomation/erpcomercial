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
        'folha_ref',
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

    /**
     * Contas de um período, pelo regime escolhido (DRE, relatório por centro de custo):
     *  - `pagamento`: pagas, pela data em que foram pagas (caixa);
     *  - `competencia`: pagas ou não, pelo MÊS a que a despesa pertence; conta sem competência
     *    informada entra pelo vencimento;
     *  - `vencimento`: pagas ou não, pela data de vencimento.
     */
    public function scopeDoPeriodo($query, string $regime, $inicio, $fim)
    {
        $inicio = \Carbon\Carbon::parse($inicio)->toDateString();
        $fim = \Carbon\Carbon::parse($fim)->toDateString();

        if ($regime === 'competencia') {
            $mesInicio = \Carbon\Carbon::parse($inicio)->startOfMonth()->toDateString();

            return $query->whereIn('status', ['pendente', 'paga', 'vencida'])
                ->where(function ($q) use ($inicio, $fim, $mesInicio) {
                    $q->where(fn ($c) => $c->whereNotNull('competencia')
                        ->whereDate('competencia', '>=', $mesInicio)->whereDate('competencia', '<=', $fim))
                        ->orWhere(fn ($v) => $v->whereNull('competencia')
                            ->whereDate('vencimento', '>=', $inicio)->whereDate('vencimento', '<=', $fim));
                });
        }

        if ($regime === 'vencimento') {
            return $query->whereIn('status', ['pendente', 'paga', 'vencida'])
                ->whereDate('vencimento', '>=', $inicio)->whereDate('vencimento', '<=', $fim);
        }

        return $query->where('status', 'paga')
            ->whereDate('pago_em', '>=', $inicio)->whereDate('pago_em', '<=', $fim);
    }

    /** Quanto a conta pesa num relatório: o que foi pago, ou o valor lançado enquanto pendente. */
    public function getValorEfetivoAttribute(): float
    {
        return (float) ($this->status === 'paga' ? $this->valor_pago : $this->valor);
    }

    /**
     * Despesa de pessoal: lançada pela folha, paga a um funcionário, ou classificada num grupo
     * do plano de contas cujo nome começa com "Pessoal" (o plano padrão tem três).
     */
    public function getEhPessoalAttribute(): bool
    {
        if ($this->folha_ref || $this->funcionario_id) {
            return true;
        }

        $grupo = $this->planoConta?->parent?->nome;

        return $grupo !== null && str_starts_with(mb_strtolower($grupo), 'pessoal');
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
