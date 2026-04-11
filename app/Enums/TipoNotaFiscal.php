<?php

namespace App\Enums;

enum TipoNotaFiscal: string
{
    case NFe = 'nfe';
    case NFCe = 'nfce';
    case NFSe = 'nfse';

    public function label(): string
    {
        return match ($this) {
            self::NFe => 'NF-e (Modelo 55)',
            self::NFCe => 'NFC-e (Consumidor)',
            self::NFSe => 'NFS-e (Serviços)',
        };
    }
}
