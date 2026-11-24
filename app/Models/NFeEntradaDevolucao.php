<?php

namespace App\Models;

use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Devolução de compra ao fornecedor (24/11/2026) — ver EntradaNFe\DevolucaoCompraService. */
class NFeEntradaDevolucao extends Model
{
    use BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'nfe_entrada_devolucoes';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'nfe_entrada_id',
        'numero',
        'motivo',
        'documento',
        'valor_total',
        'valor_abatido',
        'credito_fornecedor',
        'contas_ajustadas',
        'user_id',
        'estornada_em',
        'estornada_por',
        'estorno_motivo',
    ];

    protected function casts(): array
    {
        return [
            'valor_total'        => 'decimal:2',
            'valor_abatido'      => 'decimal:2',
            'credito_fornecedor' => 'decimal:2',
            'contas_ajustadas'   => 'array',
            'estornada_em'       => 'datetime',
        ];
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(NFeEntrada::class, 'nfe_entrada_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NFeEntradaDevolucaoItem::class, 'devolucao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estornador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estornada_por');
    }

    public function estornada(): bool
    {
        return $this->estornada_em !== null;
    }
}
