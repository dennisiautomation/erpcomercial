<?php

namespace App\Models;

use App\Enums\StatusCaixa;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caixa extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'user_id',
        'numero_caixa',
        'valor_abertura',
        'valor_fechamento',
        'valor_esperado',
        'status',
        'aberto_em',
        'fechado_em',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusCaixa::class,
            'valor_abertura' => 'decimal:2',
            'valor_fechamento' => 'decimal:2',
            'valor_esperado' => 'decimal:2',
            'aberto_em' => 'datetime',
            'fechado_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoCaixa::class);
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class);
    }
}
