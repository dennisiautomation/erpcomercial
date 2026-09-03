<?php

namespace App\Enums;

enum TipoMovimentacaoCaixa: string
{
    case Venda = 'venda';
    case Sangria = 'sangria';
    case Suprimento = 'suprimento';
    case Abertura = 'abertura';
    case Fechamento = 'fechamento';
    // Dinheiro devolvido ao cliente numa troca/devolução (03/09/2026).
    // Sai da gaveta como a sangria, mas com nome próprio: no fechamento o
    // caixa precisa saber o que foi devolução e o que foi retirada.
    case Devolucao = 'devolucao';

    public function label(): string
    {
        return match ($this) {
            self::Venda => 'Venda',
            self::Sangria => 'Sangria',
            self::Suprimento => 'Suprimento',
            self::Abertura => 'Abertura',
            self::Fechamento => 'Fechamento',
            self::Devolucao => 'Devolução',
        };
    }

    public function sinal(): int
    {
        return match ($this) {
            self::Venda, self::Suprimento, self::Abertura => 1,
            self::Sangria, self::Devolucao => -1,
            self::Fechamento => 0,
        };
    }
}
