<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cupom de desconto da loja online (14/11/2026). Sem BelongsToEmpresa: a vitrine
 * pública valida sem sessão do ERP — todo acesso filtra empresa_id explicitamente.
 */
class LojaCupom extends Model
{
    protected $table = 'loja_cupons';

    public const TIPOS = ['percentual' => '% de desconto', 'valor' => 'R$ de desconto', 'frete_gratis' => 'Frete grátis'];

    protected $fillable = ['empresa_id', 'codigo', 'tipo', 'valor', 'minimo', 'inicio', 'fim', 'limite_usos', 'usos', 'ativo'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'minimo' => 'decimal:2', 'inicio' => 'date', 'fim' => 'date', 'ativo' => 'boolean'];
    }

    /** Situação hoje, para a lista do lojista. */
    public function situacao(): string
    {
        return match (true) {
            ! $this->ativo => 'Desligado',
            $this->inicio && $this->inicio->isFuture() => 'Começa em ' . $this->inicio->format('d/m'),
            $this->fim && $this->fim->endOfDay()->isPast() => 'Vencido',
            $this->limite_usos !== null && $this->usos >= $this->limite_usos => 'Esgotado',
            default => 'Valendo',
        };
    }

    public function descricao(): string
    {
        return match ($this->tipo) {
            'percentual' => rtrim(rtrim(number_format((float) $this->valor, 2, ',', '.'), '0'), ',') . '% de desconto',
            'valor' => 'R$ ' . number_format((float) $this->valor, 2, ',', '.') . ' de desconto',
            default => 'Frete grátis',
        };
    }
}
