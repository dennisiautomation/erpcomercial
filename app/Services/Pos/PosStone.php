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
        // `origem` marca TODO pagamento feito pelo app da maquininha, inclusive dinheiro (29/10/2026): é o que separa,
        // na lista de vendas e no financeiro, a venda da maquininha da venda do PDV do sistema.
        $pg = ['forma' => $forma, 'valor' => round($valor, 2), 'origem' => 'pos_stone'];
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
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $pg;
    }

    /**
     * Deixa claro no financeiro que a venda foi feita na maquininha (Dennis, 29/10/2026): a conta a receber
     * criada pelo PDV/pedido ("Venda PDV #169 - Cartao_credito") vira "Venda Maquininha Stone #169 - Cartão de
     * crédito VISA" e ganha nas observações o terminal (série do ITK), autorização, ATK, operador e horário.
     */
    public static function marcarFinanceiro(\App\Models\Venda $venda): void
    {
        $formas = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'cartao_debito' => 'Cartão de débito', 'cartao_credito' => 'Cartão de crédito'];
        $pgs = collect($venda->pagamento_detalhes ?? []);
        $venda->loadMissing('vendedor');

        foreach (\App\Models\ContaReceber::withoutGlobalScopes()->where('venda_id', $venda->id)->get() as $conta) {
            $pg = $pgs->firstWhere('forma', $conta->forma_pagamento) ?? [];
            $cartao = in_array($conta->forma_pagamento, ['cartao_credito', 'cartao_debito'], true);
            $forma = trim(($formas[$conta->forma_pagamento] ?? ucfirst(str_replace('_', ' ', (string) $conta->forma_pagamento)))
                . ' ' . ($cartao ? ($pg['bandeira_nome'] ?? '') : ''));
            $parcela = $conta->total_parcelas > 1 ? " {$conta->parcela}/{$conta->total_parcelas}" : '';
            $origem = $venda->pedido_id
                ? 'Pedido #' . ($venda->pedido?->numero ?? $venda->pedido_id) . ' (venda #' . $venda->numero . ') - Maquininha Stone'
                : 'Venda Maquininha Stone #' . $venda->numero;
            $terminal = ! empty($pg['stone_itk']) ? strtok((string) $pg['stone_itk'], '-') : null;

            $conta->update([
                'descricao' => mb_substr("{$origem} - {$forma}{$parcela}", 0, 255),
                'observacoes' => implode(' · ', array_filter([
                in_array($conta->forma_pagamento, self::FORMAS_ELETRONICAS, true)
                    ? 'Recebido na maquininha Stone' . ($terminal ? " {$terminal}" : '')
                    : 'Lançado pelo app da maquininha (dinheiro no caixa)',
                    ! empty($pg['autorizacao']) ? 'Aut ' . $pg['autorizacao'] : null,
                    ! empty($pg['stone_atk']) ? 'ATK ' . $pg['stone_atk'] : null,
                    $venda->vendedor?->name ? 'Operador ' . $venda->vendedor->name : null,
                    $venda->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
                ])),
            ]);
        }
    }

    /**
     * Item de `pagamento_detalhes` que passou pela maquininha → dados prontos para a tela (venda e conta a receber):
     * terminal (série do ITK), valor aprovado, se bate com o valor registrado e a hora da Stone em Brasília
     * (a Stone devolve em UTC). Null quando o pagamento não veio da maquininha.
     */
    public static function transacao(array $pg): ?array
    {
        // Só pagamento cobrado NA Stone (cartão/PIX com ATK); dinheiro lançado pelo app não tem transação.
        if (($pg['origem'] ?? null) !== 'pos_stone' || empty($pg['stone_atk'])) {
            return null;
        }
        $aprovado = isset($pg['stone_valor_centavos']) ? $pg['stone_valor_centavos'] / 100 : null;
        try {
            $hora = ! empty($pg['stone_data_hora'])
                ? \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $pg['stone_data_hora'], 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
                : null;
        } catch (\Throwable) {
            $hora = $pg['stone_data_hora'];
        }

        return [
            'forma' => $pg['forma'] ?? null,
            'valor' => (float) ($pg['valor'] ?? 0),
            'aprovado' => $aprovado,
            'conciliado' => $aprovado !== null && abs($aprovado - (float) ($pg['valor'] ?? 0)) < 0.01,
            'tipo' => $pg['stone_tipo'] ?? null,
            'bandeira' => $pg['bandeira_nome'] ?? null,
            'parcelas' => (int) ($pg['parcelas'] ?? 1),
            'autorizacao' => $pg['autorizacao'] ?? null,
            'atk' => $pg['stone_atk'] ?? null,
            'terminal' => ! empty($pg['stone_itk']) ? strtok((string) $pg['stone_itk'], '-') : null,
            'hora' => $hora,
            'cnpj' => $pg['cnpj_credenciadora'] ?? null,
        ];
    }

    /** A venda teve algum pagamento feito na maquininha? (selo nas listas e telas). */
    public static function daMaquininha(?array $pagamentos): bool
    {
        return collect($pagamentos ?? [])->contains('origem', 'pos_stone');
    }
}
