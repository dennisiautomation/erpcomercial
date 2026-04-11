<?php

namespace App\Enums;

enum Perfil: string
{
    case Admin = 'admin';
    case Dono = 'dono';
    case Gerente = 'gerente';
    case Vendedor = 'vendedor';
    case Caixa = 'caixa';
    case Financeiro = 'financeiro';
    case Consulta = 'consulta';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Dono => 'Dono/Gestor',
            self::Gerente => 'Gerente',
            self::Vendedor => 'Vendedor',
            self::Caixa => 'Operador de Caixa',
            self::Financeiro => 'Financeiro',
            self::Consulta => 'Consulta',
        };
    }

    public function nivel(): int
    {
        return match ($this) {
            self::Admin => 100,
            self::Dono => 90,
            self::Gerente => 70,
            self::Vendedor => 50,
            self::Caixa => 40,
            self::Financeiro => 60,
            self::Consulta => 10,
        };
    }
}
