<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Services\FocusNFe\FiscalPayloadBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DK venda 448734 (10/11/2026): "NF-e sem tag IE do destinatario" (IE = "ISENTO")
 * e "Total da NF difere do somatorio" (frete do pedido fora da nota).
 */
class FiscalIeFreteTest extends TestCase
{
    private function builder(): FiscalPayloadBuilder
    {
        return (new \ReflectionClass(FiscalPayloadBuilder::class))->newInstanceWithoutConstructor();
    }

    public static function casosIe(): array
    {
        return [
            'isento no PI' => ['ISENTO', 'PI', ['2', null]],
            'isento minúsculo no MA' => ['isento', 'MA', ['2', null]],
            'isento em SP não existe' => ['ISENTO', 'SP', ['9', null]],
            'IE com máscara' => ['12.345.678-9', 'PI', ['1', '123456789']],
            'vazio' => ['', 'PI', ['9', null]],
            'nulo' => [null, 'PI', ['9', null]],
            'texto qualquer' => ['inseto', 'PI', ['9', null]],
        ];
    }

    #[DataProvider('casosIe')]
    public function test_indicador_ie_do_destinatario(?string $ie, string $uf, array $esperado): void
    {
        $cliente = new Cliente();
        $cliente->ie = $ie;
        $cliente->uf = $uf;

        $this->assertSame($esperado, $this->builder()->indicadorIeDestinatario($cliente));
    }

    public function test_rateio_do_frete_fecha_no_centavo(): void
    {
        $itens = [['valor_bruto' => '169.00'], ['valor_bruto' => '408.00'], ['valor_bruto' => '189.90']];
        $this->builder()->ratearAcessorios($itens, 10.00, 0.0);

        $this->assertEqualsWithDelta(10.00, array_sum(array_column($itens, 'valor_frete')), 0.001);
        $this->assertArrayNotHasKey('valor_outras_despesas', $itens[0]);
    }

    public function test_rateio_respeita_desconto_do_item_e_sobra_vai_no_maior(): void
    {
        $itens = [
            ['valor_bruto' => '10.00', 'valor_desconto' => '10.00'],
            ['valor_bruto' => '33.33'],
            ['valor_bruto' => '33.33'],
            ['valor_bruto' => '33.34'],
        ];
        $this->builder()->ratearAcessorios($itens, 0.0, 1.00);

        $this->assertArrayNotHasKey('valor_outras_despesas', $itens[0]);
        $this->assertEqualsWithDelta(1.00, array_sum(array_column($itens, 'valor_outras_despesas')), 0.001);
    }
}
