<?php

namespace App\Enums;

enum StatusVenda: string
{
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';
    case Devolvida = 'devolvida';

    public function label(): string
    {
        return match ($this) {
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
            self::Devolvida => 'Devolvida',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Concluida => 'success',
            self::Cancelada => 'danger',
            self::Devolvida => 'warning',
        };
    }
}
