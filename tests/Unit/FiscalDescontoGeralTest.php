<?php

namespace Tests\Unit;

use App\Services\FocusNFe\FiscalPayloadBuilder;
use PHPUnit\Framework\TestCase;

/**
 * DK venda 448759 (10/11/2026): "Total do Desconto difere do somatorio dos itens" —
 * o desconto geral da venda ia só no total da nota, nunca nos itens.
 */
class FiscalDescontoGeralTest extends TestCase
{
    private function builder(): FiscalPayloadBuilder
    {
        return (new \ReflectionClass(FiscalPayloadBuilder::class))->newInstanceWithoutConstructor();
    }

    public function test_desconto_geral_rateado_fecha_e_soma_ao_desconto_do_item(): void
    {
        // 5% geral = 111,40 sobre 2.227,90 (itens da venda 448759) + um item que já tinha 5,00 de desconto.
        $itens = array_map(fn ($v) => ['valor_bruto' => $v], ['184.00', '459.00', '169.00', '189.00', '189.00', '189.00', '30.00', '39.90', '779.00']);
        $itens[] = ['valor_bruto' => '50.00', 'valor_desconto' => '5.00'];
        $this->builder()->ratearDescontoGeral($itens, 111.40);

        $total = array_sum(array_map(fn ($i) => (float) ($i['valor_desconto'] ?? 0), $itens));
        $this->assertEqualsWithDelta(116.40, $total, 0.001);
        foreach ($itens as $i) {
            $this->assertLessThanOrEqual((float) $i['valor_bruto'], (float) ($i['valor_desconto'] ?? 0));
        }
    }

    public function test_sem_desconto_geral_nao_mexe_nos_itens(): void
    {
        $itens = [['valor_bruto' => '10.00'], ['valor_bruto' => '20.00', 'valor_desconto' => '2.00']];
        $antes = $itens;
        $this->builder()->ratearDescontoGeral($itens, 0.0);

        $this->assertSame($antes, $itens);
    }
}
