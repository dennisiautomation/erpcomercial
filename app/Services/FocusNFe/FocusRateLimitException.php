<?php

namespace App\Services\FocusNFe;

use RuntimeException;

/**
 * Lançada quando a Focus NFe devolve HTTP 429 (too many requests).
 * O campo retryAfterSeconds traz o valor do header Rate-Limit-Reset,
 * útil para re-agendar jobs sem desperdiçar mais tentativas.
 */
class FocusRateLimitException extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfterSeconds = 60)
    {
        parent::__construct($message);
    }
}
