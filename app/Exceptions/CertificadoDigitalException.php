<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Falha ao enviar/validar certificado digital .pfx via Focus NFe.
 * Mensagem amigável em pt-BR, segura para exibir ao usuário.
 */
class CertificadoDigitalException extends RuntimeException
{
}
