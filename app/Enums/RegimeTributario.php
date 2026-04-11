<?php

namespace App\Enums;

enum RegimeTributario: string
{
    case SimplesNacional = 'simples_nacional';
    case LucroPresumido = 'lucro_presumido';
    case LucroReal = 'lucro_real';

    public function label(): string
    {
        return match ($this) {
            self::SimplesNacional => 'Simples Nacional',
            self::LucroPresumido => 'Lucro Presumido',
            self::LucroReal => 'Lucro Real',
        };
    }
}
