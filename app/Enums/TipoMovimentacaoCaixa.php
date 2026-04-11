<?php

namespace App\Enums;

enum TipoMovimentacaoCaixa: string
{
    case Venda = 'venda';
    case Sangria = 'sangria';
    case Suprimento = 'suprimento';
    case Abertura = 'abertura';
    case Fechamento = 'fechamento';

    public function label(): string
    {
        return match ($this) {
            self::Venda => 'Venda',
            self::Sangria => 'Sangria',
            self::Suprimento => 'Suprimento',
            self::Abertura => 'Abertura',
            self::Fechamento => 'Fechamento',
        };
    }

    public function sinal(): int
    {
        return match ($this) {
            self::Venda, self::Suprimento, self::Abertura => 1,
            self::Sangria => -1,
            self::Fechamento => 0,
        };
    }
}
