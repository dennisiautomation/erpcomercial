<?php

namespace Tests\Unit\Models;

use App\Enums\RegimeTributario;
use App\Enums\StatusEmpresa;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpresaTest extends TestCase
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

    public function test_empresa_has_fillable_attributes(): void
    {
        $fillable = (new Empresa())->getFillable();

        $this->assertContains('cnpj', $fillable);
        $this->assertContains('razao_social', $fillable);
        $this->assertContains('nome_fantasia', $fillable);
        $this->assertContains('regime_tributario', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('telefone', $fillable);
    }

    public function test_empresa_has_unidades_relationship(): void
    {
        Unidade::create([
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

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->empresa->unidades());
        $this->assertCount(1, $this->empresa->unidades);
    }

    public function test_empresa_has_users_relationship(): void
    {
        User::factory()->create(['empresa_id' => $this->empresa->id, 'perfil' => 'vendedor']);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->empresa->users());
        $this->assertCount(1, $this->empresa->users);
    }

    public function test_empresa_has_clientes_relationship(): void
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->empresa->clientes());
    }

    public function test_empresa_has_produtos_relationship(): void
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->empresa->produtos());
    }

    public function test_empresa_casts_status_to_enum(): void
    {
        $this->assertInstanceOf(StatusEmpresa::class, $this->empresa->status);
        $this->assertEquals(StatusEmpresa::Ativo, $this->empresa->status);
    }

    public function test_empresa_casts_regime_tributario_to_enum(): void
    {
        $this->assertInstanceOf(RegimeTributario::class, $this->empresa->regime_tributario);
        $this->assertEquals(RegimeTributario::SimplesNacional, $this->empresa->regime_tributario);
    }

    public function test_empresa_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(Empresa::class));

        $this->empresa->delete();

        $this->assertSoftDeleted('empresas', ['id' => $this->empresa->id]);
        $this->assertNotNull($this->empresa->fresh()?->deleted_at ?? Empresa::withTrashed()->find($this->empresa->id)->deleted_at);
    }
}
