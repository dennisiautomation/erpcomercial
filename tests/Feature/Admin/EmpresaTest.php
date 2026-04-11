<?php

namespace Tests\Feature\Admin;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Empresa $empresa;

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

        $this->regularUser = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => false,
            'perfil'     => Perfil::Vendedor,
        ]);
    }

    public function test_admin_can_list_empresas(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.empresas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.empresas.index');
        $response->assertViewHas('empresas');
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.empresas.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_empresa(): void
    {
        $empresaData = [
            'cnpj'              => '98.765.432/0001-10',
            'razao_social'      => 'Nova Empresa LTDA',
            'nome_fantasia'     => 'Nova Empresa',
            'regime_tributario' => 'lucro_presumido',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Nova',
            'numero'            => '200',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11988888888',
            'email'             => 'nova@empresa.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.empresas.store'), $empresaData);

        $response->assertRedirect();
        $this->assertDatabaseHas('empresas', [
            'cnpj'         => '98.765.432/0001-10',
            'razao_social' => 'Nova Empresa LTDA',
        ]);
    }

    public function test_admin_can_view_empresa(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.empresas.show', $this->empresa));

        $response->assertStatus(200);
        $response->assertViewIs('admin.empresas.show');
        $response->assertViewHas('empresa');
    }

    public function test_admin_can_update_empresa(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.empresas.update', $this->empresa), [
                'cnpj'              => '12.345.678/0001-99',
                'razao_social'      => 'Empresa Atualizada LTDA',
                'nome_fantasia'     => 'Empresa Atualizada',
                'regime_tributario' => 'lucro_real',
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

        $response->assertRedirect();
        $this->assertDatabaseHas('empresas', [
            'id'           => $this->empresa->id,
            'razao_social' => 'Empresa Atualizada LTDA',
        ]);
    }

    public function test_admin_can_delete_empresa(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('admin.empresas.destroy', $this->empresa));

        $response->assertRedirect(route('admin.empresas.index'));
        $this->assertSoftDeleted('empresas', [
            'id' => $this->empresa->id,
        ]);
    }

    public function test_empresa_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.empresas.store'), []);

        $response->assertSessionHasErrors(['cnpj', 'razao_social', 'regime_tributario', 'status']);
    }

    public function test_empresa_cnpj_must_be_unique(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.empresas.store'), [
                'cnpj'              => '12.345.678/0001-99', // Already exists
                'razao_social'      => 'Outra Empresa LTDA',
                'regime_tributario' => 'simples_nacional',
                'cep'               => '01001000',
                'logradouro'        => 'Rua Teste',
                'numero'            => '100',
                'bairro'            => 'Centro',
                'cidade'            => 'São Paulo',
                'uf'                => 'SP',
                'telefone'          => '11999999999',
                'email'             => 'outra@empresa.com',
                'plano'             => 'profissional',
                'status'            => 'ativo',
            ]);

        $response->assertSessionHasErrors('cnpj');
    }
}
