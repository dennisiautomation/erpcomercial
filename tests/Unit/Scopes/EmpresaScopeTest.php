<?php

namespace Tests\Unit\Scopes;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_empresa_scope_filters_by_authenticated_user_empresa(): void
    {
        $empresa1 = Empresa::create([
            'cnpj'              => '12.345.678/0001-99',
            'razao_social'      => 'Empresa 1 LTDA',
            'regime_tributario' => 'simples_nacional',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '100',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11999999999',
            'email'             => 'empresa1@teste.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ]);

        $empresa2 = Empresa::create([
            'cnpj'              => '98.765.432/0001-10',
            'razao_social'      => 'Empresa 2 LTDA',
            'regime_tributario' => 'lucro_presumido',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '200',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11988888888',
            'email'             => 'empresa2@teste.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ]);

        Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $empresa1->id,
            'nome'       => 'Unidade Empresa 1',
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

        Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $empresa2->id,
            'nome'       => 'Unidade Empresa 2',
            'cnpj'       => '98.765.432/0001-10',
            'cep'        => '01001000',
            'logradouro' => 'Rua Teste',
            'numero'     => '200',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'uf'         => 'SP',
            'telefone'   => '11988888888',
            'status'     => 'ativa',
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa1->id,
            'perfil'     => Perfil::Vendedor,
        ]);

        $this->actingAs($user);

        // The EmpresaScope should filter Unidade by the authenticated user's empresa_id
        $unidades = Unidade::all();

        $this->assertCount(1, $unidades);
        $this->assertEquals('Unidade Empresa 1', $unidades->first()->nome);
    }

    public function test_empresa_scope_does_not_apply_when_no_auth(): void
    {
        $empresa1 = Empresa::create([
            'cnpj'              => '12.345.678/0001-99',
            'razao_social'      => 'Empresa 1 LTDA',
            'regime_tributario' => 'simples_nacional',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '100',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11999999999',
            'email'             => 'empresa1@teste.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ]);

        $empresa2 = Empresa::create([
            'cnpj'              => '98.765.432/0001-10',
            'razao_social'      => 'Empresa 2 LTDA',
            'regime_tributario' => 'lucro_presumido',
            'cep'               => '01001000',
            'logradouro'        => 'Rua Teste',
            'numero'            => '200',
            'bairro'            => 'Centro',
            'cidade'            => 'São Paulo',
            'uf'                => 'SP',
            'telefone'          => '11988888888',
            'email'             => 'empresa2@teste.com',
            'plano'             => 'profissional',
            'status'            => 'ativo',
        ]);

        Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $empresa1->id,
            'nome'       => 'Unidade Empresa 1',
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

        Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $empresa2->id,
            'nome'       => 'Unidade Empresa 2',
            'cnpj'       => '98.765.432/0001-10',
            'cep'        => '01001000',
            'logradouro' => 'Rua Teste',
            'numero'     => '200',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'uf'         => 'SP',
            'telefone'   => '11988888888',
            'status'     => 'ativa',
        ]);

        // Without authentication, the scope should not filter (both unidades visible)
        $unidades = Unidade::all();

        $this->assertCount(2, $unidades);
    }
}
