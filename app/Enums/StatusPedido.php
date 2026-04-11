<?php

namespace App\Enums;

enum StatusPedido: string
{
    case Rascunho = 'rascunho';
    case Confirmado = 'confirmado';
    case Faturado = 'faturado';
    case Entregue = 'entregue';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Confirmado => 'Confirmado',
            self::Faturado => 'Faturado',
            self::Entregue => 'Entregue',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rascunho => 'secondary',
            self::Confirmado => 'primary',
            self::Faturado => 'info',
            self::Entregue => 'success',
            self::Cancelado => 'danger',
        };
    }
}
