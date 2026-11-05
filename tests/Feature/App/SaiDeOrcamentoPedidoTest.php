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

    public function test_estoque_de_outra_loja_ou_inativo_e_ignorado(): void
    {
        $outra = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Outra', 'cnpj' => $this->unidade->cnpj,
            'cep' => '01001000', 'logradouro' => 'Rua', 'numero' => '1', 'bairro' => 'Centro',
            'cidade' => 'São Paulo', 'uf' => 'SP', 'telefone' => '11999999999', 'status' => 'ativa',
        ]);
        $deOutraLoja = $this->estoquePrincipal($outra);

        $this->eu()->post(route('app.pedidos.store'), [
            'cliente_id' => $this->cliente->id, 'estoque_id' => $deOutraLoja, 'itens' => $this->itens(),
        ])->assertSessionHasNoErrors();
        $this->assertNull(Pedido::withoutGlobalScopes()->latest('id')->first()->estoque_id);

        // Escolhido e depois inativado: a baixa cai no estoque de venda
        $pedido = Pedido::withoutGlobalScopes()->latest('id')->first();
        $pedido->update(['estoque_id' => $this->saoLuis->id, 'status' => StatusPedido::Confirmado]);
        $this->saoLuis->update(['status' => 'inativo']);
        $this->eu()->post(route('app.pedidos.update-status', $pedido), ['status' => 'faturado']);
        $this->assertEquals(7.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id));
    }
}
