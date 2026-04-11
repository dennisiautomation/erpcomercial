<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdemServico extends Model
{
    use BelongsToEmpresa, BelongsToUnidade, SoftDeletes;

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
        'vendedor_id',
        'tecnico_id',
        'numero',
        'equipamento',
        'defeito_relatado',
        'laudo_tecnico',
        'status',
        'valor_produtos',
        'valor_servicos',
        'desconto',
        'total',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'valor_produtos' => 'decimal:2',
            'valor_servicos' => 'decimal:2',
            'desconto' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(OrdemServicoItem::class);
    }
}
