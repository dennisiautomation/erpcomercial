<?php

namespace Tests\Feature\App;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ProdutoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_dono_can_list_produtos(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->createProduto($this->empresa);
        $this->createProduto($this->empresa);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.produtos.index'));

        $response->assertStatus(200);
        $response->assertViewHas('produtos');
    }

    public function test_dono_can_create_produto(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), [
                'descricao'      => 'Caneta Azul',
                'unidade_medida' => 'UN',
                'preco_venda'    => 5.50,
                'preco_custo'    => 2.00,
            ]);

        $response->assertRedirect(route('app.produtos.index'));

        $this->assertDatabaseHas('produtos', [
            'empresa_id' => $this->empresa->id,
            'descricao'  => 'Caneta Azul',
            'status'     => 'ativo',
        ]);
    }

    public function test_produto_requires_descricao_and_preco_venda(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        // Missing descricao
        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), [
                'unidade_medida' => 'UN',
                'preco_venda'    => 10.00,
            ]);

        $response->assertSessionHasErrors('descricao');

        // Missing preco_venda
        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), [
                'descricao'      => 'Produto Sem Preco',
                'unidade_medida' => 'UN',
            ]);

        $response->assertSessionHasErrors('preco_venda');
    }

    public function test_dono_can_update_produto(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->put(route('app.produtos.update', $produto), [
                'descricao'      => 'Produto Atualizado',
                'unidade_medida' => 'UN',
                'preco_venda'    => 150.00,
                'status'         => 'ativo',
            ]);

        $response->assertRedirect(route('app.produtos.index'));

        $this->assertDatabaseHas('produtos', [
            'id'        => $produto->id,
            'descricao' => 'Produto Atualizado',
        ]);
    }

    public function test_vendedor_can_only_view_produtos(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $this->createProduto($this->empresa);

        // Vendedor has 'ver' permission on produtos module
        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.produtos.index'));

        $response->assertStatus(200);

        // Vendedor should NOT have access to financeiro module
        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.contas-receber.index'));

        $response->assertStatus(403);
    }

    public function test_produto_data_isolated_by_empresa(): void
    {
        [$empresaB, $unidadeB] = $this->createTenant('B');

        $produtoA = $this->createProduto($this->empresa, ['descricao' => 'Produto Empresa A']);
        $produtoB = $this->createProduto($empresaB, ['descricao' => 'Produto Empresa B']);

        $donoA = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($donoA, $this->unidade)
            ->get(route('app.produtos.index'));

        $response->assertStatus(200);
        $response->assertSee('Produto Empresa A');
        $response->assertDontSee('Produto Empresa B');
    }
}
