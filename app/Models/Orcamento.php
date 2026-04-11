<?php

namespace App\Models;

use App\Enums\StatusOrcamento;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orcamento extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
        'vendedor_id',
        'numero',
        'validade_ate',
        'subtotal',
        'desconto_percentual',
        'desconto_valor',
        'total',
        'status',
        'observacoes_internas',
        'observacoes_externas',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusOrcamento::class,
            'validade_ate' => 'date',
            'subtotal' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrcamentoItem::class);
    }

    public function pedido(): HasOne
    {
        return $this->hasOne(Pedido::class);
    }
}
