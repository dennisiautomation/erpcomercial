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
        'venda_id',
        'tipo',
        'valor',
        'forma_pagamento',
        'forma_chave',   // botão configurado que gerou (14/11/2026)
        'forma_nome',
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
        'credishop'      => 'Credishop',
    ];

    public function formaLabel(): ?string
    {
        if ($this->forma_pagamento === null) {
            return null;
        }
        // Forma criada pelo lojista (14/11/2026): o nome que estava no botão
        if (! empty($this->forma_nome) && str_starts_with((string) $this->forma_chave, 'fp_')) {
            return $this->forma_nome;
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

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
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
