<?php

namespace Tests\Feature\App;

use App\Models\ContaPagar;
use App\Models\NFeEntrada;
use App\Models\NFeEntradaDevolucao;
use App\Services\EntradaNFe\EntradaNFeService;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Chamado da DK (23/11/2026, Wellington): "deu entrada numa nota, entrou estoque e
 * contas a pagar; como faz a devolução, que o fornecedor vai enviar outra?".
 * O ERP não emite nota de devolução (decisão do Dennis, 24/11): só desfaz
 * estoque e contas a pagar.
 */
class DevolucaoCompraFornecedorTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->estoqueId = $this->estoquePrincipal($this->unidade);
        $this->luva = $this->createProduto($this->empresa, ['descricao' => 'LUVA']);
        $this->gaze = $this->createProduto($this->empresa, ['descricao' => 'GAZE']);
    }

    /**
     * Nota de R$ 1.000,00 em 2 parcelas de R$ 500: 2 CX de luva (cada caixa = 10 UN,
     * R$ 600) + 20 UN de gaze (R$ 400). Concluída pelo serviço de verdade.
     */
    private function entradaConcluida(bool $comContas = true): NFeEntrada
    {
        $this->actingAs($this->dono);
        $this->createFornecedor($this->empresa, ['cpf_cnpj' => '11.222.333/0001-81', 'razao_social' => 'FORNECEDOR HOSPITALAR LTDA']);
        $entrada = NFeEntrada::withoutGlobalScopes()->create([
            'empresa_id'     => $this->empresa->id,
            'unidade_id'     => $this->unidade->id,
            'estoque_id'     => $this->estoqueId,
            'chave_acesso'   => str_repeat('3', 44),
            'numero'         => '4512',
            'serie'          => '1',
            'cnpj_emitente'  => '11222333000181',
            'nome_emitente'  => 'FORNECEDOR HOSPITALAR LTDA',
            'valor_produtos' => 1000,
            'valor_total'    => 1000,
            'duplicatas'     => [
                ['numero' => '001', 'vencimento' => now()->addDays(30)->toDateString(), 'valor' => 500],
                ['numero' => '002', 'vencimento' => now()->addDays(60)->toDateString(), 'valor' => 500],
            ],
            'status'         => 'rascunho',
        ]);
        $entrada->itens()->create(['numero_item' => 1, 'descricao' => 'LUVA CX 10', 'unidade_comercial' => 'CX', 'quantidade' => 2,
            'valor_unitario' => 300, 'valor_total' => 600, 'custo_total' => 600, 'acao' => 'vincular', 'produto_id' => $this->luva->id, 'fator_conversao' => 10]);
        $entrada->itens()->create(['numero_item' => 2, 'descricao' => 'GAZE UN', 'unidade_comercial' => 'UN', 'quantidade' => 20,
            'valor_unitario' => 20, 'valor_total' => 400, 'custo_total' => 400, 'acao' => 'vincular', 'produto_id' => $this->gaze->id, 'fator_conversao' => 1]);

        return app(EntradaNFeService::class)->concluir($entrada, [
            'estoque_id'        => $this->estoqueId,
            'gerar_conta_pagar' => $comContas,
            'itens'             => [],
        ], $this->dono->id);
    }

    private function devolver(NFeEntrada $e, array $qtds, string $motivo = 'Mercadoria com defeito, fornecedor manda outra', $user = null)
    {
        $user ??= $this->dono;

        return $this->actingAsUser($user, $this->unidade)
            ->from(route('app.entradas-nfe.show', $e))
            ->post(route('app.entradas-nfe.devolver', $e), ['motivo' => $motivo, 'documento' => '', 'quantidades' => $qtds]);
    }

    private function saldo($produto): float
    {
        return SaldoEstoque::noEstoque($this->estoqueId, $produto->id);
    }

    private function contas(NFeEntrada $e)
    {
        return ContaPagar::withoutGlobalScopes()->where('nfe_entrada_id', $e->id)->orderBy('parcela')->get();
    }

    public function test_devolucao_total_tira_do_estoque_e_cancela_as_contas(): void
    {
        $e = $this->entradaConcluida();
        $this->assertSame(20.0, $this->saldo($this->luva));
        $this->assertCount(2, $this->contas($e), 'as contas da entrada passam a ficar ligadas a ela');
        [$luva, $gaze] = $e->itens()->get()->all();

        $this->devolver($e, [$luva->id => '2', $gaze->id => '20'])
            ->assertRedirect(route('app.entradas-nfe.show', $e))->assertSessionHas('success');

        $this->assertSame(0.0, $this->saldo($this->luva));
        $this->assertSame(0.0, $this->saldo($this->gaze));
        $this->assertEquals(['cancelada', 'cancelada'], $this->contas($e)->pluck('status')->all());
        $this->assertSame('total', $e->fresh()->devolucao_situacao);

        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->where('nfe_entrada_id', $e->id)->firstOrFail();
        $this->assertEquals(1000.00, (float) $dev->valor_total);
        $this->assertEquals(1000.00, (float) $dev->valor_abatido);
        $this->assertEquals(0.00, (float) $dev->credito_fornecedor);
        // Saída de estoque (não "devolução", que no enum soma)
        $this->assertDatabaseHas('estoque_movimentacoes', ['origem_tipo' => NFeEntradaDevolucao::class, 'origem_id' => $dev->id, 'tipo' => 'saida', 'produto_id' => $this->luva->id]);
    }

    public function test_devolucao_parcial_abate_da_ultima_parcela(): void
    {
        $e = $this->entradaConcluida();
        [$luva] = $e->itens()->get()->all();

        // 1 caixa de luva = 10 UN, R$ 300
        $this->devolver($e, [$luva->id => '1'])->assertSessionHas('success');

        $this->assertSame(10.0, $this->saldo($this->luva));
        $c = $this->contas($e);
        $this->assertEquals([500.00, 200.00], $c->map(fn ($x) => (float) $x->valor)->all());
        $this->assertEquals(['pendente', 'pendente'], $c->pluck('status')->all());
        $this->assertStringContainsString('Devolução ao fornecedor nº 1', $c[1]->observacoes);
        $this->assertSame('parcial', $e->fresh()->devolucao_situacao);

        // A segunda devolução fecha a nota e leva o resto
        [$luva, $gaze] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1', $gaze->id => '20,000'])->assertSessionHas('success');
        $this->assertEquals(['cancelada', 'cancelada'], $this->contas($e)->pluck('status')->all());
        $this->assertSame('total', $e->fresh()->devolucao_situacao);
    }

    public function test_parcela_ja_paga_vira_credito_com_o_fornecedor(): void
    {
        $e = $this->entradaConcluida();
        $this->contas($e)->first()->update(['status' => 'paga', 'valor_pago' => 500, 'pago_em' => now()]);
        [$luva, $gaze] = $e->itens()->get()->all();

        $this->devolver($e, [$luva->id => '2', $gaze->id => '20'])->assertSessionHas('success');

        $c = $this->contas($e);
        $this->assertEquals(['paga', 'cancelada'], $c->pluck('status')->all());
        $this->assertEquals(500.00, (float) $c[0]->valor, 'a parcela paga não muda');
        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->where('nfe_entrada_id', $e->id)->firstOrFail();
        $this->assertEquals(500.00, (float) $dev->valor_abatido);
        $this->assertEquals(500.00, (float) $dev->credito_fornecedor);
        $this->assertStringContainsString('crédito com o fornecedor', session('success'));
    }

    public function test_nao_devolve_mais_do_que_a_nota_nem_mais_do_que_o_estoque(): void
    {
        $e = $this->entradaConcluida();
        [$luva, $gaze] = $e->itens()->get()->all();

        $this->devolver($e, [$luva->id => '3'])->assertSessionHas('error');
        $this->assertStringContainsString('só resta devolver 2,000', session('error'));

        // Vendeu 15 das 20 gazes: só 5 podem voltar
        SaldoEstoque::registrar($this->empresa->id, $this->unidade->id, $this->estoqueId, $this->gaze->id, 'saida', -15);
        $this->devolver($e, [$gaze->id => '20'])->assertSessionHas('error');
        $this->assertStringContainsString('o estoque tem 5,000', session('error'));

        $this->devolver($e, [$luva->id => '0'])->assertSessionHas('error');
        $this->assertStringContainsString('pelo menos um item', session('error'));

        $this->assertEquals(0, NFeEntradaDevolucao::withoutGlobalScopes()->count());
        $this->assertEquals([500.00, 500.00], $this->contas($e)->map(fn ($x) => (float) $x->valor)->all());
        $this->assertNull($e->fresh()->devolucao_situacao);
    }

    public function test_sem_permissao_do_financeiro_nao_mexe_em_conta_aberta(): void
    {
        $e = $this->entradaConcluida();
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        [$luva] = $e->itens()->get()->all();

        $this->devolver($e, [$luva->id => '1'], 'Defeito', $gerente)->assertSessionHas('error');
        $this->assertStringContainsString('financeiro', session('error'));
        $this->assertSame(20.0, $this->saldo($this->luva));
    }

    public function test_nota_sem_contas_so_mexe_no_estoque(): void
    {
        $e = $this->entradaConcluida(false);
        [$luva] = $e->itens()->get()->all();

        $this->devolver($e, [$luva->id => '1'])->assertSessionHas('success');
        $this->assertSame(10.0, $this->saldo($this->luva));
        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals(0.00, (float) $dev->credito_fornecedor);
    }

    public function test_tela_mostra_o_botao_e_o_historico(): void
    {
        $e = $this->entradaConcluida();
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.entradas-nfe.show', $e))
            ->assertOk()->assertSee('Devolver ao fornecedor')->assertSee('Registrar devolução');

        [$luva] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1']);
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.entradas-nfe.show', $e))
            ->assertOk()->assertSee('Nota devolvida em parte')->assertSee('Devolução nº 1')
            ->assertSee('Mercadoria com defeito, fornecedor manda outra');
    }

    private function estornar(NFeEntrada $e, NFeEntradaDevolucao $d, string $motivo = 'Fornecedor não aceitou a devolução', $user = null)
    {
        return $this->actingAsUser($user ?? $this->dono, $this->unidade)
            ->from(route('app.entradas-nfe.show', $e))
            ->post(route('app.entradas-nfe.devolucoes.estornar', [$e, $d->id]), ['estorno_motivo' => $motivo]);
    }

    public function test_estorno_volta_estoque_e_contas_como_estavam(): void
    {
        $e = $this->entradaConcluida();
        [$luva, $gaze] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1', $gaze->id => '20']); // R$ 300 + R$ 400 = 700
        $c = $this->contas($e);
        $this->assertEquals(['pendente', 'cancelada'], $c->pluck('status')->all());
        $this->assertEquals(300.00, (float) $c[0]->valor);
        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->firstOrFail();

        $this->estornar($e, $dev)->assertSessionHas('success');

        $this->assertSame(20.0, $this->saldo($this->luva));
        $this->assertSame(20.0, $this->saldo($this->gaze));
        $c = $this->contas($e);
        $this->assertEquals(['pendente', 'pendente'], $c->pluck('status')->all());
        $this->assertEquals([500.00, 500.00], $c->map(fn ($x) => (float) $x->valor)->all());
        $this->assertStringContainsString('Estorno da devolução nº 1', $c[1]->observacoes);
        $dev->refresh();
        $this->assertNotNull($dev->estornada_em);
        $this->assertSame($this->dono->id, $dev->estornada_por);
        $this->assertNull($e->fresh()->devolucao_situacao);
        $this->assertDatabaseHas('estoque_movimentacoes', ['origem_tipo' => NFeEntradaDevolucao::class, 'origem_id' => $dev->id, 'tipo' => 'entrada', 'produto_id' => $this->gaze->id]);

        // Estornada não conta: dá para devolver tudo de novo, e não estorna duas vezes
        $this->estornar($e, $dev)->assertSessionHas('error');
        $this->assertStringContainsString('já foi estornada', session('error'));
        $this->devolver($e, [$luva->id => '2', $gaze->id => '20'])->assertSessionHas('success');
        $this->assertSame('total', $e->fresh()->devolucao_situacao);
        $this->assertEquals(['cancelada', 'cancelada'], $this->contas($e)->pluck('status')->all());
    }

    public function test_estorno_parado_se_a_parcela_mudou_depois(): void
    {
        $e = $this->entradaConcluida();
        [$luva] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1']); // 2ª parcela 500 → 200
        $this->contas($e)[1]->update(['status' => 'paga', 'valor_pago' => 200, 'pago_em' => now()]);
        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->firstOrFail();

        $this->estornar($e, $dev)->assertSessionHas('error');
        $this->assertStringContainsString('parcela 2', session('error'));
        $this->assertSame(10.0, $this->saldo($this->luva), 'nada voltou ao estoque');
        $this->assertNull($dev->fresh()->estornada_em);
    }

    public function test_estorno_pede_motivo_e_financeiro_quando_mexeu_em_conta(): void
    {
        $e = $this->entradaConcluida();
        [$luva] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1']);
        $dev = NFeEntradaDevolucao::withoutGlobalScopes()->firstOrFail();

        $this->estornar($e, $dev, '')->assertSessionHasErrors('estorno_motivo');
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $this->estornar($e, $dev, 'Fornecedor recusou', $gerente)->assertSessionHas('error');
        $this->assertStringContainsString('financeiro', session('error'));
        $this->assertNull($dev->fresh()->estornada_em);
    }

    public function test_tela_mostra_estornar_e_depois_a_marca(): void
    {
        $e = $this->entradaConcluida();
        [$luva] = $e->itens()->get()->all();
        $this->devolver($e, [$luva->id => '1']);
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.entradas-nfe.show', $e))
            ->assertOk()->assertSee('Estornar esta devolução')->assertSee('Confirmar estorno');

        $this->estornar($e, NFeEntradaDevolucao::withoutGlobalScopes()->firstOrFail());
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.entradas-nfe.show', $e))
            ->assertOk()->assertSee('estornada')->assertSee('Fornecedor não aceitou a devolução')
            ->assertDontSee('Estornar esta devolução')->assertDontSee('Nota devolvida em parte');
    }
}
