<?php

namespace Tests\Feature\Admin;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnidadeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Empresa $empresa;
    private Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'cnpj'              => '12.345.678/0001-99',
            'razao_social'      => 'Empresa Teste LTDA',
            'nome_fantasia'     => 'Empresa Teste',
            'regime_tributario' => 'simples_nacional',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '100',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11999999999',
            'email'             => 'empresa@teste.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ]);

        $this->admin = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => true,
            'perfil'     => Perfil::Admin,
        ]);

        $this->unidade = Unidade::create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Unidade Matriz',
            'cnpj'       => '12.345.678/0001-99',
            'cep'        => '01001000',
            'logradouro' => 'Rua Teste',
            'numero'     => '100',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'uf'         => 'SP',
            'telefone'   => '11999999999',
            'status'     => 'ativa',
        ]);
    }

    public function test_admin_can_create_unidade_for_empresa(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.empresas.unidades.store', $this->empresa), [
                'nome'       => 'Nova Filial',
                'cnpj'       => '12.345.678/0003-99',
                'cep'        => '01001000',
                'logradouro' => 'Rua Nova',
                'numero'     => '300',
                'bairro'     => 'Centro',
                'cidade'     => 'São Paulo',
                'uf'         => 'SP',
                'telefone'   => '11988888888',
                'status'     => 'ativa',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('unidades', [
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Nova Filial',
            'status'     => 'ativa',
        ]);
    }

    public function test_admin_can_list_unidades(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.empresas.unidades.index', $this->empresa));

        $response->assertStatus(200);
        $response->assertViewIs('admin.unidades.index');
        $response->assertViewHas('unidades');
    }

    public function test_admin_can_update_unidade(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.unidades.update', $this->unidade), [
                'nome'       => 'Unidade Atualizada',
                'cnpj'       => '12.345.678/0001-99',
                'cep'        => '01001000',
                'logradouro' => 'Rua Teste',
                'numero'     => '100',
                'bairro'     => 'Centro',
                'cidade'     => 'São Paulo',
                'uf'         => 'SP',
                'telefone'   => '11999999999',
                'status'     => 'ativa',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('unidades', [
            'id'   => $this->unidade->id,
            'nome' => 'Unidade Atualizada',
        ]);
    }

    public function test_admin_can_delete_unidade(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.unidades.destroy', $this->unidade));

        $response->assertRedirect();
        $this->assertSoftDeleted('unidades', [
            'id' => $this->unidade->id,
        ]);
    }
}
