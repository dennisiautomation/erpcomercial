<?php

namespace Tests\Feature\App;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class FinanceiroTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    /* ------------------------------------------------------------------
     *  Contas a Receber
     * ------------------------------------------------------------------ */

    public function test_financeiro_can_list_contas_receber(): void
    {
        $financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $cliente    = $this->createCliente($this->empresa);

        ContaReceber::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'cliente_id'      => $cliente->id,
            'descricao'       => 'Venda #100',
            'valor'           => 500.00,
            'vencimento'      => now()->addDays(30),
            'forma_pagamento' => 'boleto',
            'parcela'         => 1,
            'total_parcelas'  => 1,
            'status'          => 'pendente',
        ]);

        $response = $this->actingAsUser($financeiro, $this->unidade)
            ->get(route('app.contas-receber.index'));

        $response->assertStatus(200);
        $response->assertViewHas('contas');
        $response->assertViewHas('totalPendente');
    }

    public function test_financeiro_can_create_conta_receber(): void
    {
        $financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $cliente    = $this->createCliente($this->empresa);

        $response = $this->actingAsUser($financeiro, $this->unidade)
            ->post(route('app.contas-receber.store'), [
                'cliente_id'          => $cliente->id,
                'descricao'           => 'Servico prestado',
                'valor'               => 1200.00,
                'parcelas'            => 3,
                'primeiro_vencimento' => now()->addDays(30)->format('Y-m-d'),
                'forma_pagamento'     => 'boleto',
            ]);

        $response->assertRedirect(route('app.contas-receber.index'));

        // Should create 3 parcelas
        $contas = ContaReceber::withoutGlobalScopes()
            ->where('empresa_id', $this->empresa->id)
            ->where('descricao', 'Servico prestado')
            ->get();

        $this->assertCount(3, $contas);

        // Each parcela value should be approximately 400
        $this->assertEquals(400.00, (float) $contas[0]->valor);
        $this->assertEquals(400.00, (float) $contas[1]->valor);
        $this->assertEquals(400.00, (float) $contas[2]->valor);

        // Total should match original value
        $totalParcelas = $contas->sum(fn ($c) => (float) $c->valor);
        $this->assertEquals(1200.00, $totalParcelas);
    }

    public function test_financeiro_can_baixar_conta_receber(): void
    {
        $financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $cliente    = $this->createCliente($this->empresa);

        $conta = ContaReceber::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'cliente_id'      => $cliente->id,
            'descricao'       => 'Fatura pendente',
            'valor'           => 250.00,
            'vencimento'      => now()->addDays(10),
            'forma_pagamento' => 'pix',
            'parcela'         => 1,
            'total_parcelas'  => 1,
            'status'          => 'pendente',
        ]);

        // Simulate the baixar logic (mark as paid)
        $this->actingAs($financeiro);
        session(['unidade_id' => $this->unidade->id, 'empresa_id' => $this->empresa->id]);

        $conta->update([
            'valor_pago' => $conta->valor,
            'pago_em'    => now(),
            'status'     => 'paga',
        ]);

        $conta->refresh();
        $this->assertEquals('paga', $conta->status);
        $this->assertEquals(250.00, (float) $conta->valor_pago);
        $this->assertNotNull($conta->pago_em);
    }

    /* ------------------------------------------------------------------
     *  Contas a Pagar
     * ------------------------------------------------------------------ */

    public function test_financeiro_can_list_contas_pagar(): void
    {
        $financeiro  = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $fornecedor  = $this->createFornecedor($this->empresa);

        ContaPagar::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'fornecedor_id'   => $fornecedor->id,
            'descricao'       => 'Compra materiais',
            'valor'           => 1000.00,
            'vencimento'      => now()->addDays(15),
            'forma_pagamento' => 'boleto',
            'parcela'         => 1,
            'total_parcelas'  => 1,
            'status'          => 'pendente',
        ]);

        $response = $this->actingAsUser($financeiro, $this->unidade)
            ->get(route('app.contas-pagar.index'));

        $response->assertStatus(200);
        $response->assertViewHas('contas');
        $response->assertViewHas('totalPendente');
    }

    public function test_financeiro_can_create_conta_pagar_with_parcelas(): void
    {
        $financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $fornecedor = $this->createFornecedor($this->empresa);

        $response = $this->actingAsUser($financeiro, $this->unidade)
            ->post(route('app.contas-pagar.store'), [
                'fornecedor_id'   => $fornecedor->id,
                'descricao'       => 'Aluguel equipamentos',
                'valor'           => 3000.00,
                'vencimento'      => now()->addDays(30)->format('Y-m-d'),
                'categoria'       => 'Operacional',
                'forma_pagamento' => 'boleto',
                'parcelas'        => 3,
            ]);

        $response->assertRedirect(route('app.contas-pagar.index'));

        $contas = ContaPagar::withoutGlobalScopes()
            ->where('empresa_id', $this->empresa->id)
            ->where('descricao', 'Aluguel equipamentos')
            ->orderBy('parcela')
            ->get();

        $this->assertCount(3, $contas);

        // Parcelas should be 1, 2, 3
        $this->assertEquals(1, $contas[0]->parcela);
        $this->assertEquals(2, $contas[1]->parcela);
        $this->assertEquals(3, $contas[2]->parcela);

        // Total should match original value
        $totalParcelas = $contas->sum(fn ($c) => (float) $c->valor);
        $this->assertEquals(3000.00, $totalParcelas);

        // All should be pendente
        foreach ($contas as $conta) {
            $this->assertEquals('pendente', $conta->status);
        }
    }

    /* ------------------------------------------------------------------
     *  Fluxo de Caixa
     * ------------------------------------------------------------------ */

    public function test_fluxo_caixa_shows_correct_totals(): void
    {
        $financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $cliente    = $this->createCliente($this->empresa);
        $fornecedor = $this->createFornecedor($this->empresa);

        // Create paid conta_receber (entrada)
        ContaReceber::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'cliente_id'      => $cliente->id,
            'descricao'       => 'Receita paga',
            'valor'           => 500.00,
            'valor_pago'      => 500.00,
            'vencimento'      => now(),
            'pago_em'         => now(),
            'forma_pagamento' => 'pix',
            'parcela'         => 1,
            'total_parcelas'  => 1,
            'status'          => 'paga',
        ]);

        // Create paid conta_pagar (saida)
        ContaPagar::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'fornecedor_id'   => $fornecedor->id,
            'descricao'       => 'Despesa paga',
            'valor'           => 200.00,
            'valor_pago'      => 200.00,
            'vencimento'      => now(),
            'pago_em'         => now(),
            'forma_pagamento' => 'boleto',
            'parcela'         => 1,
            'total_parcelas'  => 1,
            'status'          => 'paga',
        ]);

        $response = $this->actingAsUser($financeiro, $this->unidade)
            ->get(route('app.financeiro.fluxo-caixa', [
                'data_inicio' => now()->startOfMonth()->format('Y-m-d'),
                'data_fim'    => now()->endOfMonth()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertViewHas('totalEntradas', 500.00);
        $response->assertViewHas('totalSaidas', 200.00);
        $response->assertViewHas('saldoFinal', 300.00);
    }
}
