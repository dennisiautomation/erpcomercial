<?php

namespace Tests\Feature\App;

use App\Models\EstoqueMovimentacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Ajuste = balanço (26/11/2026): a quantidade digitada é a CONTADA e o saldo
 * fica igual a ela. Antes o Ajuste somava, igual à Entrada.
 */
class AjusteBalancoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $this->produto = $this->createProduto($this->empresa);
    }

    private function lancar(string $tipo, $quantidade, ?int $produtoId = null)
    {
        return $this->actingAsUser($this->gerente, $this->unidade)
            ->post(route('app.movimentacoes.store'), [
                'tipo'  => $tipo,
                'itens' => [['produto_id' => $produtoId ?? $this->produto->id, 'quantidade' => $quantidade]],
            ]);
    }

    private function ultima(?int $produtoId = null): EstoqueMovimentacao
    {
        return EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produtoId ?? $this->produto->id)->latest('id')->first();
    }

    public function test_ajuste_para_menos_deixa_o_saldo_igual_ao_contado(): void
    {
        $this->lancar('entrada', 10);
        $this->lancar('ajuste', 4)->assertRedirect(route('app.movimentacoes.index'));

        $mov = $this->ultima();
        $this->assertSame('ajuste', $mov->tipo->value);
        $this->assertEquals(10.0, (float) $mov->quantidade_anterior);
        $this->assertEquals(4.0, (float) $mov->quantidade_posterior);
        $this->assertEquals(6.0, (float) $mov->quantidade);
    }

    public function test_ajuste_para_mais_deixa_o_saldo_igual_ao_contado(): void
    {
        $this->lancar('entrada', 10);
        $this->lancar('ajuste', 13);

        $mov = $this->ultima();
        $this->assertEquals(13.0, (float) $mov->quantidade_posterior);
        $this->assertEquals(3.0, (float) $mov->quantidade);
    }

    public function test_ajuste_aceita_zero_e_zera_o_estoque(): void
    {
        $this->lancar('entrada', 7);
        $this->lancar('ajuste', 0)->assertSessionHasNoErrors();

        $mov = $this->ultima();
        $this->assertSame('ajuste', $mov->tipo->value);
        $this->assertEquals(0.0, (float) $mov->quantidade_posterior);
    }

    public function test_ajuste_igual_ao_saldo_nao_grava_movimentacao(): void
    {
        $this->lancar('entrada', 5);
        $antes = EstoqueMovimentacao::withoutGlobalScopes()->count();

        $this->lancar('ajuste', 5)->assertSessionHasNoErrors();

        $this->assertSame($antes, EstoqueMovimentacao::withoutGlobalScopes()->count());
        $this->assertSame('entrada', $this->ultima()->tipo->value);
    }

    public function test_ajuste_repetido_nao_soma(): void
    {
        // O caso real da DONA DOURO em 26/11: Ajuste de 2 duas vezes levou 6 → 8 → 10.
        $this->lancar('entrada', 6);
        $this->lancar('ajuste', 2);
        $this->lancar('ajuste', 2);

        $this->assertEquals(2.0, (float) $this->ultima()->quantidade_posterior);
    }

    public function test_entrada_e_perda_continuam_como_antes_e_nao_aceitam_zero(): void
    {
        $this->lancar('entrada', 10);
        $this->lancar('entrada', 2);
        $this->assertEquals(12.0, (float) $this->ultima()->quantidade_posterior);

        $this->lancar('perda', 3);
        $this->assertEquals(9.0, (float) $this->ultima()->quantidade_posterior);

        $this->lancar('entrada', 0)->assertSessionHasErrors('itens.0.quantidade');
        $this->assertEquals(9.0, (float) $this->ultima()->quantidade_posterior);
    }

    public function test_telas_explicam_o_ajuste_e_mostram_o_sentido_certo(): void
    {
        $this->lancar('entrada', 10);
        $this->lancar('ajuste', 4);

        $nova = $this->actingAsUser($this->gerente, $this->unidade)->get(route('app.movimentacoes.create'));
        $nova->assertOk()->assertSee('Quantidade contada')->assertDontSee('adiciona ao estoque');
        // a tela recebe o saldo atual do produto para mostrar ao lado do campo
        $nova->assertSee('"' . $this->produto->id . '":{', false);

        $lista = $this->actingAsUser($this->gerente, $this->unidade)->get(route('app.movimentacoes.index'));
        $lista->assertOk()->assertSee('bi-arrow-down-short', false);
    }
}
