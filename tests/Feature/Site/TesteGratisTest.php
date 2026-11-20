<?php

namespace Tests\Feature\Site;

use App\Mail\ConfirmarCadastroSite;
use App\Mail\NovoCadastroSite;
use App\Models\CadastroSite;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Plataforma\ContratacaoSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Autocontratação pelo site (20/11/2026): chave, "sob consulta", cadastro, confirmação e bloqueio. */
class TesteGratisTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->plano = Plano::create([
            'nome' => 'Basico', 'slug' => 'basico', 'preco_mensal' => 590, 'preco_anual' => 5600,
            'max_unidades' => 1, 'max_usuarios' => 2, 'max_produtos' => 1, 'max_notas_mes' => 100,
            'dias_trial' => 14, 'ativo' => true, 'ordem' => 1,
            'pdv_habilitado' => true, 'fiscal_habilitado' => true,
        ]);
    }

    private function dados(array $troca = []): array
    {
        return array_merge([
            'nome' => 'Maria Teste', 'email' => 'Maria@Loja.com', 'whatsapp' => '(86) 99999-0000',
            'senha' => 'segredo-123', 'senha_confirmation' => 'segredo-123',
            'cnpj' => '11.222.333/0001-81', 'razao_social' => 'Loja Teste LTDA', 'nome_fantasia' => 'Loja Teste',
            'regime_tributario' => 'simples_nacional', 'cep' => '64000-000', 'logradouro' => 'Rua A',
            'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'Teresina', 'uf' => 'pi',
            'plano' => 'basico', 'aceite' => '1',
        ], $troca);
    }

    /** Envia o cadastro e devolve o link de confirmação que foi no e-mail. */
    private function cadastrar(array $troca = []): string
    {
        Mail::fake();
        $this->post('/teste-gratis', $this->dados($troca))->assertRedirect(route('site.teste.enviado'));
        $link = null;
        Mail::assertSent(ConfirmarCadastroSite::class, function ($m) use (&$link) {
            $link = $m->link;

            return true;
        });

        return $link;
    }

    public function test_chave_desligada_site_sem_preco_e_cadastro_fechado(): void
    {
        $this->assertFalse(ContratacaoSite::ativa());

        $html = $this->get('/')->assertOk()->assertViewIs('site.redes')->getContent();
        $this->assertStringContainsString('Sob consulta', $html);
        $this->assertStringNotContainsString('R$ 590', $html);
        $this->assertStringNotContainsString('teste-gratis', $html);

        $this->get('/teste-gratis')->assertRedirect(route('site.home') . '#demo');
        Mail::fake();
        $this->post('/teste-gratis', $this->dados())->assertRedirect(route('site.home') . '#demo');
        Mail::assertNothingSent();
        $this->assertSame(0, CadastroSite::count());
        $this->assertSame(0, Empresa::withoutGlobalScopes()->count());
    }

    public function test_chave_ligada_site_mostra_preco_e_botao_de_teste(): void
    {
        ContratacaoSite::definir(true);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('R$ 590', $html);
        $this->assertStringContainsString('/teste-gratis?plano=basico', $html);
        $this->assertStringNotContainsString('data-plano-consulta', $html);

        $this->get('/teste-gratis?plano=basico')->assertOk()->assertViewIs('site.teste-gratis')->assertSee('14 dias grátis');
    }

    public function test_cadastro_so_cria_a_empresa_depois_de_confirmar_o_email(): void
    {
        ContratacaoSite::definir(true);
        $link = $this->cadastrar();

        $this->assertSame(1, CadastroSite::count());
        $this->assertSame(0, Empresa::withoutGlobalScopes()->count());
        $this->assertSame(0, User::withoutGlobalScopes()->count());

        $this->get($link)->assertRedirect(route('app.dashboard'));

        $empresa = Empresa::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('site', $empresa->origem_cadastro);
        $this->assertTrue($empresa->em_trial);
        $this->assertSame(now()->addDays(14)->toDateString(), $empresa->trial_fim->toDateString());
        $this->assertSame($this->plano->id, $empresa->plano_id);
        $this->assertTrue($empresa->isAssinaturaAtiva());
        $this->assertSame('PI', $empresa->uf);

        $user = User::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('maria@loja.com', $user->email);
        $this->assertTrue($user->isDono());
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, Unidade::withoutGlobalScopes()->where('empresa_id', $empresa->id)->count());
        $this->assertSame(1, $user->unidades()->count());
        $this->assertAuthenticatedAs($user);
        Mail::assertSent(NovoCadastroSite::class);

        // o mesmo link de novo não cria outra empresa
        $this->get($link)->assertOk()->assertSee('já foi confirmado');
        $this->assertSame(1, Empresa::withoutGlobalScopes()->count());

        // e a senha escolhida entra pela tela de login
        auth()->logout();
        $this->assertTrue(auth()->attempt(['email' => 'maria@loja.com', 'password' => 'segredo-123']));
    }

    public function test_recusa_cnpj_invalido_senha_curta_e_quem_ja_e_cliente(): void
    {
        ContratacaoSite::definir(true);
        Mail::fake();

        $this->post('/teste-gratis', $this->dados(['cnpj' => '11.222.333/0001-00']))->assertSessionHasErrors('cnpj');
        $this->post('/teste-gratis', $this->dados(['senha' => '123', 'senha_confirmation' => '123']))->assertSessionHasErrors('senha');
        $this->post('/teste-gratis', $this->dados(['aceite' => null]))->assertSessionHasErrors('aceite');
        $this->post('/teste-gratis', $this->dados(['plano' => 'nao-existe']))->assertSessionHasErrors('plano');
        $this->post('/teste-gratis', $this->dados(['site' => 'robo']))->assertSessionHasErrors('site');
        $this->assertSame(0, CadastroSite::count());

        $this->get($this->cadastrar());
        auth()->logout();

        Mail::fake();
        $this->post('/teste-gratis', $this->dados(['cnpj' => '11.444.777/0001-61']))->assertSessionHasErrors('email');
        $this->post('/teste-gratis', $this->dados(['email' => 'outra@loja.com']))->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        $this->assertSame(1, Empresa::withoutGlobalScopes()->count());
    }

    public function test_link_vencido_ou_invalido_nao_cria_nada(): void
    {
        ContratacaoSite::definir(true);
        $link = $this->cadastrar();
        CadastroSite::query()->update(['expira_em' => now()->subMinute()]);

        $this->get($link)->assertOk()->assertSee('Este link venceu');
        $this->get('/teste-gratis/confirmar/' . str_repeat('x', 64))->assertOk()->assertSee('não é válido');
        $this->assertSame(0, Empresa::withoutGlobalScopes()->count());
    }

    public function test_teste_vencido_bloqueia_o_sistema_inteiro_so_de_quem_veio_do_site(): void
    {
        ContratacaoSite::definir(true);
        $this->get($this->cadastrar());
        $empresa = Empresa::withoutGlobalScopes()->firstOrFail();
        $user = User::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($user->unidades()->value('unidades.id'), session('unidade_id')); // entrou direto na Matriz
        $this->withSession(['unidade_id' => session('unidade_id'), 'empresa_id' => $empresa->id]);

        // dentro do teste: entra
        $this->actingAs($user)->get('/app/produtos')->assertOk();

        // teste vencido: tudo cai na tela de plano, que abre
        $empresa->forceFill(['trial_fim' => now()->subDay()])->save();
        $this->actingAs($user->fresh())->get('/app/produtos')->assertRedirect(route('app.plano-expirado'));
        $this->actingAs($user->fresh())->get('/app/dashboard')->assertRedirect(route('app.plano-expirado'));
        $this->actingAs($user->fresh())->get(route('app.plano-expirado'))->assertOk();

        // a mesma situação numa empresa criada pelo admin NÃO bloqueia (comportamento antigo mantido)
        $empresa->forceFill(['origem_cadastro' => 'admin'])->save();
        $this->actingAs($user->fresh())->get('/app/produtos')->assertOk();

        // admin estende o teste: volta a entrar
        $empresa->forceFill(['origem_cadastro' => 'site'])->save();
        $empresa->fresh()->estenderTrial(30);
        $this->actingAs($user->fresh())->get('/app/produtos')->assertOk();
    }

    public function test_limite_de_usuarios_do_plano_barra_so_quem_veio_do_site(): void
    {
        ContratacaoSite::definir(true);
        $this->get($this->cadastrar());
        $empresa = Empresa::withoutGlobalScopes()->firstOrFail();

        $this->assertFalse($empresa->limiteDoPlanoBarra('usuarios')); // 1 de 2
        $this->assertFalse($empresa->limiteDoPlanoBarra('produtos')); // 0 de 1

        $this->plano->update(['max_usuarios' => 1]);
        $this->assertTrue($empresa->fresh()->limiteDoPlanoBarra('usuarios'));

        $empresa->forceFill(['origem_cadastro' => 'admin'])->save();
        $this->assertFalse($empresa->fresh()->limiteDoPlanoBarra('usuarios'));
    }

    public function test_so_admin_liga_a_chave(): void
    {
        $this->post(route('admin.planos.contratacao-site'), ['ativa' => 1])->assertRedirect(route('login'));
        $this->assertFalse(ContratacaoSite::ativa());

        $admin = User::factory()->create(['is_admin' => true, 'empresa_id' => null]);
        $this->actingAs($admin)->post(route('admin.planos.contratacao-site'), ['ativa' => 1])->assertRedirect();
        $this->assertTrue(ContratacaoSite::ativa());
        $this->actingAs($admin)->get(route('admin.planos.index'))->assertOk()->assertSee('Contratação pelo site');
        $this->actingAs($admin)->post(route('admin.planos.contratacao-site'), ['ativa' => 0]);
        $this->assertFalse(ContratacaoSite::ativa());
    }
}
