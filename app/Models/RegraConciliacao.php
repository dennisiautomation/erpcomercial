<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O que o sistema aprendeu: para este favorecido (ou este texto do banco), a despesa é desta conta e deste centro de custo. */
class RegraConciliacao extends Model
{
    use BelongsToEmpresa;

    protected $table = 'regras_conciliacao';

    protected $fillable = [
        'empresa_id', 'chave_tipo', 'chave', 'fornecedor_id', 'favorecido_nome', 'descricao_padrao',
        'plano_conta_id', 'centro_custo_id', 'usos', 'ultima_vez_em',
    ];

    protected function casts(): array
    {
        return ['ultima_vez_em' => 'datetime'];
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoContas::class, 'plano_conta_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
