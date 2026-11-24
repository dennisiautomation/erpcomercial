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
 * Chamado da DK (23/11/2026): "a cliente pagou uma parte em dinheiro e outra via
 * Pix, mas não foi possível registrar as duas formas" — R$ 500 em dinheiro +
 * R$ 77,60 no PIX num pedido de R$ 577,60, na tela Faturar pedido.
 */
class PedidoPagamentoDivididoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function cenario(float $total = 577.60): array
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $cliente = $this->createCliente($this->empresa);
        $produto = $this->createProduto($this->empresa, ['preco_venda' => $total]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 50.00, 'user_id' => $dono->id]);

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
            'quantidade'     => 1,
            'preco_unitario' => $total,
            'total'          => $total,
        ]);
        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.pedidos.update-status', $pedido), ['status' => 'confirmado']);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $dono);

        return [$dono, $pedido->fresh(), $caixa];
    }

    private function faturar($dono, Pedido $pedido, array $linhas)
    {
        return $this->actingAsUser($dono, $this->unidade)
            ->from(route('app.pedidos.show', $pedido))
            ->post(route('app.pedidos.faturar', $pedido), [
                'documento'  => 'nenhum',
                'dividir'    => '1',
                'pagamentos' => $linhas,
            ]);
    }

    public function test_dinheiro_e_pix_na_mesma_venda_entram_no_caixa_e_no_contas_a_receber(): void
    {
        [$dono, $pedido, $caixa] = $this->cenario();

        $this->faturar($dono, $pedido, [
            ['forma' => 'dinheiro', 'valor' => '500,00'],
            ['forma' => 'pix', 'valor' => '77,60'],
        ])->assertRedirect(route('app.pedidos.show', $pedido))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertEquals(StatusPedido::Faturado, $pedido->fresh()->status);
        $this->assertEquals('misto', $venda->forma_pagamento);
        $this->assertCount(2, $venda->pagamento_detalhes);
        $this->assertEquals(['dinheiro', 'pix'], array_column($venda->pagamento_detalhes, 'forma'));
        $this->assertEquals([500.00, 77.60], array_map('floatval', array_column($venda->pagamento_detalhes, 'valor')));

        // Caixa: uma entrada por forma
        $movs = MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)
            ->where('tipo', TipoMovimentacaoCaixa::Venda)->orderBy('id')->get();
        $this->assertCount(2, $movs);
        $this->assertEquals(['dinheiro', 'pix'], $movs->pluck('forma_pagamento')->all());
        $this->assertEquals([500.00, 77.60], $movs->map(fn ($m) => (float) $m->valor)->all());
        $this->assertTrue($movs->every(fn ($m) => $m->caixa_id === $caixa->id));

        // Contas a receber: a da confirmação vira a 1ª forma; a 2ª nasce paga
        $contas = ContaReceber::withoutGlobalScopes()->where('pedido_id', $pedido->id)->orderBy('id')->get();
        $this->assertCount(2, $contas);
        $this->assertEquals(['dinheiro', 'pix'], $contas->pluck('forma_pagamento')->all());
        $this->assertEquals([500.00, 77.60], $contas->map(fn ($c) => (float) $c->valor_pago)->all());
        $this->assertTrue($contas->every(fn ($c) => $c->status === 'paga' && $c->venda_id === $venda->id));
    }

    public function test_soma_diferente_do_total_nao_fatura(): void
    {
        [$dono, $pedido] = $this->cenario();

        $this->faturar($dono, $pedido, [
            ['forma' => 'dinheiro', 'valor' => '500,00'],
            ['forma' => 'pix', 'valor' => '70,00'],
        ])->assertRedirect(route('app.pedidos.show', $pedido))->assertSessionHas('error');
        $this->assertStringContainsString('falta R$ 7,60', session('error'));

        $this->assertEquals(StatusPedido::Confirmado, $pedido->fresh()->status);
        $this->assertEquals(0, Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->count());
    }

    public function test_uma_linha_so_ou_vale_no_meio_sao_recusados(): void
    {
        [$dono, $pedido] = $this->cenario();

        $this->faturar($dono, $pedido, [['forma' => 'pix', 'valor' => '577,60'], ['forma' => '', 'valor' => '']])
            ->assertSessionHas('error');
        $this->assertStringContainsString('pelo menos duas formas', session('error'));

        $this->faturar($dono, $pedido, [['forma' => 'vale', 'valor' => '77,60'], ['forma' => 'pix', 'valor' => '500,00']])
            ->assertSessionHas('error');
        $this->assertStringContainsString('Vale não entra', session('error'));

        $this->assertEquals(0, Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->count());
    }

    public function test_cartao_de_credito_parcelado_com_dinheiro(): void
    {
        [$dono, $pedido] = $this->cenario(600.00);

        $this->faturar($dono, $pedido, [
            ['forma' => 'dinheiro', 'valor' => '100'],
            ['forma' => 'cartao_credito', 'valor' => '500.00', 'parcelas' => '3'],
        ])->assertSessionMissing('error');

        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();
        $cartao = collect($venda->pagamento_detalhes)->firstWhere('forma', 'cartao_credito');
        $this->assertEquals(3, (int) $cartao['parcelas']);
        $this->assertEquals(500.00, (float) $cartao['valor']);
        $this->assertArrayNotHasKey('parcelas', collect($venda->pagamento_detalhes)->firstWhere('forma', 'dinheiro'));
    }

    public function test_cancelar_a_venda_dividida_estorna_as_duas_formas(): void
    {
        [$dono, $pedido] = $this->cenario();
        $this->faturar($dono, $pedido, [
            ['forma' => 'dinheiro', 'valor' => '500,00'],
            ['forma' => 'pix', 'valor' => '77,60'],
        ]);
        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $pedido->id)->firstOrFail();

        $this->actingAsUser($dono, $this->unidade)
            ->delete(route('app.vendas.destroy', $venda))
            ->assertSessionHas('success');

        $estornos = MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)
            ->where('tipo', TipoMovimentacaoCaixa::Estorno)->orderBy('id')->get();
        $this->assertEqualsCanonicalizing(['dinheiro', 'pix'], $estornos->pluck('forma_pagamento')->all());
        $this->assertEquals(577.60, round($estornos->sum(fn ($m) => (float) $m->valor), 2));
    }

    public function test_tela_do_pedido_oferece_dividir_o_pagamento(): void
    {
        [$dono, $pedido] = $this->cenario();

        $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.pedidos.show', $pedido))
            ->assertOk()
            ->assertSee('Dividir o pagamento')
            ->assertSee('id="faturarDivididoBox"', false)
            ->assertSee('name="dividir"', false);
    }
}
