<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes, BelongsToEmpresa, AuditableModel;

    protected $fillable = [
        'empresa_id',
        'codigo_interno',
        'codigo_barras',
        'sku',
        'descricao',
        'descricao_detalhada',
        'unidade_medida',
        'categoria_id',
        'ncm',
        'cest',
        'origem',
        'preco_custo',
        'markup',
        'preco_venda',
        'estoque_minimo',
        'foto',
        'peso_bruto',
        'peso_liquido',
        'cfop',
        'cst_csosn',
        'icms_aliquota',
        'pis_aliquota',
        'cofins_aliquota',
        'ipi_aliquota',
        // Reforma Tributária (EC 132/2023)
        'ibs_aliquota',
        'cbs_aliquota',
        'is_aliquota',
        'cst_ibs_cbs',
        'classificacao_ibs',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preco_custo' => 'decimal:2',
            'preco_venda' => 'decimal:2',
            'markup' => 'decimal:2',
            'icms_aliquota' => 'decimal:2',
            'pis_aliquota' => 'decimal:2',
            'cofins_aliquota' => 'decimal:2',
            'ipi_aliquota' => 'decimal:2',
            'ibs_aliquota' => 'decimal:4',
            'cbs_aliquota' => 'decimal:4',
            'is_aliquota' => 'decimal:4',
            'peso_bruto' => 'decimal:3',
            'peso_liquido' => 'decimal:3',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relationships                                                      */
    /* ------------------------------------------------------------------ */

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function vendaItens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function estoqueMovimentacoes(): HasMany
    {
        return $this->hasMany(EstoqueMovimentacao::class);
    }
}
