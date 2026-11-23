<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PedidoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_vendedor_can_create_pedido(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 150.00]);

        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->post(route('app.pedidos.store'), [
                'cliente_id'         => $cliente->id,
                'vendedor_id'        => $vendedor->id,
                'condicao_pagamento' => 'boleto',
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 3,
                        'preco_unitario' => 150.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('app.pedidos.index'));

        $this->assertDatabaseHas('pedidos', [
            'empresa_id'  => $this->empresa->id,
            'cliente_id'  => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'status'      => 'rascunho',
        ]);

        $pedido = Pedido::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->first();
        $this->assertEquals(450.00, (float) $pedido->total);
        $this->assertCount(1, $pedido->itens);
    }

    public function test_pedido_status_workflow(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);

        // Create pedido
        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id'  => $this->empresa->id,
            'unidade_id'  => $this->unidade->id,
            'cliente_id'  => $cliente->id,
            'vendedor_id' => $dono->id,
            'numero'      => 1,
            'subtotal'    => 200.00,
            'total'       => 200.00,
            'status'      => StatusPedido::Rascunho,
        ]);

        PedidoItem::create([
            'pedido_id'      => $pedido->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 2,
            'preco_unitario' => 100.00,
            'total'          => 200.00,
        ]);

        // Verify the status transitions: rascunho -> confirmado -> faturado -> entregue
        $this->assertEquals(StatusPedido::Rascunho, $pedido->status);

        $pedido->update(['status' => StatusPedido::Confirmado]);
        $this->assertEquals(StatusPedido::Confirmado, $pedido->fresh()->status);

        $pedido->update(['status' => StatusPedido::Faturado]);
        $this->assertEquals(StatusPedido::Faturado, $pedido->fresh()->status);

        $pedido->update(['status' => StatusPedido::Entregue]);
        $this->assertEquals(StatusPedido::Entregue, $pedido->fresh()->status);
    }

    public function test_pedido_generates_contas_receber_on_confirm(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 200.00]);

        // Create pedido directly
        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id'         => $this->empresa->id,
            'unidade_id'         => $this->unidade->id,
            'cliente_id'         => $cliente->id,
            'vendedor_id'        => $dono->id,
            'numero'             => 1,
            'condicao_pagamento' => 'boleto',
            'subtotal'           => 200.00,
            'total'              => 200.00,
            'status'             => StatusPedido::Rascunho,
        ]);

        PedidoItem::create([
            'pedido_id'      => $pedido->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 1,
            'preco_unitario' => 200.00,
            'total'          => 200.00,
        ]);

        // No contas_receber yet
        $this->assertEquals(0, ContaReceber::withoutGlobalScopes()->count());

        // Simulate the confirmation logic from PedidoController::updateStatus
        $this->actingAs($dono);
        session(['unidade_id' => $this->unidade->id, 'empresa_id' => $this->empresa->id]);

        DB::transaction(function () use ($pedido) {
            ContaReceber::create([
                'empresa_id'      => $pedido->empresa_id,
                'unidade_id'      => $pedido->unidade_id,
                'cliente_id'      => $pedido->cliente_id,
                'descricao'       => "Pedido #{$pedido->numero}",
                'valor'           => $pedido->total,
                'vencimento'      => now()->addDays(30),
                'forma_pagamento' => $pedido->condicao_pagamento ?? 'a_definir',
                'parcela'         => 1,
                'total_parcelas'  => 1,
                'status'          => 'pendente',
            ]);
            $pedido->update(['status' => StatusPedido::Confirmado]);
        });

        $conta = ContaReceber::withoutGlobalScopes()->first();
        $this->assertNotNull($conta);
        $this->assertEquals(200.00, (float) $conta->valor);
        $this->assertEquals($cliente->id, $conta->cliente_id);
        $this->assertEquals('pendente', $conta->status);
        $this->assertEquals(StatusPedido::Confirmado, $pedido->fresh()->status);
    }

    public function test_pedido_reduces_stock_on_faturamento(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);

        // Create initial stock entry
        $this->darEntradaEstoque($this->unidade, $produto, 50, [
            'custo_unitario' => 50.00,
            'user_id'        => $dono->id,
            'observacoes'    => 'Estoque inicial',
        ]);

        // Create pedido with items
        $pedido = Pedido::withoutGlobalScopes()->create([
            'empresa_id'  => $this->empresa->id,
            'unidade_id'  => $this->unidade->id,
            'cliente_id'  => $cliente->id,
            'vendedor_id' => $dono->id,
            'numero'      => 1,
            'subtotal'    => 1000.00,
            'total'       => 1000.00,
            'status'      => StatusPedido::Confirmado,
        ]);

        PedidoItem::create([
            'pedido_id'      => $pedido->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 10,
            'preco_unitario' => 100.00,
            'total'          => 1000.00,
        ]);

        // Faturar pela ROTA de verdade. A versão antiga deste teste copiava a
        // lógica do PedidoController aqui dentro e por isso ficou cega à
        // mudança para estoque_id/SaldoEstoque de 12/08/2026.
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.update-status', $pedido), ['status' => 'faturado']);

        // Check stock was reduced
        $movSaida = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('tipo', TipoMovimentacaoEstoque::Saida)
            ->latest('id')
            ->first();

        $this->assertNotNull($movSaida);
        $this->assertEquals($this->estoquePrincipal($this->unidade), $movSaida->estoque_id);
        $this->assertEquals(10.000, (float) $movSaida->quantidade);
        $this->assertEquals(50.000, (float) $movSaida->quantidade_anterior);
        $this->assertEquals(40.000, (float) $movSaida->quantidade_posterior);
        $this->assertEquals(StatusPedido::Faturado, $pedido->fresh()->status);
        $this->assertEquals(40.0, SaldoEstoque::naUnidade($this->unidade->id, $produto->id));
    }

    /** 23/11/2026: o pedido da tela respeita o desconto máximo da loja, como o PDV. */
    public function test_pedido_acima_do_desconto_maximo_da_loja_nao_e_gravado(): void
    {
        \App\Models\ConfiguracaoLoja::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'desconto_max_percentual' => 10,
        ]);
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $cliente  = $this->createCliente($this->empresa);
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $pedido = fn (array $extra, float $percItem = 0) => array_merge([
            'cliente_id' => $cliente->id, 'vendedor_id' => $vendedor->id, 'condicao_pagamento' => 'boleto',
            'desconto_percentual' => 0, 'desconto_valor' => 0,
            'itens' => [['produto_id' => $produto->id, 'quantidade' => 2, 'preco_unitario' => 100.00, 'desconto_percentual' => $percItem]],
        ], $extra);

        // 15% no geral (R$ 30 de R$ 200): barrado
        $this->actingAsUser($vendedor, $this->unidade)->post(route('app.pedidos.store'), $pedido(['desconto_valor' => 30]))
            ->assertSessionHas('error', 'Desconto acima do máximo permitido pela loja (10%).');
        // 6% no item + 5% no geral = acima de 10%: barrado
        $this->actingAsUser($vendedor, $this->unidade)->post(route('app.pedidos.store'), $pedido(['desconto_percentual' => 5, 'desconto_valor' => null], 6))
            ->assertSessionHas('error');
        $this->assertSame(0, Pedido::withoutGlobalScopes()->count());

        // Dentro do limite (R$ 20 = 10%): grava
        $this->actingAsUser($vendedor, $this->unidade)->post(route('app.pedidos.store'), $pedido(['desconto_valor' => 20]))
            ->assertRedirect(route('app.pedidos.index'));
        $criado = Pedido::withoutGlobalScopes()->first();
        $this->assertEquals(180.00, (float) $criado->total);

        // Editar para acima do limite: barrado, pedido intacto
        $this->actingAsUser($vendedor, $this->unidade)->put(route('app.pedidos.update', $criado), $pedido(['desconto_valor' => 50]))
            ->assertSessionHas('error');
        $this->assertEquals(180.00, (float) $criado->fresh()->total);
    }
}
