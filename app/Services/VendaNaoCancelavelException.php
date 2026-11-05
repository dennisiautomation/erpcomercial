<?php

namespace App\Services;

use App\Models\NotaFiscal;

/** A venda não pode ser cancelada agora; a mensagem já é a que o operador lê. */
class VendaNaoCancelavelException extends \DomainException
{
    public function __construct(string $mensagem, public readonly ?NotaFiscal $nota = null)
    {
        parent::__construct($mensagem);
    }
}
