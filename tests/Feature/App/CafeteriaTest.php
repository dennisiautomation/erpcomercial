<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Models\ConfiguracaoLoja;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\EstoqueMovimentacao;
use App\Models\GrupoOpcao;
use App\Models\GrupoOpcaoItem;
use App\Models\Produto;
use App\Models\ProdutoFichaItem;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Fase 1 cafeteria (30/10/2026): tipo do produto, ficha técnica que baixa
 * insumos, adicionais no PDV e atalhos. Ver docs.md, seção do mesmo dia.
 */
class CafeteriaTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->empresa->update(['usa_ficha_tecnica' => true]);
    }

    /** Café (kg), leite (L) e copo (un) com custo e saldo; cappuccino = 18 g + 150 ml + 1 copo. */
    private function cenarioCappuccino($user): array
    {
        $cafe  = $this->createProduto($this->empresa, ['descricao' => 'Café em grão', 'tipo' => 'insumo', 'unidade_medida' => 'KG', 'preco_custo' => 80.00, 'preco_venda' => 0]);
        $leite = $this->createProduto($this->empresa, ['descricao' => 'Leite integral', 'tipo' => 'insumo', 'unidade_medida' => 'LT', 'preco_custo' => 5.00, 'preco_venda' => 0]);
        $copo  = $this->createProduto($this->empresa, ['descricao' => 'Copo 300 ml', 'tipo' => 'insumo', 'preco_custo' => 0.30, 'preco_venda' => 0]);
        $capu  = $this->createProduto($this->empresa, ['descricao' => 'Cappuccino', 'tipo' => 'produzido', 'preco_venda' => 12.00, 'preco_custo' => 0]);

        foreach ([[$cafe, 0.018], [$leite, 0.150], [$copo, 1]] as [$ins, $qtd]) {
            ProdutoFichaItem::withoutGlobalScopes()->create([
                'empresa_id' => $this->empresa->id, 'produto_id' => $capu->id, 'insumo_id' => $ins->id, 'quantidade' => $qtd,
            ]);
        }

        $this->darEntradaEstoque($this->unidade, $cafe, 2, ['user_id' => $user->id]);
        $this->darEntradaEstoque($this->unidade, $leite, 10, ['user_id' => $user->id]);
        $this->darEntradaEstoque($this->unidade, $copo, 100, ['user_id' => $user->id]);

        return compact('cafe', 'leite', 'copo', 'capu');
    }

    /** Grupo obrigatório "Tamanho" (P 0, G +3 com receita: +0,100 L leite) e opcional "Extras" (shot +4 com 0,009 kg café). */
    private function gruposDoCappuccino(array $c): array
    {
        $p = $this->createProduto($this->empresa, ['descricao' => 'Tamanho P', 'tipo' => 'adicional', 'preco_venda' => 0]);
        $g = $this->createProduto($this->empresa, ['descricao' => 'Tamanho G', 'tipo' => 'adicional', 'preco_venda' => 3.00]);
        $shot = $this->createProduto($this->empresa, ['descricao' => 'Shot extra', 'tipo' => 'adicional', 'preco_venda' => 4.00]);
        ProdutoFichaItem::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'produto_id' => $g->id, 'insumo_id' => $c['leite']->id, 'quantidade' => 0.100]);
        ProdutoFichaItem::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'produto_id' => $shot->id, 'insumo_id' => $c['cafe']->id, 'quantidade' => 0.009]);

        $tamanho = GrupoOpcao::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'nome' => 'Tamanho', 'minimo' => 1, 'maximo' => 1, 'status' => 'ativo']);
        $extras  = GrupoOpcao::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'nome' => 'Extras', 'minimo' => 0, 'maximo' => 3, 'status' => 'ativo']);
        $op = fn ($grupo, $adic, $ordem) => GrupoOpcaoItem::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'grupo_opcao_id' => $grupo->id, 'adicional_id' => $adic->id, 'preco' => null, 'ordem' => $ordem,
        ]);
        $opP = $op($tamanho, $p, 0);
        $opG = $op($tamanho, $g, 1);
        $opShot = $op($extras, $shot, 0);

        $c['capu']->gruposOpcoes()->sync([$tamanho->id => ['ordem' => 0], $extras->id => ['ordem' => 1]]);

        return compact('opP', 'opG', 'opShot', 'tamanho', 'extras');
    }

    private function venderNoPdv($user, $caixa, array $itens, float $valor)
    {
        return $this->actingAsUser($user, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'tabela_precos' => 1,
                'itens' => $itens,
                'pagamentos' => [['forma' => 'dinheiro', 'valor' => $valor]],
                'documento' => 'recibo',
            ]);
    }

    private function saldo(Produto $p): float
    {
        return SaldoEstoque::naUnidade($this->unidade->id, $p->id);
    }

    /* ------------------------------------------------------------------ */

    public function test_vender_produzido_baixa_os_insumos_e_nao_o_produto(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $c = $this->cenarioCappuccino($caixaUser);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $c['capu']->id, 'quantidade' => 2, 'preco_unitario' => 12.00, 'desconto_valor' => 0,
        ]], 24.00)->assertOk();

        $this->assertEqualsWithDelta(2 - 0.036, $this->saldo($c['cafe']), 0.0001);
        $this->assertEqualsWithDelta(10 - 0.300, $this->saldo($c['leite']), 0.0001);
        $this->assertEqualsWithDelta(98, $this->saldo($c['copo']), 0.0001);
        $this->assertSame(0, EstoqueMovimentacao::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->count(),
            'produzido não tem saldo próprio — nada de movimentação do cappuccino');

        $item = VendaItem::where('produto_id', $c['capu']->id)->firstOrFail();
        $this->assertCount(3, $item->baixa_componentes);
        $this->assertEquals(24.00, (float) $item->total);
    }

    public function test_adicionais_somam_no_preco_e_baixam_a_receita_deles(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $c = $this->cenarioCappuccino($caixaUser);
        $o = $this->gruposDoCappuccino($c);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        // G (+3) + shot (+4) = 12 + 7 = 19. O front manda o preço SEM adicionais.
        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $c['capu']->id, 'quantidade' => 1, 'preco_unitario' => 12.00, 'desconto_valor' => 0,
            'opcoes' => [$o['opG']->id, $o['opShot']->id],
        ]], 19.00)->assertOk();

        $venda = Venda::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertEquals(19.00, (float) $venda->total);
        $item = $venda->itens()->firstOrFail();
        $this->assertEquals(19.00, (float) $item->preco_unitario);
        $this->assertSame('Cappuccino + Tamanho G + Shot extra', $item->descricao);
        $this->assertCount(2, $item->opcoes);

        // café: 0,018 (receita) + 0,009 (shot); leite: 0,150 + 0,100 (G); copo 1
        $this->assertEqualsWithDelta(2 - 0.027, $this->saldo($c['cafe']), 0.0001);
        $this->assertEqualsWithDelta(10 - 0.250, $this->saldo($c['leite']), 0.0001);
        $this->assertEqualsWithDelta(99, $this->saldo($c['copo']), 0.0001);
    }

    public function test_grupo_obrigatorio_sem_escolha_e_recusado(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $c = $this->cenarioCappuccino($caixaUser);
        $o = $this->gruposDoCappuccino($c);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $c['capu']->id, 'quantidade' => 1, 'preco_unitario' => 12.00, 'opcoes' => [$o['opShot']->id],
        ]], 16.00)->assertStatus(422)->assertJsonFragment(['error' => 'Cappuccino: escolha Tamanho.']);

        // P e G juntos passam do máximo 1
        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $c['capu']->id, 'quantidade' => 1, 'preco_unitario' => 12.00, 'opcoes' => [$o['opP']->id, $o['opG']->id],
        ]], 15.00)->assertStatus(422);

        $this->assertSame(0, Venda::withoutGlobalScopes()->count());
    }

    public function test_opcao_de_grupo_que_nao_e_do_produto_e_recusada(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $c = $this->cenarioCappuccino($caixaUser);
        $o = $this->gruposDoCappuccino($c);
        $agua = $this->createProduto($this->empresa, ['descricao' => 'Água', 'preco_venda' => 4.00]);
        $this->darEntradaEstoque($this->unidade, $agua, 10, ['user_id' => $caixaUser->id]);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $agua->id, 'quantidade' => 1, 'preco_unitario' => 4.00, 'opcoes' => [$o['opShot']->id],
        ]], 8.00)->assertStatus(422);
    }

    public function test_cancelar_venda_devolve_os_insumos(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $c = $this->cenarioCappuccino($dono);
        $o = $this->gruposDoCappuccino($c);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $dono);

        $this->venderNoPdv($dono, $caixa, [[
            'produto_id' => $c['capu']->id, 'quantidade' => 1, 'preco_unitario' => 12.00, 'opcoes' => [$o['opG']->id],
        ]], 15.00)->assertOk();
        $venda = Venda::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->actingAsUser($dono, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->delete(route('app.vendas.destroy', $venda))
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(2, $this->saldo($c['cafe']), 0.0001);
        $this->assertEqualsWithDelta(10, $this->saldo($c['leite']), 0.0001);
        $this->assertEqualsWithDelta(100, $this->saldo($c['copo']), 0.0001);
        $this->assertSame(0, EstoqueMovimentacao::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->count());
    }

    public function test_revenda_sem_opcoes_baixa_como_sempre(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $agua = $this->createProduto($this->empresa, ['descricao' => 'Água', 'preco_venda' => 4.00]);
        $this->darEntradaEstoque($this->unidade, $agua, 10, ['user_id' => $caixaUser->id]);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $agua->id, 'quantidade' => 3, 'preco_unitario' => 4.00,
        ]], 12.00)->assertOk();

        $this->assertEqualsWithDelta(7, $this->saldo($agua), 0.0001);
        $item = VendaItem::where('produto_id', $agua->id)->firstOrFail();
        $this->assertNull($item->baixa_componentes);
        $this->assertNull($item->opcoes);
        $this->assertSame('Água', $item->descricao);
    }

    public function test_insumo_e_adicional_ficam_fora_da_busca_e_nao_vendem_sozinhos(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $c = $this->cenarioCappuccino($caixaUser);
        $o = $this->gruposDoCappuccino($c);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $busca = $this->actingAsUser($caixaUser, $this->unidade)
            ->getJson(route('app.pdv.buscar-produto', 'a'))
            ->assertOk()
            ->json();
        $nomes = array_column($busca, 'descricao');
        $this->assertContains('Cappuccino', $nomes);
        $this->assertNotContains('Café em grão', $nomes);
        $this->assertNotContains('Tamanho G', $nomes);
        $capu = collect($busca)->firstWhere('descricao', 'Cappuccino');
        $this->assertTrue($capu['tem_opcoes']);
        $this->assertSame('produzido', $capu['tipo']);

        $this->venderNoPdv($caixaUser, $caixa, [[
            'produto_id' => $c['cafe']->id, 'quantidade' => 1, 'preco_unitario' => 0,
        ]], 1.00)->assertStatus(422);

        // A janela de opções devolve os grupos na ordem, com o preço de cada opção
        $op = $this->actingAsUser($caixaUser, $this->unidade)
            ->getJson(route('app.pdv.produto.opcoes', $c['capu']))
            ->assertOk()
            ->json('grupos');
        $this->assertSame(['Tamanho', 'Extras'], array_column($op, 'nome'));
        $this->assertSame(1, $op[0]['minimo']);
        $this->assertEquals(3.00, $op[0]['opcoes'][1]['preco']);

        // Produzido não tem saldo: o PDV não avisa "sem estoque"
        $this->actingAsUser($caixaUser, $this->unidade)
            ->getJson(route('app.pdv.verificar-estoque', $c['capu']->id))
            ->assertOk()
            ->assertJson(['sem_controle' => true]);
    }

    public function test_ficha_tecnica_grava_e_recalcula_o_custo(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $c = $this->cenarioCappuccino($dono);
        ProdutoFichaItem::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->delete();

        $this->actingAsUser($dono, $this->unidade)
            ->put(route('app.produtos.composicao.update', $c['capu']), [
                'ficha' => [
                    ['insumo_id' => $c['cafe']->id, 'quantidade' => '0.018'],
                    ['insumo_id' => $c['leite']->id, 'quantidade' => '0.150'],
                    ['insumo_id' => $c['copo']->id, 'quantidade' => '1'],
                    ['insumo_id' => '', 'quantidade' => ''],
                ],
            ])
            ->assertRedirect(route('app.produtos.composicao', $c['capu']))
            ->assertSessionHas('success');

        $this->assertSame(3, ProdutoFichaItem::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->count());
        // 0,018×80 + 0,150×5 + 1×0,30 = 1,44 + 0,75 + 0,30 = 2,49
        $this->assertEquals(2.49, (float) $c['capu']->fresh()->preco_custo);

        // Produzido sem receita não salva
        $this->actingAsUser($dono, $this->unidade)
            ->put(route('app.produtos.composicao.update', $c['capu']), ['ficha' => []])
            ->assertSessionHasErrors('ficha');

        // Não pode ser insumo de si mesmo
        $this->actingAsUser($dono, $this->unidade)
            ->put(route('app.produtos.composicao.update', $c['capu']), ['ficha' => [['insumo_id' => $c['capu']->id, 'quantidade' => 1]]])
            ->assertSessionHasErrors('ficha.0.insumo_id');

        $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.produtos.composicao', $c['capu']))
            ->assertOk()
            ->assertSee('Café em grão')
            ->assertSee('Custo da receita');
    }

    public function test_telas_da_cafeteria_somem_com_a_opcao_desligada(): void
    {
        $this->empresa->update(['usa_ficha_tecnica' => false]);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $prod = $this->createProduto($this->empresa);

        $this->actingAsUser($dono, $this->unidade)->get(route('app.produtos.composicao', $prod))->assertNotFound();
        $this->actingAsUser($dono, $this->unidade)->get(route('app.grupos-opcoes.index'))->assertNotFound();
        $this->actingAsUser($dono, $this->unidade)->get(route('app.produtos.create'))->assertOk()->assertDontSee('Tipo do produto');

        // tipo vindo num POST forjado não grava
        $this->actingAsUser($dono, $this->unidade)->post(route('app.produtos.store'), [
            'descricao' => 'Teste tipo', 'unidade_medida' => 'UN', 'preco_venda' => 5, 'tipo' => 'insumo',
        ])->assertRedirect();
        $this->assertSame('revenda', Produto::withoutGlobalScopes()->where('descricao', 'Teste tipo')->firstOrFail()->tipo);
    }

    public function test_cadastro_grava_tipo_e_atalho_com_a_opcao_ligada(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($dono, $this->unidade)->get(route('app.produtos.create'))->assertOk()->assertSee('Tipo do produto');

        $this->actingAsUser($dono, $this->unidade)->post(route('app.produtos.store'), [
            'descricao' => 'Pão de queijo', 'unidade_medida' => 'UN', 'preco_venda' => 6, 'tipo' => 'produzido', 'atalho_pdv' => '1', 'estoque_minimo' => 5,
        ])->assertRedirect();
        $p = Produto::withoutGlobalScopes()->where('descricao', 'Pão de queijo')->firstOrFail();
        $this->assertSame('produzido', $p->tipo);
        $this->assertTrue($p->atalho_pdv);
        $this->assertEquals(0, (int) $p->estoque_minimo);

        // Insumo nunca fica com atalho
        $this->actingAsUser($dono, $this->unidade)->post(route('app.produtos.store'), [
            'descricao' => 'Açúcar', 'unidade_medida' => 'KG', 'preco_venda' => 0, 'tipo' => 'insumo', 'atalho_pdv' => '1',
        ])->assertRedirect();
        $this->assertFalse(Produto::withoutGlobalScopes()->where('descricao', 'Açúcar')->firstOrFail()->atalho_pdv);

        $this->actingAsUser($dono, $this->unidade)->get(route('app.produtos.index', ['tipo' => 'insumo']))
            ->assertOk()->assertSee('Açúcar')->assertDontSee('Pão de queijo');
    }

    public function test_grupo_de_opcoes_cria_adicional_na_hora(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($dono, $this->unidade)->post(route('app.grupos-opcoes.store'), [
            'nome' => 'Leite', 'obrigatorio' => '0', 'maximo' => 1, 'status' => 'ativo',
            'itens' => [
                ['adicional_id' => '', 'novo_nome' => 'Leite de aveia', 'preco' => '2.50'],
                ['adicional_id' => '', 'novo_nome' => '', 'preco' => ''],
            ],
        ])->assertRedirect(route('app.grupos-opcoes.index'));

        $grupo = GrupoOpcao::withoutGlobalScopes()->where('nome', 'Leite')->firstOrFail();
        $this->assertSame(0, $grupo->minimo);
        $item = $grupo->itens()->withoutGlobalScopes()->firstOrFail();
        $adic = Produto::withoutGlobalScopes()->findOrFail($item->adicional_id);
        $this->assertSame('adicional', $adic->tipo);
        $this->assertSame('Leite de aveia', $adic->descricao);
        $this->assertEquals(2.50, (float) $adic->preco_venda);
        $this->assertNull($item->preco);

        $this->actingAsUser($dono, $this->unidade)->get(route('app.grupos-opcoes.index'))->assertOk()->assertSee('Leite de aveia');
        $this->actingAsUser($dono, $this->unidade)->get(route('app.grupos-opcoes.edit', $grupo))->assertOk();

        // Obrigatório sem opção não salva
        $this->actingAsUser($dono, $this->unidade)->post(route('app.grupos-opcoes.store'), [
            'nome' => 'Vazio', 'obrigatorio' => '1', 'maximo' => 1, 'status' => 'ativo', 'itens' => [],
        ])->assertSessionHasErrors('itens');
    }

    public function test_atalhos_trazem_os_marcados_por_categoria(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $marcado = $this->createProduto($this->empresa, ['descricao' => 'Espresso', 'atalho_pdv' => true, 'preco_venda' => 6]);
        $this->createProduto($this->empresa, ['descricao' => 'Não marcado', 'preco_venda' => 6]);
        $this->createProduto($this->empresa, ['descricao' => 'Leite insumo', 'tipo' => 'insumo', 'atalho_pdv' => true]);

        $r = $this->actingAsUser($caixaUser, $this->unidade)->getJson(route('app.pdv.atalhos'))->assertOk()->json();
        $this->assertSame('marcados', $r['origem']);
        $nomes = collect($r['categorias'])->flatMap(fn ($c) => array_column($c['produtos'], 'descricao'))->all();
        $this->assertSame(['Espresso'], $nomes);
    }

    public function test_pdv_mostra_atalhos_so_com_a_opcao_da_loja(): void
    {
        $caixaUser = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $caixaUser);

        $this->actingAsUser($caixaUser, $this->unidade)->withSession(['caixa_id' => $caixa->id])
            ->get(route('app.pdv.index'))->assertOk()->assertDontSee('id="atalhosPanel"', false)->assertSee('id="modalOpcoes"', false);

        ConfiguracaoLoja::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'pdv_atalhos' => true]);

        $this->actingAsUser($caixaUser, $this->unidade)->withSession(['caixa_id' => $caixa->id])
            ->get(route('app.pdv.index'))->assertOk()->assertSee('id="atalhosPanel"', false);
    }

    public function test_venda_de_balcao_de_produzido_baixa_insumos_e_cancelamento_devolve(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $c = $this->cenarioCappuccino($dono);
        $this->openCaixa($this->empresa, $this->unidade, $dono);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.vendas.store'), [
            'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $c['capu']->id, 'quantidade' => 1, 'preco_unitario' => 12.00]],
        ])->assertSessionHas('success');

        $this->assertEqualsWithDelta(99, $this->saldo($c['copo']), 0.0001);
        $venda = Venda::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertNotEmpty($venda->itens()->first()->baixa_componentes);

        $this->actingAsUser($dono, $this->unidade)->delete(route('app.vendas.destroy', $venda))->assertSessionHas('success');
        $this->assertEqualsWithDelta(100, $this->saldo($c['copo']), 0.0001);
        $this->assertSame(0, EstoqueMovimentacao::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->count());
    }

    public function test_faturar_pedido_de_produzido_baixa_insumos_e_cancelar_devolve(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $c = $this->cenarioCappuccino($dono);
        $this->openCaixa($this->empresa, $this->unidade, $dono);

        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id,
            'vendedor_id' => $dono->id, 'numero' => 1, 'condicao_pagamento' => 'pix',
            'subtotal' => 36, 'total' => 36, 'status' => StatusPedido::Rascunho,
        ]);
        PedidoItem::create(['pedido_id' => $pedido->id, 'produto_id' => $c['capu']->id, 'descricao' => 'Cappuccino', 'quantidade' => 3, 'preco_unitario' => 12, 'total' => 36]);
        $this->actingAsUser($dono, $this->unidade)->post(route('app.pedidos.update-status', $pedido), ['status' => 'confirmado']);

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.faturar', $pedido), ['documento' => 'nenhum', 'forma_pagamento' => 'dinheiro']);

        $this->assertEqualsWithDelta(97, $this->saldo($c['copo']), 0.0001);
        $this->assertEqualsWithDelta(2 - 0.054, $this->saldo($c['cafe']), 0.0001);
        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertCount(3, $venda->itens()->first()->baixa_componentes);

        $this->actingAsUser($dono, $this->unidade)->delete(route('app.vendas.destroy', $venda))->assertSessionHas('success');
        $this->assertEqualsWithDelta(100, $this->saldo($c['copo']), 0.0001);
        $this->assertSame(0, EstoqueMovimentacao::withoutGlobalScopes()->where('produto_id', $c['capu']->id)->count());
    }
}
