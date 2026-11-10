<?php

namespace Tests\Feature\App;

use App\Models\CentroCusto;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\PlanoContas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/** DRE com Despesas com Pessoal e competência, fluxo programado e relatório por centro de custo (10/11/2026). */
class DrePessoalCentroCustoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->funcionario = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $this->actingAsUser($this->dono, $this->unidade)->post(route('app.plano-contas.padrao'));
        $this->adm = CentroCusto::withoutGlobalScopes()->create(['empresa_id' => $this->empresa->id, 'codigo' => 'ADM', 'nome' => 'Administrativo', 'ativo' => true]);

        // salário de março pago em abril · INSS de março pendente · aluguel pago em março · sem plano pago em março
        $this->conta('Salário', 2000, '2029-04-05', '3.1.01', ['competencia' => '2029-03-01', 'pago_em' => '2029-04-05', 'funcionario_id' => $this->funcionario->id, 'centro_custo_id' => $this->adm->id]);
        $this->conta('INSS', 700, '2029-04-20', '3.2.01', ['competencia' => '2029-03-01', 'favorecido_nome' => 'INSS']);
        $this->conta('Aluguel', 3000, '2029-03-10', '3.4.01', ['pago_em' => '2029-03-10']);
        $this->conta('Diversos', 150, '2029-03-12', null, ['pago_em' => '2029-03-12']);
        $this->conta('Cancelada', 999, '2029-03-12', '3.4.01', ['status' => 'cancelada', 'competencia' => '2029-03-01']);

        $cliente = $this->createCliente($this->empresa);
        ContaReceber::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'descricao' => 'Venda',
            'valor' => 10000, 'valor_pago' => 10000, 'vencimento' => '2029-03-08', 'pago_em' => '2029-03-08', 'parcela' => 1, 'total_parcelas' => 1, 'status' => 'paga',
        ]);
    }

    private function conta(string $descricao, float $valor, string $vencimento, ?string $plano, array $extra = []): ContaPagar
    {
        $pago = $extra['pago_em'] ?? null;

        return ContaPagar::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'descricao' => $descricao, 'valor' => $valor,
            'valor_pago' => $pago ? $valor : 0, 'vencimento' => $vencimento, 'parcela' => 1, 'total_parcelas' => 1,
            'status' => $pago ? 'paga' : 'pendente',
            'plano_conta_id' => $plano ? PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->where('codigo', $plano)->value('id') : null,
        ]);
    }

    private function dre(array $extra = []): array
    {
        return $this->actingAsUser($this->dono, $this->unidade)
            ->get(route('app.dre.index', $extra + ['data_inicio' => '2029-03-01', 'data_fim' => '2029-03-31']))
            ->assertOk()->viewData('dre');
    }

    public function test_dre_por_caixa_so_conta_o_que_foi_pago_no_periodo(): void
    {
        $dre = $this->dre();
        $linhas = collect($dre['linhas'])->keyBy('chave');

        $this->assertSame('pagamento', $dre['regime']);
        $this->assertEqualsWithDelta(3150.0, $dre['despesasTotal'], 0.001); // aluguel + diversos
        $this->assertEqualsWithDelta(0.0, $dre['despesasPessoal'], 0.001);  // salário pago em abril, INSS pendente
        $this->assertFalse($linhas->has('pessoal'));
        $this->assertEqualsWithDelta(-150.0, $linhas['despesas.sem']['valor'], 0.001);
        $this->assertEqualsWithDelta(6850.0, $dre['resultadoLiquido'], 0.001);
    }

    public function test_dre_por_competencia_traz_a_despesa_para_o_mes_dela(): void
    {
        $dre = $this->dre(['regime' => 'competencia']);
        $linhas = collect($dre['linhas'])->keyBy('chave');

        $this->assertSame('competencia', $dre['regime']);
        $this->assertEqualsWithDelta(2700.0, $dre['despesasPessoal'], 0.001);  // salário + INSS de março
        $this->assertEqualsWithDelta(5850.0, $dre['despesasTotal'], 0.001);    // + aluguel e diversos (pelo vencimento)
        $this->assertSame('Despesas com Pessoal', $linhas['pessoal']['rotulo']);
        $this->assertEqualsWithDelta(-2700.0, $linhas['pessoal']['valor'], 0.001);
        $this->assertSame(1, $linhas['pessoal']['nivel']);
        // a soma das linhas de detalhe fecha com o total
        $detalhe = collect($dre['linhas'])->where('tipo', 'detalhe')->filter(fn ($l) => str_starts_with($l['chave'], 'pessoal.') || str_starts_with($l['chave'], 'despesas.'))->sum('valor');
        $this->assertEqualsWithDelta(-5850.0, $detalhe, 0.001);
    }

    public function test_dre_por_unidade_e_exportacao_abrem_nos_dois_regimes(): void
    {
        $http = fn () => $this->actingAsUser($this->dono, $this->unidade);
        foreach (['pagamento', 'competencia'] as $regime) {
            $http()->get(route('app.dre.por-unidade', ['data_inicio' => '2029-03-01', 'data_fim' => '2029-03-31', 'regime' => $regime]))
                ->assertOk()->assertViewHas('linhasRef');
            $http()->get(route('app.dre.exportar', ['data_inicio' => '2029-03-01', 'data_fim' => '2029-03-31', 'regime' => $regime]))->assertOk();
        }
    }

    public function test_fluxo_lista_o_programado_e_filtra_as_saidas(): void
    {
        $http = fn () => $this->actingAsUser($this->dono, $this->unidade);
        $periodo = ['data_inicio' => '2029-03-01', 'data_fim' => '2029-04-30'];

        $r = $http()->get(route('app.financeiro.fluxo-caixa', $periodo))->assertOk();
        $this->assertSame(['INSS'], $r->viewData('programadas')->pluck('descricao')->all());
        $r->assertViewHas('totalSaidas', 5150.00)->assertViewHas('previstaPagar', 700);

        $r = $http()->get(route('app.financeiro.fluxo-caixa', $periodo + ['centro_custo_id' => $this->adm->id]))->assertOk();
        $r->assertViewHas('totalSaidas', 2000.00)->assertViewHas('totalEntradas', 10000.00);
        $this->assertCount(0, $r->viewData('programadas'));
    }

    public function test_despesas_por_centro_de_custo(): void
    {
        $http = fn () => $this->actingAsUser($this->dono, $this->unidade);
        $periodo = ['data_inicio' => '2029-03-01', 'data_fim' => '2029-03-31'];

        $r = $http()->get(route('app.financeiro.despesas-centro-custo', $periodo + ['regime' => 'competencia']))->assertOk();
        $centros = $r->viewData('centros')->keyBy('nome');
        $this->assertEqualsWithDelta(2000.0, $centros['Administrativo']['pessoal'], 0.001);
        $this->assertEqualsWithDelta(3850.0, $centros['Sem centro de custo']['total'], 0.001); // INSS + aluguel + diversos
        $this->assertEqualsWithDelta(5850.0, $r->viewData('totais')['total'], 0.001);
        $this->assertEqualsWithDelta(2700.0, $r->viewData('totais')['pessoal'], 0.001);

        $r = $http()->get(route('app.financeiro.despesas-centro-custo', $periodo))->assertOk(); // pagamento
        $this->assertEqualsWithDelta(3150.0, $r->viewData('totais')['total'], 0.001);

        $this->actingAsUser($this->funcionario, $this->unidade)
            ->get(route('app.financeiro.despesas-centro-custo', $periodo))->assertStatus(403);
    }
}
