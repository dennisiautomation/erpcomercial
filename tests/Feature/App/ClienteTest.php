<?php

namespace Tests\Feature\App;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ClienteTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    /* ------------------------------------------------------------------
     *  CRUD - Dono
     * ------------------------------------------------------------------ */

    public function test_dono_can_list_clientes(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->createCliente($this->empresa);
        $this->createCliente($this->empresa);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);
        $response->assertViewHas('clientes');
    }

    public function test_dono_can_create_cliente_pf(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.clientes.store'), [
                'tipo_pessoa'       => 'pf',
                'cpf_cnpj'          => '123.456.789-00',
                'nome_razao_social' => 'Joao da Silva',
                'email'             => 'joao@teste.com',
                'telefone'          => '(11) 99999-0000',
                'cep'               => '01001000',
                'logradouro'        => 'Rua Teste',
                'numero'            => '100',
                'bairro'            => 'Centro',
                'cidade'            => 'São Paulo',
                'uf'                => 'SP',
            ]);

        $response->assertRedirect(route('app.clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'empresa_id'       => $this->empresa->id,
            'cpf_cnpj'         => '123.456.789-00',
            'nome_razao_social' => 'Joao da Silva',
            'tipo_pessoa'      => 'pf',
            'status'           => 'ativo',
        ]);
    }

    public function test_dono_can_create_cliente_pj(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.clientes.store'), [
                'tipo_pessoa'       => 'pj',
                'cpf_cnpj'          => '12.345.678/0001-99',
                'nome_razao_social' => 'Empresa XYZ Ltda',
                'nome_fantasia'     => 'XYZ',
                'telefone'          => '(11) 99999-0000',
                'cep'               => '01001000',
                'logradouro'        => 'Rua Teste',
                'numero'            => '200',
                'bairro'            => 'Centro',
                'cidade'            => 'São Paulo',
                'uf'                => 'SP',
            ]);

        $response->assertRedirect(route('app.clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'empresa_id'       => $this->empresa->id,
            'cpf_cnpj'         => '12.345.678/0001-99',
            'tipo_pessoa'      => 'pj',
        ]);
    }

    public function test_cpf_cnpj_unique_per_empresa(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        // Create first cliente
        $this->createCliente($this->empresa, ['cpf_cnpj' => '999.999.999-99']);

        // Try to create another with the same cpf_cnpj in the same empresa
        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.clientes.store'), [
                'tipo_pessoa'       => 'pf',
                'cpf_cnpj'          => '999.999.999-99',
                'nome_razao_social' => 'Outro Cliente',
                'telefone'          => '11999999999',
                'cep'               => '01001000',
                'logradouro'        => 'Rua Teste',
                'numero'            => '100',
                'bairro'            => 'Centro',
                'cidade'            => 'São Paulo',
                'uf'                => 'SP',
            ]);

        $response->assertSessionHasErrors('cpf_cnpj');
    }

    public function test_dono_can_update_cliente(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->put(route('app.clientes.update', $cliente), [
                'tipo_pessoa'       => 'pf',
                'cpf_cnpj'          => $cliente->cpf_cnpj,
                'nome_razao_social' => 'Nome Atualizado',
                'status'            => 'ativo',
            ]);

        $response->assertRedirect(route('app.clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'id'                => $cliente->id,
            'nome_razao_social' => 'Nome Atualizado',
        ]);
    }

    public function test_dono_can_delete_cliente(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->delete(route('app.clientes.destroy', $cliente));

        $response->assertRedirect(route('app.clientes.index'));
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    /* ------------------------------------------------------------------
     *  Permissions
     * ------------------------------------------------------------------ */

    public function test_vendedor_can_view_clientes(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $this->createCliente($this->empresa);

        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);
    }

    public function test_caixa_can_only_view_clientes(): void
    {
        $caixa = $this->createUser($this->empresa, $this->unidade, 'caixa');

        // Can view (caixa has 'ver' permission for clientes)
        $response = $this->actingAsUser($caixa, $this->unidade)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);

        // Caixa has 'ver' permission for clientes, but should NOT have
        // access to modules not assigned to them (e.g., estoque criar)
        $produto = $this->createProduto($this->empresa);

        $response = $this->actingAsUser($caixa, $this->unidade)
            ->post(route('app.movimentacoes.store'), [
                'produto_id' => $produto->id,
                'tipo'       => 'entrada',
                'quantidade' => 10,
            ]);

        // Caixa does not have 'ver' permission on 'estoque' module
        $response->assertStatus(403);
    }

    /* ------------------------------------------------------------------
     *  Multi-Tenant Isolation
     * ------------------------------------------------------------------ */

    public function test_cliente_data_isolated_by_empresa(): void
    {
        [$empresaB, $unidadeB] = $this->createTenant('B');

        // Create cliente in empresa A
        $clienteA = $this->createCliente($this->empresa, ['nome_razao_social' => 'Cliente Empresa A']);

        // Create cliente in empresa B
        $clienteB = $this->createCliente($empresaB, ['nome_razao_social' => 'Cliente Empresa B']);

        // Dono of empresa A should only see their clientes
        $donoA = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($donoA, $this->unidade)
            ->get(route('app.clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('Cliente Empresa A');
        $response->assertDontSee('Cliente Empresa B');
    }
}
