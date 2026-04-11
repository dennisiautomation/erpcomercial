<?php

namespace App\Enums;

enum StatusEmpresa: string
{
    case EmImplantacao = 'em_implantacao';
    case Ativo = 'ativo';
    case Suspenso = 'suspenso';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::EmImplantacao => 'Em Implantação',
            self::Ativo => 'Ativo',
            self::Suspenso => 'Suspenso',
            self::Cancelado => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EmImplantacao => 'warning',
            self::Ativo => 'success',
            self::Suspenso => 'danger',
            self::Cancelado => 'secondary',
        };
    }
}
