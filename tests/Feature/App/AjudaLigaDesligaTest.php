<?php

namespace Tests\Feature\App;

use App\Enums\Perfil;
use App\Models\PlataformaConfiguracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Agente de ajuda — liga/desliga (22/11/2026, pedido do Dennis: "ligo para todos
 * os clientes mas posso desligar de alguns"):
 *  - AJUDA_ATIVO no .env = interruptor do servidor (sem ele, nada aparece);
 *  - Admin → Integrações: ligado ou desligado PARA TODAS as empresas;
 *  - Admin → Empresa: exceção — "segue o geral", "sempre ligado" ou "desligado".
 */
class AjudaLigaDesligaTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $empresaA;
    private $unidadeA;
    private $empresaB;
    private $unidadeB;
    private $donoA;
    private $donoB;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ajuda.ativo' => true, 'ajuda.llm.api_key' => 'teste']);
        [$this->empresaA, $this->unidadeA] = $this->createTenant('A');
        [$this->empresaB, $this->unidadeB] = $this->createTenant('B');
        $this->donoA = $this->createUser($this->empresaA, $this->unidadeA, 'dono');
        $this->donoB = $this->createUser($this->empresaB, $this->unidadeB, 'dono');
    }

    private function veBotao($user, $unidade): bool
    {
        $html = $this->actingAsUser($user, $unidade)->get(route('app.dashboard'))->assertOk()->getContent();

        return str_contains($html, 'id="ajBotao"');
    }

    private function rota($user, $unidade): int
    {
        return $this->actingAsUser($user, $unidade)->getJson(route('app.ajuda.atual'))->status();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresaA->id, 'is_admin' => true, 'perfil' => Perfil::Admin,
        ]);
    }

    public function test_padrao_e_ligado_para_todas(): void
    {
        $this->assertTrue($this->veBotao($this->donoA, $this->unidadeA));
        $this->assertTrue($this->veBotao($this->donoB, $this->unidadeB));
        $this->assertSame(200, $this->rota($this->donoA, $this->unidadeA));
    }

    public function test_ligado_para_todas_menos_a_empresa_desligada(): void
    {
        $this->empresaA->update(['ajuda_modo' => 'desligado']);

        $this->assertFalse($this->veBotao($this->donoA, $this->unidadeA));
        $this->assertSame(404, $this->rota($this->donoA, $this->unidadeA));
        $this->assertTrue($this->veBotao($this->donoB, $this->unidadeB));
        $this->assertSame(200, $this->rota($this->donoB, $this->unidadeB));
    }

    public function test_desligado_para_todas_menos_a_empresa_ligada(): void
    {
        PlataformaConfiguracao::set('ajuda_todas', '0');
        $this->empresaB->update(['ajuda_modo' => 'ligado']);

        $this->assertFalse($this->veBotao($this->donoA, $this->unidadeA));
        $this->assertSame(404, $this->rota($this->donoA, $this->unidadeA));
        $this->assertTrue($this->veBotao($this->donoB, $this->unidadeB));
        $this->assertSame(200, $this->rota($this->donoB, $this->unidadeB));
    }

    public function test_sem_ajuda_ativo_no_servidor_nada_aparece_nem_com_empresa_ligada(): void
    {
        config(['ajuda.ativo' => false]);
        $this->empresaA->update(['ajuda_modo' => 'ligado']);

        $this->assertFalse($this->veBotao($this->donoA, $this->unidadeA));
        $this->assertSame(404, $this->rota($this->donoA, $this->unidadeA));
    }

    public function test_admin_liga_e_desliga_para_todas_na_tela_de_integracoes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.integracoes.index'))
            ->assertOk()->assertSee('Assistente de ajuda');

        $this->actingAs($admin)->post(route('admin.integracoes.ajuda.store'), ['ajuda_todas' => '0'])
            ->assertRedirect(route('admin.integracoes.index'));
        $this->assertSame('0', PlataformaConfiguracao::get('ajuda_todas'));
        $this->assertFalse($this->veBotao($this->donoB, $this->unidadeB));

        $this->actingAs($admin)->post(route('admin.integracoes.ajuda.store'), ['ajuda_todas' => '1']);
        $this->assertSame('1', PlataformaConfiguracao::get('ajuda_todas'));
        $this->assertTrue($this->veBotao($this->donoB, $this->unidadeB));
    }

    public function test_quem_nao_e_admin_nao_muda_a_chave_geral(): void
    {
        $this->actingAsUser($this->donoA, $this->unidadeA)
            ->post(route('admin.integracoes.ajuda.store'), ['ajuda_todas' => '0'])
            ->assertForbidden();
        $this->assertNull(PlataformaConfiguracao::get('ajuda_todas'));
    }

    public function test_configuracao_que_nao_decifra_nao_derruba_a_tela(): void
    {
        // Banco copiado de outro servidor (outra APP_KEY): valores cifrados não abrem.
        \Illuminate\Support\Facades\DB::table('plataforma_configuracoes')->insert([
            ['chave' => 'ajuda_todas', 'valor' => 'cifrado-com-outra-chave', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'melhor_envio_client_id', 'valor' => 'cifrado-com-outra-chave', 'created_at' => now(), 'updated_at' => now()],
        ]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->assertTrue($this->veBotao($this->donoA, $this->unidadeA));
    }

    public function test_admin_grava_a_excecao_na_empresa(): void
    {
        $admin = $this->admin();
        $e = $this->empresaB;
        $e->update(['cnpj' => '12.345.678/0001-95']); // o formulário do admin exige CNPJ válido
        $dados = [
            'cnpj' => $e->cnpj, 'razao_social' => $e->razao_social, 'nome_fantasia' => $e->nome_fantasia,
            'regime_tributario' => $e->regime_tributario?->value ?? 'simples_nacional', 'regime_cobranca' => 'padrao',
            'status' => 'ativo',
        ];

        $this->actingAs($admin)->get(route('admin.empresas.edit', $e))
            ->assertOk()->assertSee('Assistente de ajuda')->assertSee('Segue o geral');

        $this->actingAs($admin)->put(route('admin.empresas.update', $e), $dados + ['ajuda_modo' => 'desligado'])
            ->assertSessionHasNoErrors();
        $this->assertSame('desligado', $e->fresh()->ajuda_modo);
        $this->assertFalse($this->veBotao($this->donoB, $this->unidadeB));

        $this->actingAs($admin)->put(route('admin.empresas.update', $e), $dados + ['ajuda_modo' => '']);
        $this->assertNull($e->fresh()->ajuda_modo);
        $this->assertTrue($this->veBotao($this->donoB, $this->unidadeB));

        $this->actingAs($admin)->put(route('admin.empresas.update', $e), $dados + ['ajuda_modo' => 'qualquer'])
            ->assertSessionHasErrors('ajuda_modo');
    }
}
