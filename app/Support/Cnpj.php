<?php

namespace App\Support;

/**
 * Utilitário de CPF/CNPJ com suporte ao CNPJ alfanumérico
 * (IN RFB 2.229/2024 + NT Conjunta 2025.001 — aceito na SEFAZ desde 06/07/2026).
 *
 * Formato novo: as 12 primeiras posições aceitam letras (A-Z) e números;
 * os 2 dígitos verificadores continuam numéricos, calculados pelo módulo 11
 * clássico, mas usando o valor ASCII de cada caractere menos 48
 * (0-9 → 0-9, A → 17 ... Z → 42).
 *
 * IMPORTANTE: nunca sanitizar CNPJ com preg_replace('/\D/') — isso destrói
 * as letras de um CNPJ alfanumérico. Usar sempre Cnpj::limpar().
 */
final class Cnpj
{
    /** Remove máscara preservando letras (CNPJ alfanumérico) e normaliza em caixa alta. */
    public static function limpar(?string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $valor) ?? '');
    }

    /**
     * Limpa um campo que pode ser CPF ou CNPJ. CPF continua 11 dígitos
     * numéricos; qualquer letra presente indica CNPJ alfanumérico.
     */
    public static function limparCpfCnpj(?string $valor): string
    {
        return self::limpar($valor);
    }

    /** True quando o documento limpo tem 14 posições (CNPJ), com ou sem letras. */
    public static function pareceCnpj(?string $valor): bool
    {
        return strlen(self::limpar($valor)) === 14;
    }

    /** True quando o documento limpo é um CPF (11 posições, só dígitos). */
    public static function pareceCpf(?string $valor): bool
    {
        return (bool) preg_match('/^\d{11}$/', self::limpar($valor));
    }

    /**
     * Valida estrutura + dígitos verificadores (aceita numérico e alfanumérico).
     */
    public static function valido(?string $cnpj): bool
    {
        $cnpj = self::limpar($cnpj);

        if (! preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj)) {
            return false;
        }
        // Sequências de um mesmo dígito (00000000000000 etc.) são inválidas
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        return substr($cnpj, 12, 2) === self::dv(substr($cnpj, 0, 12));
    }

    /**
     * Calcula os 2 dígitos verificadores da base de 12 posições.
     * Módulo 11 com pesos clássicos, valor do caractere = ASCII - 48.
     */
    public static function dv(string $base12): string
    {
        $valores = array_map(fn (string $c) => ord($c) - 48, str_split(strtoupper($base12)));

        $dv1 = self::mod11($valores, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $valores[] = $dv1;
        $dv2 = self::mod11($valores, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return $dv1 . $dv2;
    }

    /** Reaplica a máscara XX.XXX.XXX/XXXX-XX (funciona com letras). */
    public static function formatar(?string $cnpj): string
    {
        $c = self::limpar($cnpj);
        if (strlen($c) !== 14) {
            return (string) $cnpj;
        }

        return substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3)
            . '/' . substr($c, 8, 4) . '-' . substr($c, 12, 2);
    }

    /** @param  list<int>  $valores  @param  list<int>  $pesos */
    private static function mod11(array $valores, array $pesos): int
    {
        $soma = 0;
        foreach ($valores as $i => $v) {
            $soma += $v * $pesos[$i];
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
