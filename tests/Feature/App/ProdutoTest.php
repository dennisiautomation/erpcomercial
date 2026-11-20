<?php

namespace Tests\Feature\App;

use App\Models\Produto;
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

    public function test_segundo_envio_seguido_do_mesmo_produto_nao_duplica(): void
    {
        // Duplo clique no "Salvar Produto" (chamado da Stilo Vinte, 20/11/2026): o segundo
        // envio chegava com o preço 100× e gravava outro produto.
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $dados = ['descricao' => 'Vestido Cetim Perolado', 'unidade_medida' => 'UN', 'preco_custo' => 75];

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), $dados + ['preco_venda' => 165.00])
            ->assertRedirect(route('app.produtos.index'));
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), $dados + ['preco_venda' => 16500.00])
            ->assertRedirect(route('app.produtos.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, Produto::withoutGlobalScopes()->where('descricao', 'Vestido Cetim Perolado')->count());
        $this->assertEquals(165.00, Produto::withoutGlobalScopes()->where('descricao', 'Vestido Cetim Perolado')->value('preco_venda'));

        // Outro produto logo em seguida grava normalmente
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), ['descricao' => 'Saia Tule', 'unidade_medida' => 'UN', 'preco_venda' => 77]);
        $this->assertDatabaseHas('produtos', ['descricao' => 'Saia Tule']);

        // Passados os 10 segundos, o mesmo nome volta a ser aceito (produto de mesmo nome é permitido)
        $this->travel(11)->seconds();
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.produtos.store'), $dados + ['preco_venda' => 165.00]);
        $this->assertSame(2, Produto::withoutGlobalScopes()->where('descricao', 'Vestido Cetim Perolado')->count());
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

    public function test_dono_altera_preco_pela_lista_e_markup_acompanha(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa, ['preco_custo' => 50.00, 'preco_venda' => 100.00]);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->patchJson(route('app.produtos.preco', $produto), ['preco_venda' => 80.00]);

        $response->assertOk()->assertJson(['preco_formatado' => 'R$ 80,00']);

        $produto->refresh();
        $this->assertEquals('80.00', $produto->preco_venda);
        $this->assertEquals('60.00', $produto->markup);
    }

    public function test_lista_mostra_preco_editavel_so_para_quem_edita(): void
    {
        $this->createProduto($this->empresa);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');

        $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.produtos.index'))
            ->assertSee('preco-inline', false);

        $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.produtos.index'))
            ->assertDontSee('preco-inline', false);
    }

    public function test_lista_avisa_preco_fixo_por_forma(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $comFixo = $this->createProduto($this->empresa, ['descricao' => 'Com Fixo']);
        foreach (['debito' => 120, 'credito' => 120, 'atacado' => 90] as $modalidade => $valor) {
            $comFixo->precos()->create(['empresa_id' => $this->empresa->id, 'modalidade' => $modalidade, 'valor' => $valor]);
        }
        $this->createProduto($this->empresa, ['descricao' => 'Sem Fixo']);

        $response = $this->actingAsUser($dono, $this->unidade)->get(route('app.produtos.index'));

        $response->assertSee('Cartão R$ 120,00');
        $response->assertSee('Atacado R$ 90,00');
        $this->assertSame(1, substr_count($response->getContent(), 'bi-exclamation-triangle me-1'));
    }

    public function test_preco_pela_lista_exige_valor_valido(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa);

        $this->actingAsUser($dono, $this->unidade)
            ->patchJson(route('app.produtos.preco', $produto), ['preco_venda' => -1])
            ->assertStatus(422);

        $this->assertEquals('100.00', $produto->fresh()->preco_venda);
    }

    public function test_vendedor_e_caixa_nao_alteram_preco_pela_lista(): void
    {
        $produto = $this->createProduto($this->empresa);

        foreach (['vendedor', 'caixa'] as $perfil) {
            $user = $this->createUser($this->empresa, $this->unidade, $perfil);

            $this->actingAsUser($user, $this->unidade)
                ->patchJson(route('app.produtos.preco', $produto), ['preco_venda' => 1.00])
                ->assertStatus(403);
        }

        $this->assertEquals('100.00', $produto->fresh()->preco_venda);
    }

    public function test_preco_pela_lista_nao_altera_produto_de_outra_empresa(): void
    {
        [$empresaB] = $this->createTenant('B');
        $produtoB = $this->createProduto($empresaB);
        $donoA = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($donoA, $this->unidade)
            ->patchJson(route('app.produtos.preco', $produtoB), ['preco_venda' => 1.00])
            ->assertNotFound();

        $this->assertEquals('100.00', Produto::withoutGlobalScopes()->find($produtoB->id)->preco_venda);
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
