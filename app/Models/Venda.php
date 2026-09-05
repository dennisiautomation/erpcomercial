<?php

namespace App\Models;

use App\Enums\CanalVenda;
use App\Enums\StatusVenda;
use App\Traits\AuditableModel;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venda extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade, AuditableModel;

    protected $auditFields = ['status', 'total', 'cliente_id', 'cancelamento_motivo', 'canceled_at'];

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
        'cpf_cnpj_nota',
        'vendedor_id',
        'caixa_id',
        'pedido_id',
        'numero',
        'subtotal',
        'desconto_percentual',
        'desconto_valor',
        'total',
        'forma_pagamento',
        'pagamento_detalhes',
        'troco',
        'status',
        'tipo',
        'canal',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusVenda::class,
            'canal' => CanalVenda::class,
            'pagamento_detalhes' => 'array',
            'subtotal' => 'decimal:2',
            'desconto_valor' => 'decimal:2',
            'total' => 'decimal:2',
            'troco' => 'decimal:2',
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

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function devolucoes(): HasMany
    {
        return $this->hasMany(Devolucao::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class);
    }

    public function valeUsos(): HasMany
    {
        return $this->hasMany(ValeUso::class);
    }

    /** Valor já devolvido/trocado desta venda (devoluções não canceladas). */
    public function getTotalDevolvidoAttribute(): float
    {
        return round((float) $this->devolucoes
            ->where('status', '!=', 'cancelada')
            ->sum('valor_estornado'), 2);
    }

    /* ------------------------------------------------------------------ */
    /*  Acessores                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Juros de parcelamento cobrado do cliente, somado das formas de pagamento.
     *
     * Não é coluna: sai do JSON `pagamento_detalhes`, onde o PDV grava
     * `juros_valor` por pagamento. É o vOutro (outras despesas acessórias) da
     * nota — o `FiscalPayloadBuilder` lê este acessor para fechar a conta
     * `total = produtos − desconto + outras despesas` que a SEFAZ valida.
     */
    public function getOutrasDespesasAttribute(): float
    {
        if (! is_array($this->pagamento_detalhes)) {
            return 0.0;
        }

        // Dois acréscimos moram aqui: o juros do parcelamento (02/09) e o
        // acréscimo do cartão cobrado por parte (04/09, regra `por_parte`).
        // Os dois seguem o mesmo caminho — vOutro na NFC-e, linha no cupom —
        // e podem coexistir na mesma venda parcelada.
        return round(array_sum(array_map(
            fn ($pg) => (float) ($pg['juros_valor'] ?? 0)
                      + (float) ($pg['acrescimo_forma_valor'] ?? 0),
            $this->pagamento_detalhes
        )), 2);
    }
}
