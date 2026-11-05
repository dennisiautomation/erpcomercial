<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Estoque;
use App\Models\Orcamento;
use App\Models\Pedido;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * "Sai de" no orçamento e no pedido (05/11/2026, chamado DK Med): a loja Matriz tem os
 * estoques Principal (Teresina) e São Luís; o vendedor escolhe de qual o produto sai e a
 * baixa do faturamento sai dele. Loja com um estoque só não muda nada.
 */
class SaiDeOrcamentoPedidoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $dono;
    private $cliente;
    private $produto;
    private int $principal;
    private Estoque $saoLuis;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->cliente = $this->createCliente($this->empresa);
        $this->produto = $this->createProduto($this->empresa, ['preco_venda' => 100]);
        $this->principal = $this->estoquePrincipal($this->unidade);
        $this->saoLuis = Estoque::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id,
            'nome' => 'São Luis', 'codigo' => 'SLZ', 'permite_venda' => false, 'is_padrao' => false, 'status' => 'ativo',
        ]);
        foreach ([$this->principal, $this->saoLuis->id] as $estoqueId) {
            SaldoEstoque::registrar($this->empresa->id, $this->unidade->id, $estoqueId, $this->produto->id,
                TipoMovimentacaoEstoque::Entrada->value, 10, ['custo_unitario' => 50, 'user_id' => $this->dono->id]);
        }
    }

    private function eu()
    {
        return $this->actingAsUser($this->dono, $this->unidade);
    }

    private function itens(): array
    {
        return [['produto_id' => $this->produto->id, 'quantidade' => 3, 'preco_unitario' => 100]];
    }

    public function test_telas_mostram_sai_de_com_os_estoques_da_loja(): void
    {
        foreach (['app.orcamentos.create', 'app.pedidos.create'] as $rota) {
            $this->eu()->get(route($rota))->assertOk()
                ->assertSee('Sai de')->assertSee('name="estoque_id"', false)->assertSee('São Luis');
        }
    }

    public function test_loja_com_um_estoque_so_nao_mostra_e_nao_grava(): void
    {
        $this->saoLuis->update(['status' => 'inativo']);

        $this->eu()->get(route('app.orcamentos.create'))->assertOk()->assertDontSee('name="estoque_id"', false);
        $this->eu()->post(route('app.orcamentos.store'), [
            'cliente_id' => $this->cliente->id, 'validade_ate' => now()->addDays(10)->toDateString(),
            'estoque_id' => $this->saoLuis->id, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();

        $this->assertNull(Orcamento::withoutGlobalScopes()->sole()->estoque_id);
    }

    public function test_orcamento_vira_pedido_e_o_faturamento_baixa_de_sao_luis(): void
    {
        $this->eu()->post(route('app.orcamentos.store'), [
            'cliente_id' => $this->cliente->id, 'validade_ate' => now()->addDays(10)->toDateString(),
            'estoque_id' => $this->saoLuis->id, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();
        $orcamento = Orcamento::withoutGlobalScopes()->sole();
        $this->assertSame($this->saoLuis->id, $orcamento->estoque_id);

        $this->eu()->get(route('app.orcamentos.show', $orcamento))->assertOk()->assertSee('Sai de: <strong>São Luis</strong>', false);
        $this->eu()->get(route('app.orcamentos.imprimir', $orcamento))->assertOk()->assertSee('Sai de: São Luis');

        $this->eu()->post(route('app.orcamentos.converter', $orcamento))->assertRedirect();
        $pedido = Pedido::withoutGlobalScopes()->sole();
        $this->assertSame($this->saoLuis->id, $pedido->estoque_id, 'o "Sai de" vai do orçamento para o pedido');

        $this->eu()->post(route('app.pedidos.update-status', $pedido), ['status' => 'confirmado']);
        $this->openCaixa($this->empresa, $this->unidade, $this->dono);
        $this->eu()->post(route('app.pedidos.faturar', $pedido), ['documento' => 'nenhum', 'forma_pagamento' => 'dinheiro'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id), 'saiu de São Luís');
        $this->assertEquals(10.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id), 'Teresina intacto');

        // Cancelar a venda do pedido devolve para São Luís, não para o Principal
        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();
        $this->eu()->delete(route('app.vendas.destroy', $venda))->assertSessionHas('success');
        $this->assertEquals(10.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id));
        $this->assertEquals(10.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));
    }

    public function test_pedido_direto_grava_edita_e_imprime_o_sai_de(): void
    {
        $this->eu()->post(route('app.pedidos.store'), [
            'cliente_id' => $this->cliente->id, 'condicao_pagamento' => 'pix',
            'estoque_id' => $this->saoLuis->id, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();
        $pedido = Pedido::withoutGlobalScopes()->sole();
        $this->assertSame($this->saoLuis->id, $pedido->estoque_id);

        $this->eu()->get(route('app.pedidos.edit', $pedido))->assertOk()
            ->assertSee('<option value="' . $this->saoLuis->id . '" selected', false);
        $this->eu()->get(route('app.pedidos.imprimir', $pedido))->assertOk()->assertSee('Sai de: <b>São Luis</b>', false);

        // Volta para Teresina na edição
        $this->eu()->put(route('app.pedidos.update', $pedido), [
            'cliente_id' => $this->cliente->id, 'condicao_pagamento' => 'pix',
            'estoque_id' => $this->principal, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->principal, $pedido->fresh()->estoque_id);

        // Faturar pelo status (caminho antigo) também respeita
        $pedido = $pedido->fresh();
        $pedido->update(['estoque_id' => $this->saoLuis->id, 'status' => StatusPedido::Confirmado]);
        $this->eu()->post(route('app.pedidos.update-status', $pedido), ['status' => 'faturado']);
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id));
        $this->assertEquals(10.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));
    }

    public function test_sai_de_e_obrigatorio_nada_vem_marcado_e_estoque_de_outra_loja_nao_vale(): void
    {
        $this->eu()->get(route('app.pedidos.create'))->assertOk()
            ->assertSee('<option value="" selected>Escolha de onde sai…</option>', false);

        // Sem escolher
        $this->eu()->post(route('app.pedidos.store'), [
            'cliente_id' => $this->cliente->id, 'itens' => $this->itens(),
        ])->assertSessionHasErrors('estoque_id');
        $this->eu()->post(route('app.orcamentos.store'), [
            'cliente_id' => $this->cliente->id, 'validade_ate' => now()->addDays(10)->toDateString(), 'itens' => $this->itens(),
        ])->assertSessionHasErrors('estoque_id');

        // Estoque de outra loja
        $outra = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Outra', 'cnpj' => $this->unidade->cnpj,
            'cep' => '01001000', 'logradouro' => 'Rua', 'numero' => '1', 'bairro' => 'Centro',
            'cidade' => 'São Paulo', 'uf' => 'SP', 'telefone' => '11999999999', 'status' => 'ativa',
        ]);
        $this->eu()->post(route('app.pedidos.store'), [
            'cliente_id' => $this->cliente->id, 'estoque_id' => $this->estoquePrincipal($outra), 'itens' => $this->itens(),
        ])->assertSessionHasErrors('estoque_id');

        $this->assertSame(0, Pedido::withoutGlobalScopes()->count());
        $this->assertSame(0, Orcamento::withoutGlobalScopes()->count());
    }

    public function test_pedido_antigo_sem_sai_de_obriga_escolher_ao_editar_e_baixa_cai_no_de_venda(): void
    {
        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id,
            'numero' => 1, 'subtotal' => 300, 'total' => 300, 'status' => StatusPedido::Rascunho,
        ]);
        \App\Models\PedidoItem::create(['pedido_id' => $pedido->id, 'produto_id' => $this->produto->id,
            'descricao' => 'x', 'quantidade' => 3, 'preco_unitario' => 100, 'total' => 300]);

        $this->eu()->get(route('app.pedidos.edit', $pedido))->assertOk()
            ->assertSee('<option value="" selected>Escolha de onde sai…</option>', false);
        $this->eu()->put(route('app.pedidos.update', $pedido), [
            'cliente_id' => $this->cliente->id, 'itens' => $this->itens(),
        ])->assertSessionHasErrors('estoque_id');

        // Sem editar (pedido de antes da mudança): o faturamento baixa do estoque de venda
        $pedido->update(['status' => StatusPedido::Confirmado]);
        $this->eu()->post(route('app.pedidos.update-status', $pedido), ['status' => 'faturado']);
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));

        // Escolhido e depois inativado: idem
        $p2 = Pedido::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'cliente_id' => $this->cliente->id,
            'estoque_id' => $this->saoLuis->id, 'numero' => 2, 'subtotal' => 300, 'total' => 300, 'status' => StatusPedido::Confirmado,
        ]);
        \App\Models\PedidoItem::create(['pedido_id' => $p2->id, 'produto_id' => $this->produto->id,
            'descricao' => 'x', 'quantidade' => 3, 'preco_unitario' => 100, 'total' => 300]);
        $this->saoLuis->update(['status' => 'inativo']);
        $this->eu()->post(route('app.pedidos.update-status', $p2), ['status' => 'faturado']);
        $this->assertEquals(4.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));
    }

    /** Venda de pedido que saiu de São Luís, faturada, pronta para a troca. */
    private function vendaDeSaoLuis(): Venda
    {
        $this->eu()->post(route('app.pedidos.store'), [
            'cliente_id' => $this->cliente->id, 'condicao_pagamento' => 'pix',
            'estoque_id' => $this->saoLuis->id, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();
        $pedido = Pedido::withoutGlobalScopes()->latest('id')->first();
        $this->eu()->post(route('app.pedidos.update-status', $pedido), ['status' => 'confirmado']);
        $this->openCaixa($this->empresa, $this->unidade, $this->dono);
        $this->eu()->post(route('app.pedidos.faturar', $pedido), ['documento' => 'nenhum', 'forma_pagamento' => 'dinheiro'])
            ->assertSessionHasNoErrors();

        return Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();
    }

    public function test_troca_em_loja_com_dois_estoques_obriga_escolher_onde_a_peca_entra(): void
    {
        $venda = $this->vendaDeSaoLuis();
        $item = $venda->itens()->first();
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id));

        $tela = $this->eu()->get(route('app.trocas.create', ['venda' => $venda->id]))->assertOk();
        $tela->assertSee('<option value="">Entra em qual estoque?</option>', false);
        $tela->assertDontSee('" selected>São Luis', false);

        $base = ['venda_id' => $venda->id, 'motivo' => 'defeito', 'sobra_destino' => 'vale'];

        // Sem escolher: recusa, nada muda
        $this->eu()->post(route('app.trocas.store'), $base + ['itens' => [[
            'venda_item_id' => $item->id, 'quantidade' => 1, 'retorna_estoque' => 1,
        ]]])->assertSessionHas('error', fn ($m) => str_contains($m, 'Escolha em qual estoque'));
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id));
        $this->assertEquals(10.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));

        // Recebida em Teresina: entra em Teresina (o vendedor escolheu)
        $this->eu()->post(route('app.trocas.store'), $base + ['itens' => [[
            'venda_item_id' => $item->id, 'quantidade' => 1, 'retorna_estoque' => 1, 'estoque_id' => $this->principal,
        ]]])->assertSessionHas('success');
        $this->assertEquals(11.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));

        // Peça avariada (não volta ao estoque) não pede estoque
        $this->eu()->post(route('app.trocas.store'), $base + ['itens' => [[
            'venda_item_id' => $item->id, 'quantidade' => 1, 'retorna_estoque' => 0,
        ]]])->assertSessionHas('success');
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->saoLuis->id, $this->produto->id));
    }

    public function test_troca_em_loja_com_um_estoque_continua_automatica(): void
    {
        $venda = $this->vendaDeSaoLuis();
        $item = $venda->itens()->first();
        $this->saoLuis->update(['status' => 'inativo']);

        $this->eu()->post(route('app.trocas.store'), ['venda_id' => $venda->id, 'motivo' => 'defeito', 'sobra_destino' => 'vale',
            'itens' => [['venda_item_id' => $item->id, 'quantidade' => 1, 'retorna_estoque' => 1]]])
            ->assertSessionHas('success');
        $this->assertEquals(11.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));
    }
}
