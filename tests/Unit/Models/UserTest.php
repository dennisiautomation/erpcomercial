<?php

namespace Tests\Unit\Models;

use App\Enums\Perfil;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
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

    public function test_user_belongs_to_empresa(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $user->empresa());
        $this->assertEquals($this->empresa->id, $user->empresa->id);
    }

    public function test_user_has_perfil_enum_cast(): void
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Gerente,
        ]);

        $this->assertInstanceOf(Perfil::class, $user->perfil);
        $this->assertEquals(Perfil::Gerente, $user->perfil);
    }

    public function test_is_admin_helper_method(): void
    {
        $admin = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => true,
            'perfil'     => Perfil::Admin,
        ]);

        $regular = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => false,
            'perfil'     => Perfil::Vendedor,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($regular->isAdmin());
    }

    public function test_is_dono_helper_method(): void
    {
        $dono = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Dono,
        ]);

        $notDono = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => Perfil::Vendedor,
        ]);

        $this->assertTrue($dono->isDono());
        $this->assertFalse($notDono->isDono());
    }

    public function test_has_permission_method(): void
    {
        $admin = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => true,
            'perfil'     => Perfil::Admin,
        ]);

        $dono = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin'   => false,
            'perfil'     => Perfil::Dono,
        ]);

        // Admin and Dono always have permission (returns true in hasPermission)
        $this->assertTrue($admin->hasPermission('produtos', 'excluir'));
        $this->assertTrue($dono->hasPermission('produtos', 'excluir'));
    }
}
