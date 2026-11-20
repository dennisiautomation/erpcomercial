<?php

namespace Tests\Feature\App;

use App\Models\Empresa;
use App\Models\Treinamento;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Treinamento separado por seção (20/11/2026): Sistema e Loja online na mesma tela, em blocos. */
class TreinamentoSecaoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $perfil): User
    {
        $empresa = Empresa::first() ?? Empresa::create(['cnpj' => '11222333000181', 'razao_social' => 'Loja LTDA', 'nome_fantasia' => 'Loja', 'regime_tributario' => 'simples_nacional',
            'email' => 'a@b.com', 'telefone' => '86999990000', 'cep' => '64000-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Teresina', 'uf' => 'PI', 'status' => 'ativo']);
        $unidade = Unidade::withoutGlobalScopes()->first() ?? Unidade::withoutGlobalScopes()->create(['empresa_id' => $empresa->id, 'nome' => 'Matriz', 'cnpj' => '11222333000181', 'status' => 'ativa',
            'cep' => '64000-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Teresina', 'uf' => 'PI', 'telefone' => '86999990000']);
        $u = User::factory()->create(['empresa_id' => $empresa->id, 'perfil' => $perfil, 'status' => 'ativo']);
        $u->unidades()->sync([$unidade->id]);
        $this->withSession(['unidade_id' => $unidade->id, 'empresa_id' => $empresa->id]);

        return $u;
    }

    public function test_importar_publica_na_secao_e_a_tela_separa_os_blocos(): void
    {
        Storage::fake('local');
        $video = tempnam(sys_get_temp_dir(), 'tr') . '.mp4';
        file_put_contents($video, str_repeat('x', 2048));

        Treinamento::create(['titulo' => 'Primeiros Passos', 'ordem' => 1, 'ativo' => true, 'perfis' => ['vendedor'], 'arquivo' => 'treinamentos/a.mp4', 'tamanho' => 10]);
        $this->artisan('treinamento:importar', ['arquivo' => $video, '--titulo' => '1. Configurar a loja', '--secao' => 'loja_online', '--ordem' => 101])->assertSuccessful();
        $this->artisan('treinamento:importar', ['arquivo' => $video, '--titulo' => '2. Produtos na loja', '--secao' => 'loja_online', '--ordem' => 102])->assertSuccessful();
        // de novo com o mesmo título: troca o vídeo, não duplica
        $antigo = Treinamento::where('titulo', '1. Configurar a loja')->value('arquivo');
        $this->artisan('treinamento:importar', ['arquivo' => $video, '--titulo' => '1. Configurar a loja', '--secao' => 'loja_online', '--ordem' => 101])->assertSuccessful();
        $this->assertSame(3, Treinamento::count());
        $t = Treinamento::where('titulo', '1. Configurar a loja')->firstOrFail();
        $this->assertSame(['loja_online', [], 2048, true], [$t->secao, $t->perfis, $t->tamanho, $t->ativo]);
        Storage::disk('local')->assertExists($t->arquivo);
        Storage::disk('local')->assertMissing($antigo);
        // recusa seção que não existe e arquivo que não é vídeo
        $this->artisan('treinamento:importar', ['arquivo' => $video, '--titulo' => 'X', '--secao' => 'outra'])->assertFailed();
        $this->artisan('treinamento:importar', ['arquivo' => '/nao/existe.mp4', '--titulo' => 'X'])->assertFailed();

        // dono: os dois blocos, o do sistema primeiro
        $this->actingAs($this->usuario('dono'))->get(route('app.treinamento.index'))->assertOk()
            ->assertSeeInOrder(['data-treino-secao="erp"', 'Primeiros Passos', 'data-treino-secao="loja_online"', '1. Configurar a loja', '2. Produtos na loja'], false);
        // vendedor: só o do perfil dele, sem título de bloco; o vídeo da loja não abre
        $vend = $this->usuario('vendedor');
        $this->actingAs($vend)->get(route('app.treinamento.index'))->assertOk()->assertSee('Primeiros Passos')
            ->assertDontSee('Configurar a loja')->assertDontSee('data-treino-secao', false);
        $this->actingAs($vend)->get(route('app.treinamento.video', $t))->assertNotFound();
    }

    public function test_admin_grava_a_secao_e_aceita_nenhum_perfil(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('treinamentos/11111111-1111-1111-1111-111111111111.mp4', 'x');
        $admin = User::factory()->create(['is_admin' => true, 'empresa_id' => null]);

        $this->actingAs($admin)->post(route('admin.treinamentos.store'), ['titulo' => 'Aula da loja', 'secao' => 'loja_online', 'ordem' => 5, 'ativo' => 1,
            'arquivo' => '11111111-1111-1111-1111-111111111111.mp4'])->assertSessionHasNoErrors();
        $t = Treinamento::firstOrFail();
        $this->assertSame(['loja_online', []], [$t->secao, $t->perfis]);

        $this->actingAs($admin)->put(route('admin.treinamentos.update', $t), ['titulo' => 'Aula da loja', 'secao' => 'inventada', 'ordem' => 5])->assertSessionHasErrors('secao');
        $this->actingAs($admin)->put(route('admin.treinamentos.update', $t), ['titulo' => 'Aula geral', 'ordem' => 5, 'perfis' => ['caixa']])->assertSessionHasNoErrors();
        $this->assertSame(['erp', ['caixa']], [$t->fresh()->secao, $t->fresh()->perfis]);
        $this->actingAs($admin)->get(route('admin.treinamentos.index'))->assertOk()->assertSee('Seção')->assertSee('Sistema');
        $this->actingAs($admin)->get(route('admin.treinamentos.edit', $t))->assertOk()->assertSee('Loja online');
    }
}
