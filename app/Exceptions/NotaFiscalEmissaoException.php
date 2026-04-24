<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lançada quando os dados enviados para emissão de nota fiscal
 * estão incompletos/inválidos, ANTES de chamar a Focus NFe.
 * Mensagem em pt-BR, segura para exibir ao usuário.
 */
class NotaFiscalEmissaoException extends RuntimeException
{
}
