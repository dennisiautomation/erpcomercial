<?php

namespace Tests\Unit\Middleware;

use App\Enums\Perfil;
use App\Http\Middleware\CheckPermission;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckPermissionTest extends TestCase
{
    use RefreshDatabase;

    private CheckPermission $middleware;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new CheckPermission();

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

    private function createUserWithPerfil(Perfil $perfil, bool $isAdmin = false): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil'     => $perfil,
            'is_admin'   => $isAdmin,
        ]);
    }

    private function makeRequest(?User $user = null): Request
    {
        $request = Request::create('/test', 'GET');

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    private function passThrough(): \Closure
    {
        return fn ($request) => new Response('OK');
    }

    public function test_admin_has_access_to_everything(): void
    {
        $admin = $this->createUserWithPerfil(Perfil::Admin, true);
        $request = $this->makeRequest($admin);

        $response = $this->middleware->handle($request, $this->passThrough(), 'empresas', 'excluir');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_dono_can_access_empresa_modules(): void
    {
        $dono = $this->createUserWithPerfil(Perfil::Dono);
        $request = $this->makeRequest($dono);

        // Dono can manage unidades
        $response = $this->middleware->handle($request, $this->passThrough(), 'unidades', 'criar');
        $this->assertEquals(200, $response->getStatusCode());

        // Dono can manage produtos
        $response = $this->middleware->handle($request, $this->passThrough(), 'produtos', 'editar');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_vendedor_can_view_products(): void
    {
        $vendedor = $this->createUserWithPerfil(Perfil::Vendedor);
        $request = $this->makeRequest($vendedor);

        $response = $this->middleware->handle($request, $this->passThrough(), 'produtos', 'ver');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_vendedor_cannot_delete_products(): void
    {
        $vendedor = $this->createUserWithPerfil(Perfil::Vendedor);
        $request = $this->makeRequest($vendedor);

        $this->expectException(HttpException::class);

        $this->middleware->handle($request, $this->passThrough(), 'produtos', 'excluir');
    }

    public function test_caixa_can_create_vendas(): void
    {
        $caixa = $this->createUserWithPerfil(Perfil::Caixa);
        $request = $this->makeRequest($caixa);

        $response = $this->middleware->handle($request, $this->passThrough(), 'vendas', 'criar');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_consulta_can_only_view(): void
    {
        $consulta = $this->createUserWithPerfil(Perfil::Consulta);
        $request = $this->makeRequest($consulta);

        // Can view produtos
        $response = $this->middleware->handle($request, $this->passThrough(), 'produtos', 'ver');
        $this->assertEquals(200, $response->getStatusCode());

        // Cannot create produtos
        $this->expectException(HttpException::class);
        $this->middleware->handle($request, $this->passThrough(), 'produtos', 'criar');
    }

    public function test_unauthenticated_user_gets_403(): void
    {
        $request = $this->makeRequest(null); // No user

        $this->expectException(HttpException::class);

        $this->middleware->handle($request, $this->passThrough(), 'produtos', 'ver');
    }
}
