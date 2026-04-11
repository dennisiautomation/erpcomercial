<?php

namespace Tests\Feature\App;

use App\Enums\StatusOrcamento;
use App\Models\Orcamento;
use App\Models\Pedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class OrcamentoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_vendedor_can_create_orcamento_with_items(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto1 = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $produto2 = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);

        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'   => $cliente->id,
                'vendedor_id'  => $vendedor->id,
                'validade_ate' => now()->addDays(30)->format('Y-m-d'),
                'itens' => [
                    [
                        'produto_id'     => $produto1->id,
                        'quantidade'     => 2,
                        'preco_unitario' => 100.00,
                    ],
                    [
                        'produto_id'     => $produto2->id,
                        'quantidade'     => 3,
                        'preco_unitario' => 50.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('app.orcamentos.index'));

        $this->assertDatabaseHas('orcamentos', [
            'empresa_id'  => $this->empresa->id,
            'unidade_id'  => $this->unidade->id,
            'cliente_id'  => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'status'      => 'em_aberto',
        ]);

        $orcamento = Orcamento::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->first();
        $this->assertCount(2, $orcamento->itens);
    }

    public function test_orcamento_calculates_totals_correctly(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 80.00]);

        $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'     => $cliente->id,
                'vendedor_id'    => $vendedor->id,
                'validade_ate'   => now()->addDays(15)->format('Y-m-d'),
                'desconto_valor' => 10.00,
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 5,
                        'preco_unitario' => 80.00,
                    ],
                ],
            ]);

        $orcamento = Orcamento::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->first();

        // subtotal = 5 * 80 = 400, total = 400 - 10 = 390
        $this->assertEquals(400.00, (float) $orcamento->subtotal);
        $this->assertEquals(390.00, (float) $orcamento->total);
        $this->assertEquals(10.00, (float) $orcamento->desconto_valor);
    }

    public function test_orcamento_can_be_converted_to_pedido(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);

        // Create orcamento
        $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'   => $cliente->id,
                'vendedor_id'  => $vendedor->id,
                'validade_ate' => now()->addDays(30)->format('Y-m-d'),
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 2,
                        'preco_unitario' => 100.00,
                    ],
                ],
            ]);

        $orcamento = Orcamento::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->first();

        // Convert to pedido
        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.converter', $orcamento));

        $response->assertRedirect();

        // Pedido should exist
        $this->assertDatabaseHas('pedidos', [
            'empresa_id'  => $this->empresa->id,
            'orcamento_id' => $orcamento->id,
            'cliente_id'   => $cliente->id,
            'status'       => 'rascunho',
        ]);

        $pedido = Pedido::withoutGlobalScopes()->where('orcamento_id', $orcamento->id)->first();
        $this->assertNotNull($pedido);
        $this->assertEquals((float) $orcamento->total, (float) $pedido->total);
    }

    public function test_converted_orcamento_status_changes(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);

        $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'   => $cliente->id,
                'vendedor_id'  => $vendedor->id,
                'validade_ate' => now()->addDays(10)->format('Y-m-d'),
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 50.00,
                    ],
                ],
            ]);

        $orcamento = Orcamento::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->first();
        $this->assertEquals(StatusOrcamento::EmAberto, $orcamento->status);

        // Convert
        $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.converter', $orcamento));

        $orcamento->refresh();
        $this->assertEquals(StatusOrcamento::Convertido, $orcamento->status);

        // Cannot convert again
        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.orcamentos.converter', $orcamento));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_orcamento_data_isolated_by_empresa(): void
    {
        [$empresaB, $unidadeB] = $this->createTenant('B');

        $vendedorA = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $clienteA  = $this->createCliente($this->empresa);
        $produtoA  = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);

        $vendedorB = $this->createUser($empresaB, $unidadeB, 'vendedor');
        $clienteB  = $this->createCliente($empresaB);
        $produtoB  = $this->createProduto($empresaB, ['preco_venda' => 200.00]);

        // Create orcamento in empresa A
        $this->actingAsUser($vendedorA, $this->unidade)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'   => $clienteA->id,
                'vendedor_id'  => $vendedorA->id,
                'validade_ate' => now()->addDays(30)->format('Y-m-d'),
                'itens' => [
                    ['produto_id' => $produtoA->id, 'quantidade' => 1, 'preco_unitario' => 100.00],
                ],
            ]);

        // Create orcamento in empresa B
        $this->actingAsUser($vendedorB, $unidadeB)
            ->post(route('app.orcamentos.store'), [
                'cliente_id'   => $clienteB->id,
                'vendedor_id'  => $vendedorB->id,
                'validade_ate' => now()->addDays(30)->format('Y-m-d'),
                'itens' => [
                    ['produto_id' => $produtoB->id, 'quantidade' => 1, 'preco_unitario' => 200.00],
                ],
            ]);

        // Empresa A listing should only show 1 orcamento
        $response = $this->actingAsUser($vendedorA, $this->unidade)
            ->get(route('app.orcamentos.index'));

        $response->assertStatus(200);
        $orcamentos = $response->viewData('orcamentos');
        $this->assertEquals(1, $orcamentos->total());
    }
}
