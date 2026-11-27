<?php

namespace Tests\Feature\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\ProdutoPreco;
use App\Services\LojaOnline\Exclusivos\CellMaster;
use App\Services\LojaOnline\ModelosLoja;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Loja online de ATACADO (27/11/2026, Cell Master).
 *
 * A regra é da loja (`lojas_online.config`): `pecas_minimo` = mínimo de peças no pedido
 * INTEIRO (pode misturar produtos) e `preco_atacado` = a vitrine mostra e cobra o preço de
 * atacado do cadastro. As duas nascem desligadas: loja que não liga não muda.
 * O modelo exclusivo "cellmaster" só aparece para a loja dona e liga a regra ao ser aplicado.
 */
class LojaAtacadoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $dono;
    private $a;
    private $b;
    private LojaOnline $loja;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->empresa->update(['exige_documento_cadastro' => false]);
        $estoque = $this->estoquePrincipal($this->unidade);

        $this->a = $this->createProduto($this->empresa, ['preco_venda' => 100, 'descricao' => 'Perfume Alfa']);
        $this->b = $this->createProduto($this->empresa, ['preco_venda' => 50, 'descricao' => 'Garrafa Beta']);
        foreach ([$this->a, $this->b] as $p) {
            $p->forceFill(['loja_online' => true])->save();
            SaldoEstoque::registrar($this->empresa->id, $this->unidade->id, $estoque, $p->id,
                TipoMovimentacaoEstoque::Entrada->value, 10, ['custo_unitario' => 10, 'user_id' => $this->dono->id]);
        }
        ProdutoPreco::create(['empresa_id' => $this->empresa->id, 'produto_id' => $this->a->id, 'modalidade' => 'atacado', 'valor' => 60]);

        $this->loja = LojaOnline::create([
            'empresa_id' => $this->empresa->id, 'ativo' => true, 'publicada' => true, 'slug' => 'atacado',
            'nome' => 'Loja Atacado', 'unidade_id' => $this->unidade->id, 'modelo' => 'vitrine',
        ]);
    }

    private function regra(array $config): void
    {
        $this->loja->update(['config' => $config + ($this->loja->config ?? [])]);
    }

    private function por(int $produtoId, int $quantidade)
    {
        return $this->postJson('/loja/atacado/carrinho/adicionar', ['produto_id' => $produtoId, 'quantidade' => $quantidade]);
    }

    private function fechar()
    {
        return $this->post('/loja/atacado/finalizar', [
            'nome' => 'Rita Revendedora', 'telefone' => '(86) 98888-2222', 'entrega' => 'retirada', 'pagamento' => 'combinar',
        ]);
    }

    private function pedidos(): int
    {
        return Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->count();
    }

    public function test_loja_sem_a_regra_continua_vendendo_uma_peca_no_preco_normal(): void
    {
        $this->get('/loja/atacado/produtos')->assertOk()->assertSee('100,00')->assertDontSee('60,00');
        $this->por($this->a->id, 1)->assertOk()->assertJsonPath('aviso', null);
        $this->get('/loja/atacado/carrinho')->assertOk()->assertDontSee('data-lj-pecas', false);
        $this->get('/loja/atacado/carrinho/resumo')->assertJsonPath('pecas_minimo', 0)->assertJsonPath('faltam_pecas', 0);
        $this->fechar();
        $this->assertSame(1, $this->pedidos());
    }

    public function test_abaixo_do_minimo_de_pecas_o_carrinho_avisa_e_o_pedido_nao_fecha(): void
    {
        $this->regra(['pecas_minimo' => 6]);

        $this->por($this->a->id, 2)->assertOk()->assertJsonPath('aviso', 'Adicionado. Faltam 4 peças para o pedido mínimo.');
        $this->get('/loja/atacado/carrinho')->assertOk()
            ->assertSee('2 de 6 peças')->assertSee('faltam 4 peças')->assertSee('aria-disabled="true"', false)
            ->assertDontSee('style="margin-top:1.2rem" href="/loja/atacado/finalizar"', false);
        $this->get('/loja/atacado/carrinho/resumo')->assertJsonPath('pecas', 2)->assertJsonPath('pecas_minimo', 6)->assertJsonPath('faltam_pecas', 4);

        // a tela do fechamento não abre, e o envio direto (sem passar pela tela) também não fecha
        $this->get('/loja/atacado/finalizar')->assertRedirect('/loja/atacado/carrinho');
        $this->fechar()->assertRedirect('/loja/atacado/carrinho')->assertSessionHas('loja_erro');
        $this->assertSame(0, $this->pedidos());
    }

    public function test_o_minimo_e_do_pedido_inteiro_e_pode_misturar_produtos(): void
    {
        $this->regra(['pecas_minimo' => 6]);

        $this->por($this->a->id, 2);
        $this->por($this->b->id, 3)->assertJsonPath('aviso', 'Adicionado. Falta 1 peça para o pedido mínimo.');
        $this->por($this->b->id, 1)->assertJsonPath('aviso', null);

        $this->get('/loja/atacado/carrinho')->assertSee('6 de 6 peças')->assertSee('mínimo do atacado atingido')
            ->assertSee('style="margin-top:1.2rem" href="/loja/atacado/finalizar"', false);
        $this->get('/loja/atacado/finalizar')->assertOk();
        $this->fechar();

        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();
        $this->assertEquals(6, (float) $pedido->itens()->sum('quantidade'));
        $this->assertEquals(2 * 100 + 4 * 50, (float) $pedido->total);
    }

    public function test_preco_de_atacado_vale_na_vitrine_no_carrinho_e_no_pedido(): void
    {
        $this->regra(['preco_atacado' => true]);

        // A tem preço de atacado (60); B não tem e sai pelo preço normal (50)
        $this->get('/loja/atacado/produtos')->assertOk()->assertSee('60,00')->assertSee('50,00')->assertDontSee('100,00');
        $this->por($this->a->id, 2)->assertJsonPath('subtotal', 120);
        $this->por($this->b->id, 1)->assertJsonPath('subtotal', 170);
        $this->fechar();

        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();
        $this->assertEquals(170.00, (float) $pedido->total);
    }

    public function test_painel_grava_o_minimo_de_pecas_e_o_preco_de_atacado(): void
    {
        $base = ['nome' => 'Loja Atacado', 'unidade_id' => $this->unidade->id, 'retirada' => 1, 'pedido_minimo' => '0'];
        $eu = fn () => $this->actingAsUser($this->dono, $this->unidade);

        $eu()->get(route('app.loja-online.configuracoes'))->assertOk()->assertSee('name="pecas_minimo"', false)->assertSee('name="preco_atacado"', false);
        $eu()->put(route('app.loja-online.configuracoes.salvar'), $base + ['pecas_minimo' => '6', 'preco_atacado' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(6, $this->loja->fresh()->opcao('pecas_minimo'));
        $this->assertTrue($this->loja->fresh()->opcao('preco_atacado'));

        $eu()->put(route('app.loja-online.configuracoes.salvar'), $base + ['pecas_minimo' => '-2'])->assertSessionHasErrors('pecas_minimo');
        $eu()->put(route('app.loja-online.configuracoes.salvar'), $base + ['pecas_minimo' => ''])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->loja->fresh()->opcao('pecas_minimo'));
        $this->assertFalse($this->loja->fresh()->opcao('preco_atacado'));
    }

    public function test_modelo_cellmaster_e_exclusivo_e_o_bloco_dele_so_passa_com_o_modelo(): void
    {
        $this->assertCount(6, ModelosLoja::todos());
        $this->assertArrayNotHasKey('cellmaster', ModelosLoja::todos());
        $this->assertArrayHasKey('cellmaster', ModelosLoja::catalogo());

        $comum = new LojaOnline(['modelo' => 'vitrine']);
        $dona = new LojaOnline(['modelo' => 'cellmaster', 'modelo_exclusivo' => 'cellmaster']);
        $merlinda = new LojaOnline(['modelo' => 'merlinda', 'modelo_exclusivo' => 'merlinda']);
        $this->assertSame('cellmaster', array_key_first(ModelosLoja::paraLoja($dona)));
        $this->assertArrayNotHasKey('cellmaster', ModelosLoja::paraLoja($comum));
        $this->assertArrayNotHasKey('cellmaster', ModelosLoja::paraLoja($merlinda));
        $this->assertArrayHasKey('jostforte', ModelosLoja::fontesDaLoja($dona));
        $this->assertArrayNotHasKey('jostforte', ModelosLoja::fontesDaLoja($comum));

        $bloco = [['tipo' => 'atacado_passos', 'titulo' => 'Como comprar', 'lista' => [['nome' => 'Monte', 'texto' => 'x']]]];
        $this->assertSame([], ModelosLoja::limparBlocos($bloco, [], 'vitrine'));
        $this->assertSame([], ModelosLoja::limparBlocos($bloco, [], 'merlinda'));
        $this->assertSame('atacado_passos', ModelosLoja::limparBlocos($bloco, [], 'cellmaster')[0]['tipo']);

        $tema = ModelosLoja::tema('cellmaster');
        $this->assertSame($tema, array_merge($tema, ModelosLoja::limparTema($tema)), 'o tema do modelo passa pela própria higiene');
        $blocos = CellMaster::modelo()['blocos'];
        $this->assertSame(count($blocos), count(ModelosLoja::limparBlocos($blocos, [], 'cellmaster')), 'todo bloco de partida é aceito pelo editor');
    }

    public function test_admin_aplica_o_modelo_e_a_regra_do_atacado_entra_sem_pisar_no_que_a_loja_escreveu(): void
    {
        Storage::fake('public');
        $this->regra(['texto_rodape' => 'Texto que a loja escreveu']);
        $admin = $this->createUser($this->empresa, $this->unidade, 'admin');
        $admin->forceFill(['is_admin' => true, 'empresa_id' => null])->save();

        // lojista não aplica
        $this->actingAsUser($this->dono, $this->unidade)
            ->post(route('admin.empresas.loja-online.modelo-exclusivo', $this->empresa), ['modelo_exclusivo' => 'cellmaster']);
        $this->assertNull($this->loja->fresh()->modelo_exclusivo);

        $this->actingAs($admin)->post(route('admin.empresas.loja-online.modelo-exclusivo', $this->empresa), ['modelo_exclusivo' => 'cellmaster'])->assertRedirect();
        $loja = $this->loja->fresh();
        $this->assertSame('cellmaster', $loja->modelo);
        $this->assertSame(6, $loja->opcao('pecas_minimo'));
        $this->assertTrue($loja->opcao('preco_atacado'));
        $this->assertSame('Texto que a loja escreveu', $loja->opcao('texto_rodape'));
        $this->assertSame('loja-online/' . $this->empresa->id . '/cellmaster-logo.png', $loja->tema['logo']);
        Storage::disk('public')->assertExists('loja-online/' . $this->empresa->id . '/cellmaster-marca.png');
        $this->assertContains('atacado_passos', array_column($loja->blocos, 'tipo'));

        auth()->logout();
        $this->get('/loja/atacado')->assertOk()
            ->assertSee('data-modelo="cellmaster"', false)->assertSee('é o pedido mínimo', false)->assertSee('60,00')
            ->assertSee('Pedido mínimo de 6 peças');

        // a loja muda o mínimo em Configurações e a página acompanha (o número não é do modelo)
        $loja->update(['config' => ['pecas_minimo' => 12] + $loja->config]);
        $this->get('/loja/atacado')->assertSee('<b>12</b>', false);

        // a loja desligou a regra antes: religar o modelo não liga de volta
        $loja->update(['config' => ['pecas_minimo' => 0, 'preco_atacado' => false] + $loja->config, 'modelo_exclusivo' => null]);
        $this->actingAs($admin)->post(route('admin.empresas.loja-online.modelo-exclusivo', $this->empresa), ['modelo_exclusivo' => 'cellmaster']);
        $this->assertSame(0, $loja->fresh()->opcao('pecas_minimo'));
        $this->assertFalse($loja->fresh()->opcao('preco_atacado'));
    }
}
