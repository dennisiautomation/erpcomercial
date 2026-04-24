<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando a emissão de Carta de Correção falha na SEFAZ/Focus.
 * A mensagem é amigável e em pt-BR, segura para exibir ao usuário final.
 */
class CartaCorrecaoException extends RuntimeException
{
}
