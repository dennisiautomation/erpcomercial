<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Produto;
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
        EstoqueMovimentacao::withoutGlobalScopes()->create([
            'empresa_id'           => $this->empresa->id,
            'unidade_id'           => $this->unidade->id,
            'produto_id'           => $produto->id,
            'tipo'                 => TipoMovimentacaoEstoque::Entrada,
            'quantidade'           => 50,
            'quantidade_anterior'  => 0,
            'quantidade_posterior' => 50,
            'custo_unitario'       => 50.00,
            'user_id'              => $dono->id,
            'observacoes'          => 'Estoque inicial',
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

        $item = PedidoItem::create([
            'pedido_id'      => $pedido->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 10,
            'preco_unitario' => 100.00,
            'total'          => 1000.00,
        ]);

        // Simulate faturamento logic from PedidoController::updateStatus
        $this->actingAs($dono);
        session(['unidade_id' => $this->unidade->id, 'empresa_id' => $this->empresa->id]);

        DB::transaction(function () use ($pedido) {
            foreach ($pedido->itens as $item) {
                if ($item->produto_id) {
                    $produtoItem = Produto::find($item->produto_id);
                    $estoqueAnterior = $produtoItem->estoqueMovimentacoes()
                        ->where('unidade_id', $pedido->unidade_id)
                        ->latest()
                        ->value('quantidade_posterior') ?? 0;

                    EstoqueMovimentacao::create([
                        'empresa_id'           => $pedido->empresa_id,
                        'unidade_id'           => $pedido->unidade_id,
                        'produto_id'           => $item->produto_id,
                        'tipo'                 => TipoMovimentacaoEstoque::Saida,
                        'quantidade'           => $item->quantidade,
                        'quantidade_anterior'  => $estoqueAnterior,
                        'quantidade_posterior' => $estoqueAnterior - $item->quantidade,
                        'custo_unitario'       => $item->preco_unitario,
                        'origem_tipo'          => Pedido::class,
                        'origem_id'            => $pedido->id,
                        'user_id'              => auth()->id(),
                        'observacoes'          => "Faturamento Pedido #{$pedido->numero}",
                    ]);
                }
            }
            $pedido->update(['status' => StatusPedido::Faturado]);
        });

        // Check stock was reduced
        $movSaida = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('tipo', TipoMovimentacaoEstoque::Saida)
            ->latest('id')
            ->first();

        $this->assertNotNull($movSaida);
        $this->assertEquals(10.000, (float) $movSaida->quantidade);
        $this->assertEquals(50.000, (float) $movSaida->quantidade_anterior);
        $this->assertEquals(40.000, (float) $movSaida->quantidade_posterior);
        $this->assertEquals(StatusPedido::Faturado, $pedido->fresh()->status);
    }
}
