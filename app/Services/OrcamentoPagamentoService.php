<?php

namespace App\Services;

use App\Models\ConfiguracaoLoja;

/**
 * Quadro "Formas de pagamento" do orçamento (25/10/2026, pedido da DK).
 *
 * As vendedoras digitavam à mão "5x de R$ 300 no cartão ou R$ 1.425 no PIX" e o
 * cliente se confundia. Aqui o sistema calcula as duas linhas a partir do total:
 *
 *  - Loja com preço cadastrado = cartão: o total dos produtos JÁ é o do cartão.
 *    Cartão = produtos + frete (com juros da tabela, se a parcela tiver).
 *    Dinheiro/PIX = produtos com o desconto à vista + frete.
 *  - Loja na regra de sempre (preço cadastrado = à vista): Dinheiro/PIX = total
 *    (menos o desconto à vista do orçamento, se o vendedor puser algum).
 *    Cartão = produtos com o acréscimo de crédito da loja + frete, e os juros.
 *
 * O frete nunca leva desconto nem acréscimo: é repasse.
 * Preço próprio por produto (produto_precos) não entra — o orçamento trabalha com
 * o preço que está na linha do item, que o vendedor pode ter mudado.
 */
class OrcamentoPagamentoService
{
    public function __construct(private JurosParcelamentoService $juros)
    {
    }

    /**
     * @param  float  $produtos  total dos itens já com os descontos do orçamento (sem frete)
     * @return array{avista:float, desconto_percentual:float, desconto_valor:float, parcelas:int,
     *               valor_parcela:float, cartao_total:float, tem_juros:bool, juros_percentual:float}
     */
    public function simular(float $produtos, float $frete, int $parcelas, float $descontoAvista, ConfiguracaoLoja $config): array
    {
        $produtos = round(max(0.0, $produtos), 2);
        $frete = round(max(0.0, $frete), 2);
        $parcelas = max(1, $parcelas);
        $descontoAvista = max(0.0, min(100.0, $descontoAvista));

        $descontoValor = round($produtos * $descontoAvista / 100, 2);
        $avista = round($produtos - $descontoValor + $frete, 2);

        $cartaoBase = $config->precoBaseCartao()
            ? $produtos
            : round($produtos * (1 + max(0.0, (float) $config->percentual_credito) / 100), 2);

        $sim = $this->juros->simular($cartaoBase + $frete, $parcelas, $config);

        return [
            'avista'              => $avista,
            'desconto_percentual' => $descontoAvista,
            'desconto_valor'      => $descontoValor,
            'parcelas'            => $parcelas,
            'valor_parcela'       => $sim['valor_parcela'],
            'cartao_total'        => $sim['total'],
            'tem_juros'           => $sim['tem_juros'],
            'juros_percentual'    => $sim['percentual'],
        ];
    }

    /** Desconto à vista que um orçamento novo já traz. */
    public function descontoPadrao(ConfiguracaoLoja $config): float
    {
        return $config->precoBaseCartao() ? (float) $config->desconto_avista_percentual : 0.0;
    }
}
