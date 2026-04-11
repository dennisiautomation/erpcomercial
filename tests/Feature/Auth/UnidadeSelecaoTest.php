<?php

namespace Tests\Feature\Auth;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnidadeSelecaoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $user;
    private Unidade $unidade1;
    private Unidade $unidade2;

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

        $this->unidade1 = Unidade::create([
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

        $this->unidade2 = Unidade::create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Unidade Filial',
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

        $this->user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
        ]);

        $this->user->unidades()->attach([$this->unidade1->id, $this->unidade2->id]);
    }

    public function test_user_sees_available_unidades(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('selecionar-unidade'));

        $response->assertStatus(200);
        $response->assertViewIs('selecionar-unidade');
        $response->assertViewHas('unidades');
    }

    public function test_user_can_select_unidade(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('selecionar-unidade.store'), [
                'unidade_id' => $this->unidade1->id,
            ]);

        $response->assertRedirect(route('app.dashboard'));
    }

    public function test_unidade_stored_in_session(): void
    {
        $this->actingAs($this->user)
            ->post(route('selecionar-unidade.store'), [
                'unidade_id' => $this->unidade1->id,
            ]);

        $this->assertEquals($this->unidade1->id, session('unidade_id'));
    }

    public function test_redirect_to_dashboard_after_selection(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('selecionar-unidade.store'), [
                'unidade_id' => $this->unidade2->id,
            ]);

        $response->assertRedirect(route('app.dashboard'));
        $this->assertEquals($this->unidade2->id, session('unidade_id'));
    }
}
