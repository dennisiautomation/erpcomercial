<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item da NF-e de compra + o que o lojista decidiu na conferência.
 * Sem trait de empresa: só é lido pela entrada, que já é escopada.
 */
class NFeEntradaItem extends Model
{
    protected $table = 'nfe_entrada_itens';

    protected $fillable = [
        'nfe_entrada_id',
        'numero_item',
        'codigo_fornecedor',
        'ean',
        'descricao',
        'ncm',
        'cfop',
        'unidade_comercial',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'custo_total',
        'acao',
        'produto_id',
        'fator_conversao',
        'estoque_movimentacao_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade'      => 'decimal:4',
            'valor_unitario'  => 'decimal:6',
            'valor_total'     => 'decimal:2',
            'custo_total'     => 'decimal:2',
            'fator_conversao' => 'decimal:4',
        ];
    }

    /** Quantidade que entra no estoque, já na unidade do produto. */
    public function quantidadeEntrada(): float
    {
        return round((float) $this->quantidade * (float) $this->fator_conversao, 3);
    }

    /** Custo de UMA unidade do produto (frete/IPI/ST rateados, desconto abatido). */
    public function custoUnitarioEntrada(): float
    {
        $qtd = $this->quantidadeEntrada();

        return $qtd > 0 ? round((float) $this->custo_total / $qtd, 2) : 0.0;
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(NFeEntrada::class, 'nfe_entrada_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
