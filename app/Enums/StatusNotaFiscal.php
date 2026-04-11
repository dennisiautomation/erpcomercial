<?php

namespace App\Enums;

enum StatusNotaFiscal: string
{
    case Pendente = 'pendente';
    case Autorizada = 'autorizada';
    case Cancelada = 'cancelada';
    case Rejeitada = 'rejeitada';
    case Inutilizada = 'inutilizada';
    case Contingencia = 'contingencia';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Autorizada => 'Autorizada',
            self::Cancelada => 'Cancelada',
            self::Rejeitada => 'Rejeitada',
            self::Inutilizada => 'Inutilizada',
            self::Contingencia => 'Contingência',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Autorizada => 'success',
            self::Cancelada => 'danger',
            self::Rejeitada => 'danger',
            self::Inutilizada => 'secondary',
            self::Contingencia => 'info',
        };
    }
}
