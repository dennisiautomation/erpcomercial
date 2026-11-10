<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guia ou benefício que se repete todo mês com valor DIGITADO (INSS, FGTS, vale-transporte...).
 * `funcionario_id` NULL = da empresa inteira (uma guia só); preenchido = daquele funcionário.
 */
class FolhaItem extends Model
{
    use BelongsToEmpresa, SoftDeletes;

    protected $table = 'folha_itens';

    public const TIPOS = ['encargo' => 'Encargo / imposto', 'beneficio' => 'Benefício', 'outro' => 'Outro'];

    protected $fillable = [
        'empresa_id', 'funcionario_id', 'tipo', 'nome', 'favorecido_nome', 'valor', 'dia_vencimento',
        'vence_mes_seguinte', 'plano_conta_id', 'centro_custo_id', 'unidade_id', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vence_mes_seguinte' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funcionario_id');
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
