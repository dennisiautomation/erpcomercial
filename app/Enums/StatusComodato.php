<?php

namespace App\Enums;

enum StatusComodato: string
{
    case Pendente = 'pendente';
    case Parcial = 'parcial';
    case Devolvido = 'devolvido';
    case Perdido = 'perdido';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando retorno',
            self::Parcial => 'Devolvido em parte',
            self::Devolvido => 'Devolvido',
            self::Perdido => 'Não voltou',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'warning',
            self::Parcial => 'info',
            self::Devolvido => 'success',
            self::Perdido => 'danger',
        };
    }

    /** Status que ainda contam como peça fora da loja. */
    public function emAberto(): bool
    {
        return in_array($this, [self::Pendente, self::Parcial], true);
    }

    /** @return array<string> valores que ainda contam como peça fora */
    public static function valoresEmAberto(): array
    {
        return [self::Pendente->value, self::Parcial->value];
    }
}
