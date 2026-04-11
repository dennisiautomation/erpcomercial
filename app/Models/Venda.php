<?php

namespace App\Models;

use App\Enums\StatusVenda;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venda extends Model
{
    use HasFactory, SoftDeletes, BelongsToEmpresa, BelongsToUnidade;

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'cliente_id',
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
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusVenda::class,
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
}
