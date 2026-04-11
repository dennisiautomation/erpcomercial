<?php

namespace App\Enums;

enum StatusOrcamento: string
{
    case EmAberto = 'em_aberto';
    case Aprovado = 'aprovado';
    case Recusado = 'recusado';
    case Expirado = 'expirado';
    case Convertido = 'convertido';

    public function label(): string
    {
        return match ($this) {
            self::EmAberto => 'Em Aberto',
            self::Aprovado => 'Aprovado',
            self::Recusado => 'Recusado',
            self::Expirado => 'Expirado',
            self::Convertido => 'Convertido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EmAberto => 'warning',
            self::Aprovado => 'success',
            self::Recusado => 'danger',
            self::Expirado => 'secondary',
            self::Convertido => 'info',
        };
    }
}
