<?php

namespace App\Services\Pos;

/**
 * Tradução do retorno do Deeplink da Stone para o ERP e para a NFC-e.
 */
class PosStone
{
    /** Stone Instituição de Pagamento S.A. — credenciadora informada na NFC-e (tag CNPJ do grupo card). */
    public const CNPJ_CREDENCIADORA = '16501555000157';

    /** Formas que passam pela maquininha (cobrança eletrônica, com estorno pelo app da Stone). */
    public const FORMAS_ELETRONICAS = ['cartao_credito', 'cartao_debito', 'pix'];

    /**
     * Bandeira devolvida pela Stone (campo `brand`) → código tBand da NFC-e (NT 2015.002).
     * Desconhecida → 99 (outros), que a SEFAZ aceita.
     */
    public static function codigoBandeira(?string $marca): string
    {
        $m = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $marca));

        return match (true) {
            str_contains($m, 'VISA') => '01',
            str_contains($m, 'MASTER') || str_contains($m, 'MAESTRO') => '02',
            str_contains($m, 'AMEX') || str_contains($m, 'AMERICAN') => '03',
            str_contains($m, 'SOROCRED') => '04',
            str_contains($m, 'DINERS') => '05',
            str_contains($m, 'ELO') => '06',
            str_contains($m, 'HIPERCARD') => '07',
            str_contains($m, 'AURA') => '08',
            str_contains($m, 'CABAL') => '09',
            str_contains($m, 'BANESCARD') => '20',
            str_contains($m, 'JCB') => '24',
            str_contains($m, 'HIPER') => '30',
            default => '99',
        };
    }

    /**
     * Monta o item de `pagamento_detalhes` com os dados da transação Stone. As
     * chaves `bandeira`, `cnpj_credenciadora` e `autorizacao` são as que o
     * FiscalPayloadBuilder::formasPagamento já lê para a NFC-e.
     */
    public static function pagamento(string $forma, float $valor, ?int $parcelas, ?array $stone): array
    {
        $pg = ['forma' => $forma, 'valor' => round($valor, 2)];
        if ($parcelas && $parcelas > 1) {
            $pg['parcelas'] = $parcelas;
        }

        if ($stone && in_array($forma, self::FORMAS_ELETRONICAS, true)) {
            $pg += array_filter([
                'bandeira' => $forma === 'pix' ? null : self::codigoBandeira($stone['bandeira'] ?? null),
                'bandeira_nome' => $stone['bandeira'] ?? null,
                'cnpj_credenciadora' => self::CNPJ_CREDENCIADORA,
                'autorizacao' => $stone['autorizacao'] ?? null,
                'stone_atk' => $stone['atk'] ?? null,
                'stone_itk' => $stone['itk'] ?? null,
                'stone_tipo' => $stone['tipo'] ?? null,
                'stone_data_hora' => $stone['data_hora'] ?? null,
                'stone_valor_centavos' => isset($stone['valor_centavos']) ? (int) $stone['valor_centavos'] : null,
                'origem' => 'pos_stone',
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $pg;
    }
}
