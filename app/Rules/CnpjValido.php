<?php

namespace App\Rules;

use App\Support\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CNPJ numérico OU alfanumérico (NT Conjunta 2025.001):
 * 12 primeiras posições aceitam A-Z/0-9, DV numérico por módulo 11 (ASCII-48).
 */
class CnpjValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cnpj::valido((string) $value)) {
            $fail('O CNPJ informado é inválido (dígitos verificadores não conferem).');
        }
    }
}
