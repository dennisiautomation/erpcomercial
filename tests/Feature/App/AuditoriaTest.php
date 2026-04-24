<?php

namespace Tests\Feature\App;

use App\Models\Cliente;
use App\Models\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_criacao_de_cliente_gera_activity(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($dono, $this->unidade);

        Cliente::create([
            'empresa_id'        => $this->empresa->id,
            'tipo_pessoa'       => 'pf',
            'cpf_cnpj'          => '12345678901',
            'nome_razao_social' => 'Cliente Teste',
            'status'            => 'ativo',
        ]);

        $activity = Activity::where('log_name', 'Cliente')->where('event', 'created')->first();

        $this->assertNotNull($activity, 'Activity de criação de Cliente deveria existir');
        $this->assertEquals('Cliente criado', $activity->description);
        $this->assertEquals($this->empresa->id, $activity->properties['empresa_id']);
        $this->assertEquals($dono->id, $activity->causer_id);
    }

    public function test_alteracao_de_produto_registra_apenas_campos_alterados(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa);

        $this->actingAsUser($dono, $this->unidade);

        $produto->update(['preco_venda' => 99.99]);

        $activity = Activity::where('log_name', 'Produto')
            ->where('event', 'updated')
            ->latest()->first();

        $this->assertNotNull($activity);
        $changes = $activity->attribute_changes->toArray();
        $this->assertArrayHasKey('preco_venda', $changes['attributes']);
        $this->assertEquals('99.99', (string) $changes['attributes']['preco_venda']);
    }

    public function test_dono_pode_acessar_pagina_de_auditoria(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.auditoria.index'));

        $response->assertStatus(200);
        $response->assertViewIs('app.auditoria.index');
    }

    public function test_vendedor_nao_pode_acessar_auditoria(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');

        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.auditoria.index'));

        $response->assertForbidden();
    }

    public function test_auditoria_filtra_por_empresa_logada(): void
    {
        // Empresa A cria atividade
        $donoA = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->actingAsUser($donoA, $this->unidade);
        Cliente::create([
            'empresa_id'        => $this->empresa->id,
            'tipo_pessoa'       => 'pf',
            'cpf_cnpj'          => '11111111111',
            'nome_razao_social' => 'Cliente Empresa A',
            'status'            => 'ativo',
        ]);

        // Empresa B cria atividade
        [$empresaB, $unidadeB] = $this->createTenant('b');
        $donoB = $this->createUser($empresaB, $unidadeB, 'dono');
        $this->actingAsUser($donoB, $unidadeB);
        Cliente::create([
            'empresa_id'        => $empresaB->id,
            'tipo_pessoa'       => 'pf',
            'cpf_cnpj'          => '22222222222',
            'nome_razao_social' => 'Cliente Empresa B',
            'status'            => 'ativo',
        ]);

        // Dono A acessa auditoria — vê só os eventos da empresa A
        $response = $this->actingAsUser($donoA, $this->unidade)
            ->get(route('app.auditoria.index'));

        $response->assertStatus(200);
        $response->assertSee('Cliente Empresa A', false);
        $response->assertDontSee('Cliente Empresa B', false);
    }
}
