<?php

namespace App\Services\OpenFinance;

use RuntimeException;

/** Erro de conversa com a Tecnospeed; a mensagem já vem pronta para a tela. */
class OpenFinanceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
