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
 * A regra `por_parte` (04/09/2026) inverte o desenho: o item fica no preço à
 * vista e o acréscimo do cartão é cobrado sobre o VALOR PAGO naquela forma —
 * ver `acrescimoSobre()`. É a única regra em que o preço do item não depende da
 * forma de pagamento, nem quando a venda tem uma forma só.
 *
 * O ATACADO é um segundo eixo, do cliente e não do pagamento: cliente marcado
 * como atacado leva o preço de atacado do produto independente da forma de
 * pagamento. Produto sem preço de atacado cadastrado cai no preço base.
 */
class TabelaPrecoService
{
    public const MODALIDADES = ['dinheiro_pix', 'debito', 'credito'];

    /**
     * Regra de split em que cada forma paga a tabela dela (04/09/2026).
     *
     * As outras três escolhem UMA tabela para a venda inteira. Nesta o item
     * fica sempre no preço à vista e o acréscimo do cartão vira valor de
     * pagamento — o mesmo caminho dos juros de parcelamento, que já sai na
     * NFC-e como `valor_outras_despesas` (vOutro) e no cupom como linha
     * própria. Ver `acrescimoSobre()`.
     */
    public const REGRA_POR_PARTE = 'por_parte';

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

        // Na regra por parte o ITEM nunca muda de tabela — nem com uma forma só.
        // O acréscimo vira valor de pagamento, então o preço unitário que vai
        // para a nota e para o cupom é sempre o da etiqueta. Sem isso haveria um
        // caso de borda feio: o caixa que registra o crédito PRIMEIRO teria os
        // itens inflados e, ao acrescentar a segunda forma, o valor já digitado
        // estaria numa tabela que deixou de valer.
        if ($this->cobraPorParte($config)) {
            return 'dinheiro_pix';
        }

        if (count($formas) <= 1) {
            return $modalidades[0] ?? 'dinheiro_pix';
        }

        return match ($config->regra_preco_split ?? 'cartao_maior') {
            'sempre_menor' => 'dinheiro_pix',
            'sempre_maior' => 'credito',
            default        => $this->maiorModalidade($modalidades),
        };
    }

    /** A loja cobra por parte (cada forma paga a tabela dela)? */
    public function cobraPorParte(ConfiguracaoLoja $config): bool
    {
        return ($config->regra_preco_split ?? 'cartao_maior') === self::REGRA_POR_PARTE;
    }

    /**
     * Acréscimo percentual que a forma de pagamento cobra sobre a parte dela.
     *
     * Débito e crédito saem da regra geral da loja; dinheiro, PIX, boleto,
     * crediário, transferência e vale não acrescentam nada.
     *
     * ⚠️ O override por produto (`produto_precos`) NÃO entra aqui: aquilo é um
     * preço fechado do item numa modalidade, e não há como transformá-lo em
     * percentual sobre um pedaço do pagamento. Loja que usa preço próprio no
     * cartão e liga esta regra passa a cobrar o percentual geral — a tela de
     * configuração avisa, com o número de produtos afetados.
     */
    public function acrescimoDaForma(string $forma, ConfiguracaoLoja $config): float
    {
        return match (self::FORMA_MODALIDADE[$forma] ?? 'dinheiro_pix') {
            'debito'  => max(0.0, (float) $config->percentual_debito),
            'credito' => max(0.0, (float) $config->percentual_credito),
            default   => 0.0,
        };
    }

    /**
     * Acréscimo em reais sobre um valor pago numa forma.
     *
     * O valor entra em preço à vista e sai com o acréscimo somado — a mesma
     * convenção do `JurosParcelamentoService::simular()`, para o caixa nunca
     * precisar saber em que "moeda" está digitando.
     *
     * @return array{percentual:float, acrescimo:float, total:float, tem_acrescimo:bool}
     */
    public function acrescimoSobre(float $valorBase, string $forma, ConfiguracaoLoja $config): array
    {
        $valorBase = round($valorBase, 2);
        $percentual = $this->acrescimoDaForma($forma, $config);
        $total = $percentual > 0 ? round($valorBase * (1 + $percentual / 100), 2) : $valorBase;

        return [
            'percentual'    => $percentual,
            'acrescimo'     => round($total - $valorBase, 2),
            'total'         => $total,
            'tem_acrescimo' => $percentual > 0,
        ];
    }

    private function maiorModalidade(array $modalidades): string
    {
        $peso = array_flip(self::MODALIDADES); // dinheiro_pix=0, debito=1, credito=2

        usort($modalidades, fn ($a, $b) => $peso[$b] <=> $peso[$a]);

        return $modalidades[0] ?? 'dinheiro_pix';
    }
}
