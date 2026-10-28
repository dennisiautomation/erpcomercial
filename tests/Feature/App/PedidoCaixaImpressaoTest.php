<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoCaixa;
use App\Models\ContaReceber;
use App\Models\MovimentacaoCaixa;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Relato da DK Med (28/10/2026): venda de pedido não entrava no caixa e o
 * impresso do pedido saía como orçamento (ou como a tela do sistema).
 */
class PedidoCaixaImpressaoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    /** Pedido confirmado, com item e com a conta a receber da confirmação. */
    private function pedidoConfirmado($dono, $cliente, $produto, float $total = 300.00): Pedido
    {
        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id'         => $this->empresa->id,
            'unidade_id'         => $this->unidade->id,
            'cliente_id'         => $cliente->id,
            'vendedor_id'        => $dono->id,
            'numero'             => 1,
            'condicao_pagamento' => 'pix',
            'subtotal'           => $total,
            'total'              => $total,
            'status'             => StatusPedido::Rascunho,
        ]);

        PedidoItem::create([
            'pedido_id'      => $pedido->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 3,
            'preco_unitario' => $total / 3,
            'total'          => $total,
        ]);

        // Confirma pela rota: é ela que abre a conta a receber "Pedido #N"
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.update-status', $pedido), ['status' => 'confirmado']);

        return $pedido->fresh();
    }

    public function test_faturar_sem_caixa_aberto_e_recusado(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 50.00, 'user_id' => $dono->id]);
        $pedido = $this->pedidoConfirmado($dono, $cliente, $produto);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->from(route('app.pedidos.show', $pedido))
            ->post(route('app.pedidos.faturar', $pedido), [
                'documento'       => 'nenhum',
                'forma_pagamento' => 'pix',
            ]);

        $response->assertRedirect(route('app.pedidos.show', $pedido));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Abra o caixa', session('error'));

        $this->assertEquals(StatusPedido::Confirmado, $pedido->fresh()->status);
        $this->assertEquals(0, Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->count());
    }

    public function test_faturar_lanca_a_venda_no_caixa_e_baixa_a_conta_a_receber(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 50.00, 'user_id' => $dono->id]);
        $pedido = $this->pedidoConfirmado($dono, $cliente, $produto);
        $caixa  = $this->openCaixa($this->empresa, $this->unidade, $dono);

        $contaAntes = ContaReceber::withoutGlobalScopes()->where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($contaAntes, 'a confirmação deve abrir a conta a receber com pedido_id');
        $this->assertEquals('pendente', $contaAntes->status);

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.faturar', $pedido), [
                'documento'       => 'nenhum',
                'forma_pagamento' => 'pix',
            ])
            ->assertRedirect(route('app.pedidos.show', $pedido));

        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($venda);
        $this->assertEquals($caixa->id, $venda->caixa_id);
        $this->assertEquals(StatusPedido::Faturado, $pedido->fresh()->status);

        // Uma entrada no caixa, na forma escolhida, ligada à venda
        $movs = MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)->get();
        $this->assertCount(1, $movs);
        $this->assertEquals(TipoMovimentacaoCaixa::Venda, $movs[0]->tipo);
        $this->assertEquals('pix', $movs[0]->forma_pagamento);
        $this->assertEquals(300.00, (float) $movs[0]->valor);
        $this->assertEquals($caixa->id, $movs[0]->caixa_id);

        // A conta da confirmação virou paga, ligada à venda — sem conta duplicada
        $contas = ContaReceber::withoutGlobalScopes()->where('pedido_id', $pedido->id)->get();
        $this->assertCount(1, $contas);
        $this->assertEquals($contaAntes->id, $contas[0]->id);
        $this->assertEquals('paga', $contas[0]->status);
        $this->assertEquals($venda->id, $contas[0]->venda_id);
        $this->assertEquals('pix', $contas[0]->forma_pagamento);
        $this->assertEquals(300.00, (float) $contas[0]->valor_pago);
    }

    public function test_cancelar_a_venda_do_pedido_estorna_o_caixa(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 50.00, 'user_id' => $dono->id]);
        $pedido = $this->pedidoConfirmado($dono, $cliente, $produto);
        $this->openCaixa($this->empresa, $this->unidade, $dono);

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.faturar', $pedido), ['documento' => 'nenhum', 'forma_pagamento' => 'dinheiro']);
        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();

        $this->actingAsUser($dono, $this->unidade)
            ->delete(route('app.vendas.destroy', $venda))
            ->assertSessionHas('success');

        $estorno = MovimentacaoCaixa::withoutGlobalScopes()
            ->where('venda_id', $venda->id)
            ->where('tipo', TipoMovimentacaoCaixa::Estorno)
            ->first();
        $this->assertNotNull($estorno, 'a entrada da venda de pedido precisa ser estornada no cancelamento');
        $this->assertEquals(300.00, (float) $estorno->valor);
        $this->assertEquals('dinheiro', $estorno->forma_pagamento);
    }

    public function test_folha_do_pedido_sai_como_pedido_com_a_loja(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $pedido  = $this->pedidoConfirmado($dono, $cliente, $produto);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.pedidos.imprimir', $pedido));

        $response->assertOk();
        // Formato pedido/protocolo de entrega: sem o cabeçalho nem o título do orçamento
        $response->assertSee('Pedido de venda');
        $response->assertSee('Protocolo de entrega');
        $response->assertSee('Entregar para');
        $response->assertSee('Recebido por (nome legível)');
        $response->assertDontSee('ORÇAMENTO');
        $response->assertDontSee('De acordo');
        $response->assertSee('Loja: <b>' . $this->unidade->nome . '</b>', false);
        $response->assertSee($cliente->nome_razao_social);
        $response->assertSee('R$ 300,00');
        // Ainda não faturado: o entregador cobra o total
        $response->assertSee('A pagar: R$ 300,00');
    }

    public function test_tela_do_pedido_imprime_pela_folha_e_mostra_a_loja(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $pedido  = $this->pedidoConfirmado($dono, $cliente, $produto);

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.pedidos.show', $pedido));

        $response->assertOk();
        $response->assertSee(route('app.pedidos.imprimir', $pedido));
        $response->assertDontSee('onclick="window.print()"', false);
        $response->assertSee('Loja de saída');
        $response->assertSee($this->unidade->nome);
    }
}
