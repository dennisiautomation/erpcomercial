<?php

namespace App\Enums;

enum StatusCaixa: string
{
    case Aberto = 'aberto';
    case Fechado = 'fechado';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Fechado => 'Fechado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Aberto => 'success',
            self::Fechado => 'secondary',
        };
    }
}
