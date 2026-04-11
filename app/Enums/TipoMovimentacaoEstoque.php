<?php

namespace App\Enums;

enum TipoMovimentacaoEstoque: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';
    case Perda = 'perda';
    case Bonificacao = 'bonificacao';
    case Transferencia = 'transferencia';
    case Devolucao = 'devolucao';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::Ajuste => 'Ajuste',
            self::Perda => 'Perda',
            self::Bonificacao => 'Bonificação',
            self::Transferencia => 'Transferência',
            self::Devolucao => 'Devolução',
        };
    }

    public function sinal(): int
    {
        return match ($this) {
            self::Entrada, self::Devolucao => 1,
            self::Saida, self::Perda, self::Bonificacao => -1,
            self::Ajuste, self::Transferencia => 0, // depends on context
        };
    }
}
