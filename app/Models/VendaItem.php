<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaItem extends Model
{
    use HasFactory;

    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id',
        'produto_id',
        'servico_id',
        'unidade_origem_id',
        'descricao',
        'quantidade',
        'preco_unitario',
        'desconto_percentual',
        'desconto_valor',
        'total',
        // Snapshot fiscal — preserva dados no momento da venda
        'snapshot_ncm',
        'snapshot_cest',
        'snapshot_cfop',
        'snapshot_cst_csosn',
        'snapshot_cst_pis',
        'snapshot_cst_cofins',
        'snapshot_cst_ipi',
        'snapshot_origem',
        'snapshot_icms_aliquota',
        'snapshot_pis_aliquota',
        'snapshot_cofins_aliquota',
        'snapshot_ipi_aliquota',
        'snapshot_unidade_medida',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'total' => 'decimal:2',
            'snapshot_icms_aliquota' => 'decimal:2',
            'snapshot_pis_aliquota' => 'decimal:2',
            'snapshot_cofins_aliquota' => 'decimal:2',
            'snapshot_ipi_aliquota' => 'decimal:2',
        ];
    }

    /**
     * Captura o estado fiscal corrente do Produto neste item (snapshot).
     * Chamar antes de salvar a venda — após isso, edições no Produto
     * não afetam mais o histórico fiscal da venda.
     */
    public function aplicarSnapshotFiscal(?Produto $produto = null): void
    {
        $produto ??= $this->produto;
        if (! $produto) {
            return;
        }
        $this->snapshot_ncm = $produto->ncm;
        $this->snapshot_cest = $produto->cest;
        $this->snapshot_cfop = $produto->cfop;
        $this->snapshot_cst_csosn = $produto->cst_csosn;
        $this->snapshot_cst_pis = $produto->cst_pis;
        $this->snapshot_cst_cofins = $produto->cst_cofins;
        $this->snapshot_cst_ipi = $produto->cst_ipi;
        $this->snapshot_origem = $produto->origem;
        $this->snapshot_icms_aliquota = $produto->icms_aliquota;
        $this->snapshot_pis_aliquota = $produto->pis_aliquota;
        $this->snapshot_cofins_aliquota = $produto->cofins_aliquota;
        $this->snapshot_ipi_aliquota = $produto->ipi_aliquota;
        $this->snapshot_unidade_medida = $produto->unidade_medida;
    }

    /**
     * Retorna o dado fiscal congelado, com fallback no Produto atual.
     * Usar isso em buildPayload em vez de ler direto $produto->...
     */
    public function fiscal(string $campo, mixed $fallback = null): mixed
    {
        $snap = $this->getAttribute("snapshot_{$campo}");
        if ($snap !== null && $snap !== '') {
            return $snap;
        }
        // Campo em branco no produto (string vazia, não null) precisa cair no fallback:
        // com `??` o CFOP vazio ia para o XML como "" e a SEFAZ rejeitava a nota inteira
        // com "Erro na validação do Schema XML".
        $valor = $this->produto?->getAttribute($campo);

        return ($valor === null || $valor === '') ? $fallback : $valor;
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    /** Unidade de onde o produto saiu fisicamente (≠ unidade da venda em vendas remotas). */
    public function unidadeOrigem(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_origem_id');
    }

    /** True se este item foi vendido com estoque de outra unidade. */
    public function isVendaRemota(): bool
    {
        return $this->unidade_origem_id
            && $this->venda
            && $this->unidade_origem_id !== $this->venda->unidade_id;
    }
}
