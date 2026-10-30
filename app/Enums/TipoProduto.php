<?php

namespace App\Enums;

/**
 * Tipo do produto (Fase 1 cafeteria, 30/10/2026). Só aparece no cadastro da
 * empresa que liga "Usa ficha técnica"; para as outras todo produto é Revenda.
 */
enum TipoProduto: string
{
    /** Compra e vende: baixa o próprio produto (o de sempre). */
    case Revenda = 'revenda';
    /** Feito na casa: a venda baixa os insumos da ficha técnica. */
    case Produzido = 'produzido';
    /** Matéria-prima: entra por compra, sai pelas fichas; não vai ao PDV. */
    case Insumo = 'insumo';
    /** Opção de outro produto (shot extra, leite vegetal): só dentro de um grupo de opções. */
    case Adicional = 'adicional';

    public function label(): string
    {
        return match ($this) {
            self::Revenda => 'Revenda',
            self::Produzido => 'Produzido (ficha técnica)',
            self::Insumo => 'Insumo',
            self::Adicional => 'Adicional',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Revenda => 'Compro e vendo pronto (água, refrigerante). O estoque baixa o próprio produto.',
            self::Produzido => 'Feito aqui (cappuccino, misto quente). A venda baixa os insumos da receita.',
            self::Insumo => 'Matéria-prima (café em grão, leite, copo). Não aparece no PDV.',
            self::Adicional => 'Opção de outro produto (shot extra, leite de aveia). Escolhido na hora da venda.',
        };
    }

    /** Vai para a busca e os atalhos do PDV como item próprio? */
    public function vendeNoPdv(): bool
    {
        return $this === self::Revenda || $this === self::Produzido;
    }

    /** Tem saldo próprio no estoque? Produzido não: quem tem saldo são os insumos. */
    public function controlaEstoqueProprio(): bool
    {
        return $this !== self::Produzido;
    }

    /** Valores que vão ao PDV, à busca do agente e aos atalhos. */
    public static function vendaveis(): array
    {
        return [self::Revenda->value, self::Produzido->value];
    }

    public static function de(?string $valor): self
    {
        return self::tryFrom((string) $valor) ?? self::Revenda;
    }
}
