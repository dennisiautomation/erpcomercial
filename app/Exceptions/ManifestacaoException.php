<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao sincronizar NFes destinadas ou enviar manifestação do
 * destinatário via Focus NFe. Mensagem em pt-BR, segura para UI.
 */
class ManifestacaoException extends RuntimeException
{
}
