<?php

namespace Tests\Feature\App;

use App\Enums\TipoMovimentacaoCaixa;
use App\Models\MovimentacaoCaixa;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/** Venda de balcão (fora do PDV) também entra no caixa — 28/10/2026. */
class VendaBalcaoCaixaTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function payload($produto): array
    {
        return [
            'forma_pagamento' => 'dinheiro',
            'itens' => [[
                'produto_id'     => $produto->id,
                'quantidade'     => 2,
                'preco_unitario' => 50.00,
            ]],
        ];
    }

    public function test_venda_de_balcao_sem_caixa_aberto_e_recusada(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 20.00, 'user_id' => $dono->id]);

        $this->actingAsUser($dono, $this->unidade)
            ->from(route('app.vendas.create'))
            ->post(route('app.vendas.store'), $this->payload($produto))
            ->assertRedirect(route('app.vendas.create'))
            ->assertSessionHas('error');

        $this->assertEquals(0, Venda::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count());
    }

    public function test_venda_de_balcao_entra_no_caixa(): void
    {
        $dono    = $this->createUser($this->empresa, $this->unidade, 'dono');
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 50.00]);
        $this->darEntradaEstoque($this->unidade, $produto, 10, ['custo_unitario' => 20.00, 'user_id' => $dono->id]);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $dono);

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.vendas.store'), $this->payload($produto))
            ->assertSessionHas('success');

        $venda = Venda::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->firstOrFail();
        $this->assertEquals($caixa->id, $venda->caixa_id);

        $mov = MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)->first();
        $this->assertNotNull($mov);
        $this->assertEquals(TipoMovimentacaoCaixa::Venda, $mov->tipo);
        $this->assertEquals('dinheiro', $mov->forma_pagamento);
        $this->assertEquals(100.00, (float) $mov->valor);
    }
}
