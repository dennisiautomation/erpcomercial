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
        // Declaração de Importação (NT 2015/003)
        'di_numero',
        'di_data',
        'di_local_desembaraco',
        'di_uf_desembaraco',
        'di_data_desembaraco',
        'di_via_transp',
        'di_valor_afrmm',
        'di_forma_importacao',
        'di_adicao_numero',
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
            'di_data' => 'date',
            'di_data_desembaraco' => 'date',
            'di_valor_afrmm' => 'decimal:2',
            'peso_bruto' => 'decimal:3',
            'peso_liquido' => 'decimal:3',
        ];
    }

    /**
     * True quando o produto é importado (origem 1, 2, 3, 6, 7, 8) e precisa dos campos de DI.
     */
    public function ehImportado(): bool
    {
        return in_array((string) $this->origem, ['1', '2', '3', '6', '7', '8'], true);
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
