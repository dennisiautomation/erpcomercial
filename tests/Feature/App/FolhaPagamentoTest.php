<?php

namespace Tests\Feature\App;

use App\Models\ContaPagar;
use App\Models\FolhaItem;
use App\Models\FuncionarioFicha;
use App\Services\FolhaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/** Folha de pagamento (10/11/2026): ficha + guias/benefícios → contas no Contas a Pagar. */
class FolhaPagamentoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->funcionario = $this->createUser($this->empresa, $this->unidade, 'vendedor');
    }

    private function http()
    {
        return $this->actingAsUser($this->dono, $this->unidade);
    }

    private function ficha(array $extra = []): void
    {
        $this->http()->put(route('app.folha.ficha.update', $this->funcionario), $extra + [
            'salario' => 2500, 'dia_pagamento' => 5, 'paga_mes_seguinte' => 1, 'vale_valor' => 1000, 'vale_dia' => 20, 'na_folha' => 1,
        ])->assertRedirect(route('app.folha.index'));
    }

    private function contasFolha()
    {
        // withoutGlobalScopes tira também o SoftDeletes (armadilha 38): filtrar as excluídas à mão
        return ContaPagar::withoutGlobalScopes()->whereNull('deleted_at')->whereNotNull('folha_ref')->orderBy('competencia')->orderBy('vencimento')->get();
    }

    public function test_so_dono_e_financeiro_entram_na_folha(): void
    {
        $this->http()->get(route('app.folha.index'))->assertOk();
        $this->actingAsUser($this->createUser($this->empresa, $this->unidade, 'financeiro'), $this->unidade)
            ->get(route('app.folha.index'))->assertOk();

        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $this->actingAsUser($gerente, $this->unidade)->get(route('app.folha.index'))->assertForbidden();
        $this->actingAsUser($gerente, $this->unidade)->get(route('app.folha.ficha.edit', $this->funcionario))->assertForbidden();
        $this->actingAsUser($gerente, $this->unidade)->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 1])->assertForbidden();
    }

    public function test_ficha_valida_vale_e_nao_aceita_funcionario_de_outra_empresa(): void
    {
        $this->http()->put(route('app.folha.ficha.update', $this->funcionario), ['salario' => 1000, 'dia_pagamento' => 5, 'vale_valor' => 1500])
            ->assertSessionHasErrors('vale_valor');
        $this->assertSame(0, FuncionarioFicha::withoutGlobalScopes()->count());

        [$outra, $outraUnidade] = $this->createTenant('2');
        $deOutra = $this->createUser($outra, $outraUnidade, 'vendedor');
        $this->http()->put(route('app.folha.ficha.update', $deOutra), ['salario' => 1000, 'dia_pagamento' => 5])->assertNotFound();

        $this->ficha();
        $this->ficha(['salario' => 2600]);
        $this->assertSame(1, FuncionarioFicha::withoutGlobalScopes()->count());
        $this->assertSame('2600.00', FuncionarioFicha::withoutGlobalScopes()->first()->salario);
    }

    public function test_lancar_cria_vale_salario_e_guia_sem_duplicar(): void
    {
        $this->http()->post(route('app.plano-contas.padrao'));
        $this->ficha();
        $this->http()->post(route('app.folha.itens.store'), [
            'tipo' => 'encargo', 'nome' => 'Guia INSS', 'favorecido_nome' => 'INSS', 'valor' => 700,
            'dia_vencimento' => 31, 'vence_mes_seguinte' => 1, 'ativo' => 1,
        ])->assertRedirect(route('app.folha.index'));

        $this->http()->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 2])->assertSessionHas('success');
        $contas = $this->contasFolha();
        $this->assertCount(6, $contas);

        $jan = $contas->filter(fn ($c) => $c->competencia->format('Y-m') === '2027-01')->keyBy('folha_ref');
        $this->assertSame(['1000.00', '2027-01-20'], [$jan['vale']->valor, $jan['vale']->vencimento->format('Y-m-d')]);
        $this->assertSame(['1500.00', '2027-02-05'], [$jan['salario']->valor, $jan['salario']->vencimento->format('Y-m-d')]);
        $this->assertSame($this->funcionario->id, $jan['salario']->funcionario_id);
        $this->assertSame('Salários', $jan['salario']->categoria);
        $this->assertNotNull($jan['salario']->plano_conta_id);

        $guia = $jan->first(fn ($c) => str_starts_with($c->folha_ref, 'item:'));
        $this->assertSame(['INSS', '2027-02-28'], [$guia->favorecido_label, $guia->vencimento->format('Y-m-d')]); // dia 31 → 28/02
        $this->assertNull($guia->funcionario_id);

        $this->http()->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 2]);
        $this->assertCount(6, $this->contasFolha());
        $this->assertSame(3, $this->contasFolha()->pluck('serie_id')->unique()->count());
    }

    public function test_atualizar_pendentes_respeita_paga_e_excluida_nao_volta(): void
    {
        $this->ficha(['vale_valor' => 0]);
        $this->http()->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 3]);
        [$jan, $fev, $mar] = $this->contasFolha()->all();

        $this->http()->post(route('app.contas-pagar.baixar', $jan), ['valor_pago' => 2500, 'pago_em' => now()->format('Y-m-d')]);
        $this->http()->delete(route('app.contas-pagar.destroy', $mar));
        $this->ficha(['vale_valor' => 0, 'salario' => 2800]);

        $this->http()->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 3]);
        $this->assertSame('2500.00', $fev->fresh()->valor); // sem marcar, nada muda

        $this->http()->post(route('app.folha.lancar'), ['inicio' => '2027-01', 'meses' => 3, 'atualizar_pendentes' => 1]);
        $this->assertSame('2500.00', $jan->fresh()->valor);   // paga não muda
        $this->assertSame('2800.00', $fev->fresh()->valor);   // pendente atualiza
        $this->assertCount(2, $this->contasFolha());          // a excluída não voltou
    }

    public function test_admissao_desligamento_e_fora_da_folha(): void
    {
        $this->ficha(['vale_valor' => 0, 'admissao' => '2027-02-10', 'demissao' => '2027-03-20']);
        $servico = app(FolhaService::class);

        $this->assertCount(0, $servico->linhas($this->empresa->id, Carbon::parse('2027-01-01')));
        $this->assertCount(1, $servico->linhas($this->empresa->id, Carbon::parse('2027-02-01')));
        $this->assertCount(1, $servico->linhas($this->empresa->id, Carbon::parse('2027-03-01')));
        $this->assertCount(0, $servico->linhas($this->empresa->id, Carbon::parse('2027-04-01')));

        FolhaItem::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'funcionario_id' => $this->funcionario->id, 'tipo' => 'beneficio',
            'nome' => 'Vale-transporte', 'valor' => 200, 'dia_vencimento' => 1, 'vence_mes_seguinte' => false, 'ativo' => true,
        ]);
        $this->assertCount(2, $servico->linhas($this->empresa->id, Carbon::parse('2027-02-01')));
        $this->ficha(['vale_valor' => 0, 'na_folha' => 0]);
        $this->assertCount(0, $servico->linhas($this->empresa->id, Carbon::parse('2027-02-01'))); // benefício segue a ficha
    }

    public function test_telas_da_folha_abrem(): void
    {
        $this->ficha();
        $item = FolhaItem::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'tipo' => 'encargo', 'nome' => 'Guia FGTS', 'valor' => 240, 'dia_vencimento' => 7,
            'vence_mes_seguinte' => true, 'ativo' => true,
        ]);

        $this->http()->get(route('app.folha.index', ['inicio' => '2027-01', 'meses' => 3]))->assertOk()
            ->assertSee($this->funcionario->name)->assertSee('Guia FGTS')->assertSee('Não lançado');
        $this->http()->get(route('app.folha.ficha.edit', $this->funcionario))->assertOk()->assertSee('2500.00');
        $this->http()->get(route('app.folha.itens.create'))->assertOk();
        $this->http()->get(route('app.folha.itens.edit', $item))->assertOk()->assertSee('Guia FGTS');
        $this->http()->delete(route('app.folha.itens.destroy', $item))->assertRedirect(route('app.folha.index'));
        $this->assertSoftDeleted('folha_itens', ['id' => $item->id]);
    }
}
