<?php

namespace App\Services;

use App\Models\ConfiguracaoLoja;

/**
 * Juros de parcelamento no cartão de crédito.
 *
 * A loja cadastra uma tabela em `configuracoes_loja.juros_por_parcela`:
 * quantidade de parcelas → acréscimo TOTAL em % ({"6": 8, "12": 16}). É o
 * formato em que a adquirente manda a tabela dela, então o lojista confere
 * linha a linha com o extrato da maquininha, sem converter nada.
 *
 * Parcela sem linha na tabela (ou com 0) é parcela sem juros — inclusive o 1x.
 *
 * NÃO confundir com `AdquirenteTaxa`: aquilo é a taxa que a maquininha desconta
 * da loja (custo). Isto é o acréscimo cobrado do cliente pelo prazo.
 */
class JurosParcelamentoService
{
    /**
     * Acréscimo em % cadastrado para essa quantidade de parcelas.
     */
    public function percentual(int $parcelas, ConfiguracaoLoja $config): float
    {
        $tabela = $config->juros_por_parcela;

        if (! is_array($tabela)) {
            return 0.0;
        }

        return max(0.0, (float) ($tabela[(string) $parcelas] ?? 0));
    }

    /**
     * Simula o parcelamento de um valor.
     *
     * O total é a âncora (valor × (1 + %)), para bater com a tabela da
     * adquirente no centavo. A parcela é o total dividido — quando a divisão
     * não fecha exata, quem absorve os centavos é a última parcela, no
     * contas a receber.
     *
     * @return array{parcelas:int, valor_parcela:float, total:float, juros_valor:float, percentual:float, tem_juros:bool}
     */
    public function simular(float $valor, int $parcelas, ConfiguracaoLoja $config): array
    {
        $parcelas = max(1, $parcelas);
        $valor = round($valor, 2);
        $percentual = $this->percentual($parcelas, $config);

        $total = $percentual > 0
            ? round($valor * (1 + $percentual / 100), 2)
            : $valor;

        return [
            'parcelas'      => $parcelas,
            'valor_parcela' => round($total / $parcelas, 2),
            'total'         => $total,
            'juros_valor'   => round($total - $valor, 2),
            'percentual'    => $percentual,
            'tem_juros'     => $percentual > 0,
        ];
    }

    /**
     * Juros só existe em cartão de crédito parcelado — dinheiro, PIX, débito e
     * 1x nunca levam acréscimo, independente do que estiver configurado.
     */
    public function aplicavel(string $forma, int $parcelas): bool
    {
        return $forma === 'cartao_credito' && $parcelas > 1;
    }
}
