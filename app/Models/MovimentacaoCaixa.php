<?php

namespace App\Models;

use App\Enums\TipoMovimentacaoCaixa;
use App\Traits\BelongsToEmpresa;
use App\Traits\BelongsToUnidade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoCaixa extends Model
{
    use HasFactory, BelongsToEmpresa, BelongsToUnidade;

    protected $table = 'movimentacoes_caixa';

    protected $fillable = [
        'empresa_id',
        'unidade_id',
        'caixa_id',
        'tipo',
        'valor',
        'forma_pagamento',
        'descricao',
        'user_id',
    ];

    /** Labels pt-BR das formas de pagamento usadas no PDV. */
    public const FORMAS_LABELS = [
        'dinheiro'       => 'Dinheiro',
        'pix'            => 'PIX',
        'cartao_credito' => 'Cartão de Crédito',
        'cartao_debito'  => 'Cartão de Débito',
        'boleto'         => 'Boleto',
        'crediario'      => 'Crediário',
        'transferencia'  => 'Transferência',
        'vale'           => 'Vale',
    ];

    public function formaLabel(): ?string
    {
        if ($this->forma_pagamento === null) {
            return null;
        }

        return self::FORMAS_LABELS[$this->forma_pagamento] ?? ucfirst($this->forma_pagamento);
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacaoCaixa::class,
            'valor' => 'decimal:2',
        ];
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
