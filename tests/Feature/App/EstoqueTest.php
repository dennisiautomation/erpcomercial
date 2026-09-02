<?php

namespace Tests\Feature\App;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\EstoqueMovimentacao;
use App\Models\TransferenciaEstoque;
use App\Models\TransferenciaEstoqueItem;
use App\Models\Unidade;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class EstoqueTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_gerente_can_create_manual_movimentacao(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $produto = $this->createProduto($this->empresa);

        // A tela manda uma LISTA de itens desde 12/08/2026 (movimentação em lote)
        $response = $this->actingAsUser($gerente, $this->unidade)
            ->post(route('app.movimentacoes.store'), [
                'tipo'        => 'entrada',
                'itens'       => [
                    ['produto_id' => $produto->id, 'quantidade' => 25, 'custo_unitario' => 10.00],
                ],
                'observacoes' => 'Compra fornecedor',
            ]);

        $response->assertRedirect(route('app.movimentacoes.index'));

        $this->assertDatabaseHas('estoque_movimentacoes', [
            'empresa_id' => $this->empresa->id,
            'produto_id' => $produto->id,
            'estoque_id' => $this->estoquePrincipal($this->unidade),
            'tipo'       => 'entrada',
            'user_id'    => $gerente->id,
        ]);
    }

    public function test_movimentacao_updates_stock_quantity(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $produto = $this->createProduto($this->empresa);

        // First entry: 50 units
        $this->actingAsUser($gerente, $this->unidade)
            ->post(route('app.movimentacoes.store'), [
                'tipo'  => 'entrada',
                'itens' => [
                    ['produto_id' => $produto->id, 'quantidade' => 50, 'custo_unitario' => 10.00],
                ],
            ]);

        $mov1 = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->latest('id')
            ->first();

        $this->assertEquals(0.000, (float) $mov1->quantidade_anterior);
        $this->assertEquals(50.000, (float) $mov1->quantidade_posterior);

        // Second entry: loss of 5 units
        $this->actingAsUser($gerente, $this->unidade)
            ->post(route('app.movimentacoes.store'), [
                'tipo'        => 'perda',
                'itens'       => [
                    ['produto_id' => $produto->id, 'quantidade' => 5],
                ],
                'observacoes' => 'Produto danificado',
            ]);

        $mov2 = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->latest('id')
            ->first();

        $this->assertEquals(50.000, (float) $mov2->quantidade_anterior);
        $this->assertEquals(45.000, (float) $mov2->quantidade_posterior);
    }

    public function test_transferencia_can_be_created(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $produto = $this->createProduto($this->empresa);

        // Create a second unidade as the destination
        $unidade2 = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Filial 1',
            'cnpj'       => '12.345.678/0002-01',
            'cep'        => '20000000',
            'logradouro' => 'Rua Filial',
            'numero'     => '200',
            'bairro'     => 'Centro',
            'cidade'     => 'Rio de Janeiro',
            'uf'         => 'RJ',
            'telefone'   => '21999999999',
            'status'     => 'ativa',
        ]);

        // Create transferencia directly (the controller uses auth()->user()->unidade_id
        // which is the session-tracked value, creating directly is more reliable)
        $transferencia = TransferenciaEstoque::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_origem_id'   => $this->unidade->id,
            'unidade_destino_id'  => $unidade2->id,
            'user_solicitante_id' => $gerente->id,
            'status'              => 'solicitada',
            'observacoes'         => 'Reposicao filial',
        ]);

        TransferenciaEstoqueItem::create([
            'transferencia_estoque_id' => $transferencia->id,
            'produto_id'               => $produto->id,
            'quantidade'               => 10,
        ]);

        $this->assertDatabaseHas('transferencias_estoque', [
            'empresa_id'         => $this->empresa->id,
            'unidade_origem_id'  => $this->unidade->id,
            'unidade_destino_id' => $unidade2->id,
            'status'             => 'solicitada',
        ]);

        $transferencia->refresh();
        $this->assertCount(1, $transferencia->itens);
        $this->assertEquals($gerente->id, $transferencia->user_solicitante_id);
    }

    public function test_transferencia_approval_moves_stock(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $produto = $this->createProduto($this->empresa, ['preco_custo' => 20.00]);

        $unidade2 = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Filial 2',
            'cnpj'       => '12.345.678/0003-01',
            'cep'        => '30000000',
            'logradouro' => 'Rua Filial',
            'numero'     => '300',
            'bairro'     => 'Centro',
            'cidade'     => 'Belo Horizonte',
            'uf'         => 'MG',
            'telefone'   => '31999999999',
            'status'     => 'ativa',
        ]);

        // Create initial stock in origin
        $this->darEntradaEstoque($this->unidade, $produto, 100, [
            'custo_unitario' => 20.00,
            'user_id'        => $gerente->id,
        ]);

        // Create transferencia
        $transferencia = TransferenciaEstoque::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_origem_id'   => $this->unidade->id,
            'unidade_destino_id'  => $unidade2->id,
            'user_solicitante_id' => $gerente->id,
            'status'              => 'solicitada',
        ]);

        TransferenciaEstoqueItem::create([
            'transferencia_estoque_id' => $transferencia->id,
            'produto_id'               => $produto->id,
            'quantidade'               => 30,
        ]);

        // Aprovar pela ROTA de verdade. Este teste já simulou a lógica do
        // controller aqui dentro e por isso não viu a mudança para
        // estoque_id/SaldoEstoque de 12/08/2026 — a cópia passava enquanto o
        // controller mudava. Cobrir o controller é o ponto do teste.
        $response = $this->actingAsUser($gerente, $this->unidade)
            ->patch(route('app.transferencias.aprovar', $transferencia));

        $response->assertRedirect(route('app.transferencias.show', $transferencia));

        $transferencia->refresh();
        $this->assertEquals('aprovada', $transferencia->status);
        $this->assertEquals($gerente->id, $transferencia->user_aprovador_id);

        // Check origin stock decreased
        $movOrigem = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('unidade_id', $this->unidade->id)
            ->where('tipo', TipoMovimentacaoEstoque::Transferencia)
            ->first();

        $this->assertNotNull($movOrigem);
        $this->assertEquals($this->estoquePrincipal($this->unidade), $movOrigem->estoque_id);
        $this->assertEquals(100.000, (float) $movOrigem->quantidade_anterior);
        $this->assertEquals(70.000, (float) $movOrigem->quantidade_posterior);

        // Check destination stock increased
        $movDestino = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('unidade_id', $unidade2->id)
            ->where('tipo', TipoMovimentacaoEstoque::Transferencia)
            ->first();

        $this->assertNotNull($movDestino);
        $this->assertEquals($this->estoquePrincipal($unidade2), $movDestino->estoque_id);
        $this->assertEquals(0.000, (float) $movDestino->quantidade_anterior);
        $this->assertEquals(30.000, (float) $movDestino->quantidade_posterior);

        // O saldo da loja é a soma dos estoques dela (SaldoEstoque é o ponto único)
        $this->assertEquals(70.0, SaldoEstoque::naUnidade($this->unidade->id, $produto->id));
        $this->assertEquals(30.0, SaldoEstoque::naUnidade($unidade2->id, $produto->id));
    }

    public function test_stock_isolated_by_unidade(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $produto = $this->createProduto($this->empresa);

        $unidade2 = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'nome'       => 'Filial Isolada',
            'cnpj'       => '12.345.678/0004-01',
            'cep'        => '80000000',
            'logradouro' => 'Rua Filial',
            'numero'     => '400',
            'bairro'     => 'Centro',
            'cidade'     => 'Curitiba',
            'uf'         => 'PR',
            'telefone'   => '41999999999',
            'status'     => 'ativa',
        ]);

        // Stock in unidade 1
        $this->darEntradaEstoque($this->unidade, $produto, 50, [
            'custo_unitario' => 10.00,
            'user_id'        => $gerente->id,
        ]);

        // Stock in unidade 2
        $this->darEntradaEstoque($unidade2, $produto, 80, [
            'custo_unitario' => 10.00,
            'user_id'        => $gerente->id,
        ]);

        // Gerente at unidade 1 should only see unidade 1 stock
        $response = $this->actingAsUser($gerente, $this->unidade)
            ->get(route('app.movimentacoes.index'));

        $response->assertStatus(200);

        // Verify at DB level: unidade 1 has 50, unidade 2 has 80
        $stockU1 = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('unidade_id', $this->unidade->id)
            ->latest('id')
            ->value('quantidade_posterior');

        $stockU2 = EstoqueMovimentacao::withoutGlobalScopes()
            ->where('produto_id', $produto->id)
            ->where('unidade_id', $unidade2->id)
            ->latest('id')
            ->value('quantidade_posterior');

        $this->assertEquals(50.000, (float) $stockU1);
        $this->assertEquals(80.000, (float) $stockU2);
    }
}
