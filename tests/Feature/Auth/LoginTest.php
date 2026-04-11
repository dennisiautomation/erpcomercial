<?php

namespace Tests\Feature\Auth;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $unidade = Unidade::create([
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

        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
            'password'   => bcrypt('senha123'),
        ]);

        $user->unidades()->attach($unidade->id);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'senha123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
            'password'   => bcrypt('senha123'),
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'senhaerrada',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => true,
            'perfil'     => Perfil::Admin,
            'password'   => bcrypt('senha123'),
        ]);

        $response = $this->post('/login', [
            'email'    => $admin->email,
            'password' => 'senha123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_empresa_user_redirected_to_selecionar_unidade(): void
    {
        $unidade1 = Unidade::create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Unidade 1',
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

        $unidade2 = Unidade::create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Unidade 2',
            'cnpj'       => '12.345.678/0002-99',
            'cep'        => '01001000',
            'logradouro' => 'Rua Teste',
            'numero'     => '200',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'uf'         => 'SP',
            'telefone'   => '11999999998',
            'status'     => 'ativa',
        ]);

        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
            'password'   => bcrypt('senha123'),
        ]);

        $user->unidades()->attach([$unidade1->id, $unidade2->id]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'senha123',
        ]);

        $response->assertRedirect(route('selecionar-unidade'));
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
