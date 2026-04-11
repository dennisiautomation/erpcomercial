<?php

namespace Tests\Feature\App;

use App\Enums\StatusCaixa;
use App\Enums\TipoMovimentacaoCaixa;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\MovimentacaoCaixa;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PdvTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    /* ------------------------------------------------------------------
     *  Caixa Operations
     * ------------------------------------------------------------------ */

    public function test_caixa_can_open_caixa(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');

        $response = $this->actingAsUser($operador, $this->unidade)
            ->post(route('app.caixa.abrir'), [
                'numero_caixa'   => 1,
                'valor_abertura' => 200.00,
            ]);

        $response->assertRedirect(route('app.pdv.index'));

        $this->assertDatabaseHas('caixas', [
            'empresa_id'     => $this->empresa->id,
            'unidade_id'     => $this->unidade->id,
            'user_id'        => $operador->id,
            'numero_caixa'   => 1,
            'status'         => 'aberto',
        ]);

        // Should also create an abertura movimentacao
        $this->assertDatabaseHas('movimentacoes_caixa', [
            'tipo'  => 'abertura',
            'valor' => 200.00,
        ]);
    }

    public function test_caixa_can_register_sale(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 2,
                        'preco_unitario' => 50.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 100.00],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'venda', 'cupom']);

        $this->assertDatabaseHas('vendas', [
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'caixa_id'   => $caixa->id,
            'total'      => 100.00,
            'status'     => 'concluida',
            'tipo'       => 'pdv',
        ]);
    }

    public function test_sale_deducts_stock(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 30.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        // Create initial stock
        EstoqueMovimentacao::withoutGlobalScopes()->create([
            'empresa_id'           => $this->empresa->id,
            'unidade_id'           => $this->unidade->id,
            'produto_id'           => $produto->id,
            'tipo'                 => TipoMovimentacaoEstoque::Entrada,
            'quantidade'           => 100,
            'quantidade_anterior'  => 0,
            'quantidade_posterior' => 100,
            'custo_unitario'       => 15.00,
            'user_id'              => $operador->id,
        ]);

        $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 3,
                        'preco_unitario' => 30.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 90.00],
                ],
            ]);

        // Verify stock deduction
        $movSaida = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('tipo', TipoMovimentacaoEstoque::Saida)
            ->first();

        $this->assertNotNull($movSaida);
        $this->assertEquals(3.000, (float) $movSaida->quantidade);
        $this->assertEquals(100.000, (float) $movSaida->quantidade_anterior);
        $this->assertEquals(97.000, (float) $movSaida->quantidade_posterior);
    }

    public function test_sale_creates_movimentacao_caixa(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 75.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 75.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 75.00],
                ],
            ]);

        $this->assertDatabaseHas('movimentacoes_caixa', [
            'caixa_id' => $caixa->id,
            'tipo'     => 'venda',
            'valor'    => 75.00,
        ]);
    }

    public function test_sale_creates_contas_receber(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 100.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 100.00],
                ],
            ]);

        $venda = Venda::withoutGlobalScopes()->first();

        $conta = ContaReceber::withoutGlobalScopes()
            ->where('venda_id', $venda->id)
            ->first();

        $this->assertNotNull($conta);
        $this->assertEquals(100.00, (float) $conta->valor);
        $this->assertEquals('paga', $conta->status);
        $this->assertEquals('dinheiro', $conta->forma_pagamento);
    }

    public function test_sale_calculates_comissao(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 200.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 200.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 200.00],
                ],
            ]);

        $venda = Venda::withoutGlobalScopes()->first();

        $comissao = Comissao::withoutGlobalScopes()
            ->where('venda_id', $venda->id)
            ->first();

        $this->assertNotNull($comissao);
        // Default 5% commission
        $this->assertEquals(5.00, (float) $comissao->percentual);
        $this->assertEquals(10.00, (float) $comissao->valor_comissao);
        $this->assertEquals(200.00, (float) $comissao->valor_venda);
        $this->assertEquals('pendente', $comissao->status);
    }

    public function test_sale_returns_cupom_data(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 50.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 50.00],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('cupom'));
        $this->assertNotNull($response->json('venda.id'));
    }

    public function test_split_payment_works(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, ['preco_venda' => 100.00]);
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), [
                'itens' => [
                    [
                        'produto_id'     => $produto->id,
                        'quantidade'     => 1,
                        'preco_unitario' => 100.00,
                        'desconto_valor' => 0,
                    ],
                ],
                'pagamentos' => [
                    ['forma' => 'dinheiro', 'valor' => 60.00],
                    ['forma' => 'cartao_credito', 'valor' => 40.00],
                ],
            ]);

        $response->assertOk();

        $venda = Venda::withoutGlobalScopes()->first();
        $this->assertEquals('misto', $venda->forma_pagamento);
        $this->assertEquals(100.00, (float) $venda->total);

        // Should create 2 contas_receber entries (one per payment)
        $contas = ContaReceber::withoutGlobalScopes()
            ->where('venda_id', $venda->id)
            ->get();

        $this->assertEquals(2, $contas->count());
    }

    /* ------------------------------------------------------------------
     *  Sangria / Suprimento
     * ------------------------------------------------------------------ */

    public function test_caixa_sangria_registers_movimentacao(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.caixa.sangria'), [
                'valor'     => 50.00,
                'descricao' => 'Retirada para deposito',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('movimentacoes_caixa', [
            'caixa_id'  => $caixa->id,
            'tipo'      => 'sangria',
            'valor'     => 50.00,
            'descricao' => 'Retirada para deposito',
        ]);
    }

    public function test_caixa_suprimento_registers_movimentacao(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.caixa.suprimento'), [
                'valor'     => 100.00,
                'descricao' => 'Troco adicional',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('movimentacoes_caixa', [
            'caixa_id'  => $caixa->id,
            'tipo'      => 'suprimento',
            'valor'     => 100.00,
            'descricao' => 'Troco adicional',
        ]);
    }

    /* ------------------------------------------------------------------
     *  Fechamento
     * ------------------------------------------------------------------ */

    public function test_caixa_fechamento_calculates_correctly(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $caixa    = $this->openCaixa($this->empresa, $this->unidade, $operador, 200.00);

        // Create movimentacao abertura (the openCaixa helper does not create it)
        MovimentacaoCaixa::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'caixa_id'   => $caixa->id,
            'tipo'       => TipoMovimentacaoCaixa::Abertura,
            'valor'      => 200.00,
            'descricao'  => 'Abertura de caixa',
            'user_id'    => $operador->id,
        ]);

        // Simulate a sale movimentacao
        MovimentacaoCaixa::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'caixa_id'   => $caixa->id,
            'tipo'       => TipoMovimentacaoCaixa::Venda,
            'valor'      => 300.00,
            'descricao'  => 'Venda #1',
            'user_id'    => $operador->id,
        ]);

        // Simulate a sangria
        MovimentacaoCaixa::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'caixa_id'   => $caixa->id,
            'tipo'       => TipoMovimentacaoCaixa::Sangria,
            'valor'      => 50.00,
            'descricao'  => 'Sangria',
            'user_id'    => $operador->id,
        ]);

        // Expected: abertura(200) + venda(300) - sangria(50) = 450
        $response = $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->post(route('app.caixa.fechar'), [
                'valor_contado' => 450.00,
            ]);

        $response->assertRedirect(route('app.pdv.index'));

        $caixa->refresh();
        $this->assertEquals(StatusCaixa::Fechado, $caixa->status);
        $this->assertEquals(450.00, (float) $caixa->valor_fechamento);
        $this->assertEquals(450.00, (float) $caixa->valor_esperado);
    }

    /* ------------------------------------------------------------------
     *  Product Search
     * ------------------------------------------------------------------ */

    public function test_buscar_produto_by_barcode(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, [
            'codigo_barras' => '7891234567890',
            'descricao'     => 'Refrigerante Cola',
        ]);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->getJson(route('app.pdv.buscar-produto', ['codigo' => '7891234567890']));

        $response->assertOk();
        $response->assertJsonFragment(['codigo_barras' => '7891234567890']);
    }

    public function test_buscar_produto_by_name(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $produto  = $this->createProduto($this->empresa, [
            'descricao' => 'Agua Mineral 500ml',
        ]);

        $response = $this->actingAsUser($operador, $this->unidade)
            ->getJson(route('app.pdv.buscar-produto', ['codigo' => 'Agua']));

        $response->assertOk();
        $response->assertJsonFragment(['descricao' => 'Agua Mineral 500ml']);
    }
}
