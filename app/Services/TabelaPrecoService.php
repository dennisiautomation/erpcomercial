<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConfiguracaoLoja;
use App\Models\Produto;

/**
 * Tabelas de preço por forma de pagamento.
 *
 * Preço base do produto (preco_venda) = tabela Dinheiro/PIX. Débito e crédito
 * saem da regra geral (percentual em configuracoes_loja) com override por
 * produto em produto_precos. Modalidades ordenadas: dinheiro_pix < debito < credito.
 *
 * O ATACADO é um segundo eixo, do cliente e não do pagamento: cliente marcado
 * como atacado leva o preço de atacado do produto independente da forma de
 * pagamento. Produto sem preço de atacado cadastrado cai no preço base.
 */
class TabelaPrecoService
{
    public const MODALIDADES = ['dinheiro_pix', 'debito', 'credito'];

    /** Forma de pagamento do PDV → modalidade de preço. */
    public const FORMA_MODALIDADE = [
        'dinheiro'       => 'dinheiro_pix',
        'pix'            => 'dinheiro_pix',
        'cartao_debito'  => 'debito',
        'cartao_credito' => 'credito',
        // Demais formas (boleto, crediario, transferencia, vale) usam a tabela base
    ];

    /** Preços das 3 modalidades para um produto (override > regra geral > base). */
    public function precosDoProduto(Produto $produto, ConfiguracaoLoja $config): array
    {
        $base = (float) $produto->preco_venda;

        $overrides = $produto->relationLoaded('precos')
            ? $produto->precos->pluck('valor', 'modalidade')
            : $produto->precos()->pluck('valor', 'modalidade');

        return [
            'dinheiro_pix' => (float) ($overrides['dinheiro_pix'] ?? $base),
            'debito'       => (float) ($overrides['debito']
                ?? round($base * (1 + ((float) $config->percentual_debito) / 100), 2)),
            'credito'      => (float) ($overrides['credito']
                ?? round($base * (1 + ((float) $config->percentual_credito) / 100), 2)),
            // Sem preço de atacado cadastrado o cliente de atacado paga o base —
            // nunca um preço inventado por regra geral.
            'atacado'      => (float) ($overrides['atacado'] ?? $base),
        ];
    }

    /**
     * Modalidade de preço da venda: o tipo do cliente vem antes da forma de pagamento.
     *
     * Cliente de atacado leva o preço de atacado em qualquer forma; cliente de
     * varejo (ou venda sem cliente) segue as tabelas por pagamento de sempre.
     */
    public function modalidadeDaVenda(array $formas, ConfiguracaoLoja $config, ?Cliente $cliente = null): string
    {
        if ($cliente && $cliente->tipo_preco === 'atacado') {
            return 'atacado';
        }

        return $this->modalidadeDosPagamentos($formas, $config);
    }

    /**
     * Modalidade de preço aplicável ao conjunto de formas de pagamento da venda.
     *
     * Venda simples (1 forma) segue a forma. Split segue a regra parametrizada:
     * - cartao_maior: a maior tabela entre as formas presentes
     * - sempre_menor: tabela base (dinheiro/PIX)
     * - sempre_maior: tabela crédito
     */
    public function modalidadeDosPagamentos(array $formas, ConfiguracaoLoja $config): string
    {
        $modalidades = array_map(
            fn ($forma) => self::FORMA_MODALIDADE[$forma] ?? 'dinheiro_pix',
            $formas
        );

        if (count($formas) <= 1) {
            return $modalidades[0] ?? 'dinheiro_pix';
        }

        return match ($config->regra_preco_split ?? 'cartao_maior') {
            'sempre_menor' => 'dinheiro_pix',
            'sempre_maior' => 'credito',
            default        => $this->maiorModalidade($modalidades),
        };
    }

    private function maiorModalidade(array $modalidades): string
    {
        $peso = array_flip(self::MODALIDADES); // dinheiro_pix=0, debito=1, credito=2

        usort($modalidades, fn ($a, $b) => $peso[$b] <=> $peso[$a]);

        return $modalidades[0] ?? 'dinheiro_pix';
    }
}
