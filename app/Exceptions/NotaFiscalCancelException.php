<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando o cancelamento de uma nota fiscal falha na SEFAZ/Focus.
 * A mensagem deve ser amigável e em pt-BR, segura para exibir ao usuário final.
 */
class NotaFiscalCancelException extends RuntimeException
{
}
