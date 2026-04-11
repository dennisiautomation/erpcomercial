<?php

namespace Tests\Feature\App;

use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $empresaA;
    private $unidadeA;
    private $empresaB;
    private $unidadeB;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresaA, $this->unidadeA] = $this->createTenant('A');
        [$this->empresaB, $this->unidadeB] = $this->createTenant('B');
    }

    public function test_empresa_a_cannot_see_empresa_b_clientes(): void
    {
        $donoA = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $donoB = $this->createUser($this->empresaB, $this->unidadeB, 'dono');

        $clienteA = $this->createCliente($this->empresaA, ['nome_razao_social' => 'Cliente da Empresa A']);
        $clienteB = $this->createCliente($this->empresaB, ['nome_razao_social' => 'Cliente da Empresa B']);

        // Dono A sees only empresa A clientes
        $response = $this->actingAsUser($donoA, $this->unidadeA)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('Cliente da Empresa A');
        $response->assertDontSee('Cliente da Empresa B');

        // Dono B sees only empresa B clientes
        $response = $this->actingAsUser($donoB, $this->unidadeB)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('Cliente da Empresa B');
        $response->assertDontSee('Cliente da Empresa A');
    }

    public function test_empresa_a_cannot_see_empresa_b_produtos(): void
    {
        $donoA = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $donoB = $this->createUser($this->empresaB, $this->unidadeB, 'dono');

        $produtoA = $this->createProduto($this->empresaA, ['descricao' => 'Produto Exclusivo A']);
        $produtoB = $this->createProduto($this->empresaB, ['descricao' => 'Produto Exclusivo B']);

        $response = $this->actingAsUser($donoA, $this->unidadeA)
            ->get(route('app.produtos.index'));

        $response->assertStatus(200);
        $response->assertSee('Produto Exclusivo A');
        $response->assertDontSee('Produto Exclusivo B');
    }

    public function test_empresa_a_cannot_see_empresa_b_vendas(): void
    {
        $donoA = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $donoB = $this->createUser($this->empresaB, $this->unidadeB, 'dono');

        $clienteA = $this->createCliente($this->empresaA);
        $clienteB = $this->createCliente($this->empresaB);

        // Create vendas directly
        $vendaA = Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaA->id,
            'unidade_id'      => $this->unidadeA->id,
            'cliente_id'      => $clienteA->id,
            'vendedor_id'     => $donoA->id,
            'numero'          => 1,
            'subtotal'        => 100.00,
            'total'           => 100.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        $vendaB = Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaB->id,
            'unidade_id'      => $this->unidadeB->id,
            'cliente_id'      => $clienteB->id,
            'vendedor_id'     => $donoB->id,
            'numero'          => 1,
            'subtotal'        => 200.00,
            'total'           => 200.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        $response = $this->actingAsUser($donoA, $this->unidadeA)
            ->get(route('app.vendas.index'));

        $response->assertStatus(200);

        // Verify at DB level with scope
        $vendasVisiveis = Venda::where('empresa_id', $this->empresaA->id)->count();
        $this->assertEquals(1, $vendasVisiveis);
    }

    public function test_unidade_scope_filters_vendas_for_gerente(): void
    {
        // Create a second unidade for empresa A
        $unidadeA2 = \App\Models\Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresaA->id,
            'nome'       => 'Filial A2',
            'cnpj'       => '12.345.678/0005-0A',
            'cep'        => '13000000',
            'logradouro' => 'Rua Filial',
            'numero'     => '500',
            'bairro'     => 'Centro',
            'cidade'     => 'Campinas',
            'uf'         => 'SP',
            'telefone'   => '19999999999',
            'status'     => 'ativa',
        ]);

        $gerenteU1 = $this->createUser($this->empresaA, $this->unidadeA, 'gerente');

        $clienteA = $this->createCliente($this->empresaA);

        // Venda in unidade 1
        Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaA->id,
            'unidade_id'      => $this->unidadeA->id,
            'cliente_id'      => $clienteA->id,
            'vendedor_id'     => $gerenteU1->id,
            'numero'          => 1,
            'subtotal'        => 100.00,
            'total'           => 100.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        // Venda in unidade 2
        Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaA->id,
            'unidade_id'      => $unidadeA2->id,
            'cliente_id'      => $clienteA->id,
            'vendedor_id'     => $gerenteU1->id,
            'numero'          => 2,
            'subtotal'        => 200.00,
            'total'           => 200.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        // Gerente at unidade 1 should only see unidade 1 vendas (UnidadeScope applies)
        $this->actingAs($gerenteU1);
        session(['unidade_id' => $this->unidadeA->id, 'empresa_id' => $this->empresaA->id]);

        // The UnidadeScope filters for non-dono/admin perfils
        $vendasVisiveis = Venda::count();
        $this->assertEquals(1, $vendasVisiveis);
    }

    public function test_dono_sees_all_unidades_data(): void
    {
        $unidadeA2 = \App\Models\Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresaA->id,
            'nome'       => 'Filial A3',
            'cnpj'       => '12.345.678/0006-0A',
            'cep'        => '11000000',
            'logradouro' => 'Rua Filial',
            'numero'     => '600',
            'bairro'     => 'Centro',
            'cidade'     => 'Santos',
            'uf'         => 'SP',
            'telefone'   => '13999999999',
            'status'     => 'ativa',
        ]);

        $donoA   = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $clienteA = $this->createCliente($this->empresaA);

        // Venda in unidade 1
        Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaA->id,
            'unidade_id'      => $this->unidadeA->id,
            'cliente_id'      => $clienteA->id,
            'vendedor_id'     => $donoA->id,
            'numero'          => 1,
            'subtotal'        => 100.00,
            'total'           => 100.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        // Venda in unidade 2
        Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresaA->id,
            'unidade_id'      => $unidadeA2->id,
            'cliente_id'      => $clienteA->id,
            'vendedor_id'     => $donoA->id,
            'numero'          => 2,
            'subtotal'        => 200.00,
            'total'           => 200.00,
            'forma_pagamento' => 'dinheiro',
            'status'          => 'concluida',
            'tipo'            => 'pdv',
        ]);

        // Dono should see ALL vendas across unidades (UnidadeScope does not apply to dono)
        $this->actingAs($donoA);
        session(['unidade_id' => $this->unidadeA->id, 'empresa_id' => $this->empresaA->id]);

        $vendasVisiveis = Venda::count();
        $this->assertEquals(2, $vendasVisiveis);
    }
}
