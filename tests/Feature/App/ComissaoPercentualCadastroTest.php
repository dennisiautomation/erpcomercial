<?php

namespace Tests\Feature\App;

use App\Models\Comissao;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * 11/11/2026 (CellMaster / N S BORBA): o PDV gravava 5% fixo e ignorava o % do
 * cadastro do funcionário. Agora vale o cadastro; vazio ou 0% = sem comissão.
 */
class ComissaoPercentualCadastroTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function venderNoPdv($operador, ?int $vendedorId = null): Venda
    {
        $produto = $this->createProduto($this->empresa, ['preco_venda' => 200.00]);
        $caixa = $this->openCaixa($this->empresa, $this->unidade, $operador);

        $this->actingAsUser($operador, $this->unidade)
            ->withSession(['caixa_id' => $caixa->id])
            ->postJson(route('app.pdv.venda'), array_filter([
                'vendedor_id' => $vendedorId,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1, 'preco_unitario' => 200.00, 'desconto_valor' => 0]],
                'pagamentos' => [['forma' => 'dinheiro', 'valor' => 200.00]],
            ]))->assertSuccessful();

        return Venda::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_pdv_usa_o_percentual_do_cadastro_do_vendedor(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor', ['comissao_percentual' => 1.00]);

        $venda = $this->venderNoPdv($operador, $vendedor->id);

        $comissao = Comissao::withoutGlobalScopes()->where('venda_id', $venda->id)->firstOrFail();
        $this->assertSame($vendedor->id, (int) $comissao->user_id);
        $this->assertEquals(1.00, (float) $comissao->percentual);
        $this->assertEquals(2.00, (float) $comissao->valor_comissao);
    }

    public function test_pdv_sem_percentual_ou_zero_nao_gera_comissao(): void
    {
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa'); // sem % (vazio)
        $zero = $this->createUser($this->empresa, $this->unidade, 'vendedor', ['comissao_percentual' => 0]);

        $v1 = $this->venderNoPdv($operador);
        $v2 = $this->venderNoPdv($operador, $zero->id);

        $this->assertSame(0, Comissao::withoutGlobalScopes()->whereIn('venda_id', [$v1->id, $v2->id])->count());
    }

    public function test_trocar_vendedor_recalcula_com_o_percentual_do_novo(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $operador = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $antigo = $this->createUser($this->empresa, $this->unidade, 'vendedor', ['comissao_percentual' => 1.00]);
        $novo = $this->createUser($this->empresa, $this->unidade, 'vendedor', ['comissao_percentual' => 3.00]);
        $semComissao = $this->createUser($this->empresa, $this->unidade, 'vendedor', ['comissao_percentual' => 0]);

        $venda = $this->venderNoPdv($operador, $antigo->id);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.vendas.trocar-vendedor', $venda), ['vendedor_id' => $novo->id]);
        $comissao = Comissao::withoutGlobalScopes()->where('venda_id', $venda->id)->firstOrFail();
        $this->assertSame($novo->id, (int) $comissao->user_id);
        $this->assertEquals(3.00, (float) $comissao->percentual);
        $this->assertEquals(6.00, (float) $comissao->valor_comissao);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.vendas.trocar-vendedor', $venda), ['vendedor_id' => $semComissao->id]);
        $this->assertSame(0, Comissao::withoutGlobalScopes()->where('venda_id', $venda->id)->whereNull('deleted_at')->count());
        $this->assertSame(1, Comissao::withoutGlobalScopes()->where('venda_id', $venda->id)->whereNotNull('deleted_at')->count()); // soft delete: recuperável
    }
}
