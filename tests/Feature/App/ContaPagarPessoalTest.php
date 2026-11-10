<?php

namespace Tests\Feature\App;

use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\PlanoContas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Contas a pagar para despesa de pessoal (10/11/2026): favorecido funcionário / imposto,
 * plano de contas e centro de custo com vínculo, recorrência, editar, baixa e estorno.
 */
class ContaPagarPessoalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
        $this->funcionario = $this->createUser($this->empresa, $this->unidade, 'vendedor');
    }

    private function contas(string $descricao)
    {
        return ContaPagar::withoutGlobalScopes()->where('descricao', $descricao)->orderBy('parcela')->get();
    }

    private function planoPadrao(): void
    {
        $this->actingAsUser($this->financeiro, $this->unidade)->post(route('app.plano-contas.padrao'));
    }

    private function conta(string $codigo): PlanoContas
    {
        return PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->where('codigo', $codigo)->firstOrFail();
    }

    public function test_plano_padrao_cria_contas_de_pessoal_e_nao_duplica(): void
    {
        $this->planoPadrao();
        $total = PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count();
        $this->assertSame(count(\App\Http\Controllers\App\PlanoContasController::PADRAO), $total);

        $this->planoPadrao();
        $this->assertSame($total, PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count());

        $salarios = $this->conta('3.1.01');
        $this->assertSame('analitica', $salarios->natureza);
        $this->assertSame($this->conta('3.1')->id, $salarios->parent_id);
    }

    public function test_salario_recorrente_para_funcionario_lanca_doze_meses_classificados(): void
    {
        $this->planoPadrao();
        $plano = $this->conta('3.1.01');
        $centro = CentroCusto::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'ADM', 'nome' => 'Administrativo', 'ativo' => true,
        ]);

        $this->actingAsUser($this->financeiro, $this->unidade)->post(route('app.contas-pagar.store'), [
            'favorecido_tipo'  => 'funcionario',
            'funcionario_id'   => $this->funcionario->id,
            'descricao'        => 'Salário teste',
            'valor'            => 2500,
            'vencimento'       => '2027-01-31',
            'competencia'      => '2026-12',
            'plano_conta_id'   => $plano->id,
            'centro_custo_id'  => $centro->id,
            'recorrente'       => 1,
            'recorrencia_tipo' => 'mensal',
        ])->assertRedirect(route('app.contas-pagar.index'));

        $contas = $this->contas('Salário teste');
        $this->assertCount(12, $contas);
        $this->assertNull($contas[0]->fornecedor_id);
        $this->assertSame($this->funcionario->id, $contas[0]->funcionario_id);
        $this->assertSame($plano->id, $contas[0]->plano_conta_id);
        $this->assertSame('Salários', $contas[0]->categoria);
        $this->assertSame($centro->id, $contas[0]->centro_custo_id);
        $this->assertSame('2027-02-28', $contas[1]->vencimento->format('Y-m-d')); // dia 31 não pula mês
        $this->assertSame('2027-03-31', $contas[2]->vencimento->format('Y-m-d'));
        $this->assertSame('2027-01-01', $contas[1]->competencia->format('Y-m-d'));
        $this->assertCount(1, $contas->pluck('serie_id')->unique());
        $this->assertNotNull($contas[0]->serie_id);
    }

    public function test_guia_de_imposto_bimestral_sem_fornecedor(): void
    {
        $this->actingAsUser($this->financeiro, $this->unidade)->post(route('app.contas-pagar.store'), [
            'favorecido_tipo'  => 'outro',
            'favorecido_nome'  => 'INSS',
            'descricao'        => 'Guia INSS teste',
            'valor'            => 700,
            'vencimento'       => '2027-01-20',
            'recorrente'       => 1,
            'recorrencia_tipo' => 'bimestral',
        ])->assertRedirect(route('app.contas-pagar.index'));

        $contas = $this->contas('Guia INSS teste');
        $this->assertCount(12, $contas);
        $this->assertSame('INSS', $contas[0]->favorecido_label);
        $this->assertSame('bimestral', $contas[0]->recorrencia_tipo);
        $this->assertSame('2027-03-20', $contas[1]->vencimento->format('Y-m-d'));
    }

    public function test_favorecido_obrigatorio_e_isolado_por_empresa(): void
    {
        [$outraEmpresa, $outraUnidade] = $this->createTenant('2');
        $deOutra = $this->createUser($outraEmpresa, $outraUnidade, 'vendedor');
        $base = ['descricao' => 'Recusada', 'valor' => 10, 'vencimento' => '2027-01-10'];
        $http = fn () => $this->actingAsUser($this->financeiro, $this->unidade);

        $http()->post(route('app.contas-pagar.store'), $base)->assertSessionHasErrors('fornecedor_id');
        $http()->post(route('app.contas-pagar.store'), $base + ['favorecido_tipo' => 'outro'])->assertSessionHasErrors('favorecido_nome');
        $http()->post(route('app.contas-pagar.store'), $base + ['favorecido_tipo' => 'funcionario'])->assertSessionHasErrors('funcionario_id');
        $http()->post(route('app.contas-pagar.store'), $base + ['favorecido_tipo' => 'funcionario', 'funcionario_id' => $deOutra->id])
            ->assertSessionHasErrors('funcionario_id');

        $this->assertCount(0, $this->contas('Recusada'));
    }

    public function test_editar_so_este_mes_ou_aplicar_nas_proximas_pendentes(): void
    {
        $http = fn () => $this->actingAsUser($this->financeiro, $this->unidade);
        $http()->post(route('app.contas-pagar.store'), [
            'favorecido_tipo' => 'outro', 'favorecido_nome' => 'Vale-transporte', 'descricao' => 'VT teste',
            'valor' => 300, 'vencimento' => '2027-01-05', 'recorrente' => 1, 'recorrencia_tipo' => 'mensal',
        ]);
        $contas = $this->contas('VT teste');
        $dados = ['favorecido_tipo' => 'outro', 'favorecido_nome' => 'Vale-transporte', 'descricao' => 'VT teste', 'vencimento' => '2027-01-05'];

        $http()->put(route('app.contas-pagar.update', $contas[0]), $dados + ['valor' => 320]);
        $this->assertSame(['320.00', '300.00'], $this->contas('VT teste')->take(2)->pluck('valor')->all());

        $http()->post(route('app.contas-pagar.baixar', $contas[4]), ['valor_pago' => 300, 'pago_em' => now()->format('Y-m-d')]);
        $http()->put(route('app.contas-pagar.update', $contas[2]), ['vencimento' => '2027-03-05', 'valor' => 350, 'aplicar_proximas' => 1] + $dados);

        $valores = $this->contas('VT teste')->pluck('valor')->all();
        $this->assertSame(['320.00', '300.00', '350.00', '350.00', '300.00', '350.00'], array_slice($valores, 0, 6));
        $this->assertSame('2027-04-05', $this->contas('VT teste')[3]->vencimento->format('Y-m-d'));
    }

    public function test_baixa_com_valor_data_e_forma_e_estorno(): void
    {
        $http = fn () => $this->actingAsUser($this->financeiro, $this->unidade);
        $conta = ContaPagar::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'favorecido_nome' => 'FGTS',
            'descricao' => 'Guia FGTS', 'valor' => 200, 'vencimento' => now(), 'parcela' => 1, 'total_parcelas' => 1, 'status' => 'pendente',
        ]);
        $ontem = now()->subDay()->format('Y-m-d');

        $http()->post(route('app.contas-pagar.baixar', $conta), ['valor_pago' => 210.55, 'pago_em' => now()->addDay()->format('Y-m-d')])
            ->assertSessionHasErrors('pago_em');
        $this->assertSame('pendente', $conta->fresh()->status);

        $http()->post(route('app.contas-pagar.baixar', $conta), ['valor_pago' => 210.55, 'pago_em' => $ontem, 'forma_pagamento' => 'pix']);
        $conta->refresh();
        $this->assertSame(['paga', '210.55', $ontem, 'pix'], [$conta->status, $conta->valor_pago, $conta->pago_em->format('Y-m-d'), $conta->forma_pagamento]);

        // paga não se edita
        $http()->put(route('app.contas-pagar.update', $conta), ['favorecido_tipo' => 'outro', 'favorecido_nome' => 'FGTS', 'descricao' => 'X', 'valor' => 1, 'vencimento' => $ontem]);
        $this->assertSame('Guia FGTS', $conta->fresh()->descricao);

        $http()->post(route('app.contas-pagar.estornar', $conta));
        $conta->refresh();
        $this->assertSame('pendente', $conta->status);
        $this->assertNull($conta->pago_em);
        $this->assertSame('0.00', $conta->valor_pago);
    }

    public function test_telas_de_contas_a_pagar_abrem(): void
    {
        $this->planoPadrao();
        $conta = ContaPagar::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'funcionario_id' => $this->funcionario->id,
            'descricao' => 'Vale do dia 20', 'valor' => 800, 'vencimento' => now()->addDays(5), 'competencia' => now()->startOfMonth(),
            'plano_conta_id' => $this->conta('3.1.02')->id, 'categoria' => 'Adiantamento de salário (vale)',
            'parcela' => 1, 'total_parcelas' => 1, 'status' => 'pendente',
        ]);
        $http = fn () => $this->actingAsUser($this->financeiro, $this->unidade);

        $http()->get(route('app.contas-pagar.index'))->assertOk()->assertSee($this->funcionario->name)->assertSee('modalBaixa');
        $http()->get(route('app.contas-pagar.create'))->assertOk()->assertSee('3.1.01 — Salários');
        $http()->get(route('app.contas-pagar.edit', $conta))->assertOk()->assertSee('Vale do dia 20');
        $http()->get(route('app.contas-pagar.show', $conta))->assertOk()->assertSee('3.1.02');
    }
}
