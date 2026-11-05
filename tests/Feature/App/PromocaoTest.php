<?php

namespace Tests\Feature\App;

use App\Models\AgenteIaConfig;
use App\Models\ConfiguracaoLoja;
use App\Models\IntegracaoToken;
use App\Models\Produto;
use App\Models\ProdutoPreco;
use App\Models\Promocao;
use App\Models\Unidade;
use App\Services\PromocaoService;
use App\Services\TabelaPrecoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Promoções por período (05/11/2026). Decisões do Dennis: preço da promoção = à
 * vista, cartão com o acréscimo da loja por cima; sobreposição bloqueada ao criar;
 * vale no agente do WhatsApp; atacado fica com o preço dele.
 */
class PromocaoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private Unidade $loja2;
    private $dono;
    private Produto $produto;
    private Produto $outro;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');

        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->loja2 = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Loja 2', 'cnpj' => $this->unidade->cnpj,
            'cep' => '01001000', 'logradouro' => 'Rua', 'numero' => '1', 'bairro' => 'Centro',
            'cidade' => 'São Paulo', 'uf' => 'SP', 'telefone' => '11999999999', 'status' => 'ativa',
        ]);
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->produto = $this->createProduto($this->empresa, ['descricao' => 'BIQUINI ESTAMPADO', 'preco_venda' => 100]);
        $this->outro = $this->createProduto($this->empresa, ['descricao' => 'SAIDA DE PRAIA', 'preco_venda' => 50]);

        // Loja 1 na regra de sempre: débito +5%, crédito +10%
        ConfiguracaoLoja::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id,
            'percentual_debito' => 5, 'percentual_credito' => 10]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function promocao(array $itens, array $lojas, string $inicio = '2026-11-10', string $fim = '2026-11-20', string $nome = 'Black Friday'): Promocao
    {
        $p = Promocao::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'nome' => $nome, 'data_inicio' => $inicio, 'data_fim' => $fim,
        ]);
        $p->unidades()->sync(array_map(fn ($u) => $u->id, $lojas));
        foreach ($itens as [$produto, $tipo, $valor]) {
            $p->itens()->create(['produto_id' => $produto->id, 'tipo' => $tipo, 'valor' => $valor, 'preco_referencia' => $produto->preco_venda]);
        }

        return $p;
    }

    private function precos(Produto $p, Unidade $u): array
    {
        app()->forgetScopedInstances();

        return app(TabelaPrecoService::class)->precosDoProduto($p->fresh(), ConfiguracaoLoja::daUnidade($this->empresa->id, $u->id));
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Semana do Biquíni',
            'data_inicio' => '2026-11-12',
            'data_fim' => '2026-11-15',
            'unidades' => [$this->unidade->id],
            'itens' => [
                ['produto_id' => $this->produto->id, 'tipo' => 'percentual', 'valor' => 20],
                ['produto_id' => $this->outro->id, 'tipo' => 'preco', 'valor' => 39.9],
            ],
        ], $extra);
    }

    /* ---------------- preço ---------------- */

    public function test_percentual_vale_no_avista_e_cartao_leva_acrescimo_por_cima(): void
    {
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);

        $this->assertEquals(
            ['dinheiro_pix' => 80.0, 'debito' => 84.0, 'credito' => 88.0, 'atacado' => 100.0],
            $this->precos($this->produto, $this->unidade)
        );
    }

    public function test_preco_fixo_e_o_avista(): void
    {
        $this->promocao([[$this->produto, 'preco', 70]], [$this->unidade]);

        $p = $this->precos($this->produto, $this->unidade);
        $this->assertSame(70.0, $p['dinheiro_pix']);
        $this->assertSame(77.0, $p['credito']);
    }

    public function test_loja_na_regra_do_cartao(): void
    {
        // Cadastrado = cartão (100); à vista com 10% = 90. Promo 20% sobre o à vista = 72; cartão = 72 / 0,9 = 80
        ConfiguracaoLoja::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->loja2->id,
            'preco_base' => 'cartao', 'desconto_avista_percentual' => 10]);
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->loja2]);

        $p = $this->precos($this->produto, $this->loja2);
        $this->assertSame(72.0, $p['dinheiro_pix']);
        $this->assertSame(80.0, $p['debito']);
        $this->assertSame(80.0, $p['credito']);
    }

    public function test_cartao_fixo_do_produto_nao_vale_durante_a_promocao_e_nunca_sobe(): void
    {
        ProdutoPreco::create(['empresa_id' => $this->empresa->id, 'produto_id' => $this->produto->id, 'modalidade' => 'credito', 'valor' => 105]);
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);

        $p = $this->precos($this->produto, $this->unidade);
        $this->assertSame(80.0, $p['dinheiro_pix']);
        $this->assertSame(88.0, $p['credito']);

        // Promoção mal feita (preço acima do normal) não aumenta nada
        $this->promocao([[$this->outro, 'preco', 60]], [$this->unidade], nome: 'Errada');
        $this->assertSame(50.0, $this->precos($this->outro, $this->unidade)['dinheiro_pix']);
    }

    public function test_so_nas_lojas_e_nos_dias_da_promocao(): void
    {
        $promo = $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade], '2026-11-11', '2026-11-12');

        $this->assertSame(100.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix'], 'antes de começar');
        Carbon::setTestNow('2026-11-11 00:00:01');
        $this->assertSame(80.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix'], '1º dia');
        $this->assertSame(100.0, $this->precos($this->produto, $this->loja2)['dinheiro_pix'], 'outra loja');
        Carbon::setTestNow('2026-11-12 23:59:59');
        $this->assertSame(80.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix'], 'último dia, fim do dia');
        Carbon::setTestNow('2026-11-13 00:00:01');
        $this->assertSame(100.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix'], 'depois do fim');
        $this->assertSame('encerrada', $promo->fresh()->status());
    }

    public function test_encerrar_agora_volta_o_preco_no_mesmo_dia(): void
    {
        $promo = $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        $this->assertSame(80.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix']);

        $this->actingAsUser($this->dono, $this->unidade)
            ->post(route('app.promocoes.encerrar', $promo))->assertRedirect();

        $this->assertSame(100.0, $this->precos($this->produto, $this->unidade)['dinheiro_pix']);
        $this->assertSame('encerrada_antes', $promo->fresh()->status());
    }

    public function test_busca_do_pdv_traz_o_preco_e_o_aviso_da_promocao(): void
    {
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);

        $r = $this->actingAsUser($this->dono, $this->unidade)
            ->getJson(route('app.pdv.buscar-produto', 'BIQUINI'))->assertOk()->json();

        $this->assertSame('Black Friday', $r[0]['promocao']);
        $this->assertEquals(80, $r[0]['precos']['dinheiro_pix']);
        $this->assertEquals(80, $r[0]['preco_venda']);
    }

    /* ---------------- assistente ---------------- */

    public function test_cria_promocao_pelo_assistente(): void
    {
        $this->actingAsUser($this->dono, $this->unidade)
            ->post(route('app.promocoes.store'), $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $p = Promocao::withoutGlobalScopes()->with('itens', 'unidades')->sole();
        $this->assertSame('Semana do Biquíni', $p->nome);
        $this->assertSame([$this->unidade->id], $p->unidades->pluck('id')->all());
        $this->assertCount(2, $p->itens);
        $this->assertSame('agendada', $p->status());
        $this->assertEquals(100, $p->itens->firstWhere('produto_id', $this->produto->id)->preco_referencia);
    }

    public function test_bloqueia_produto_em_duas_promocoes_na_mesma_loja_e_periodo(): void
    {
        $this->promocao([[$this->produto, 'percentual', 10]], [$this->unidade, $this->loja2], '2026-11-14', '2026-11-30', 'Natal');

        $this->actingAsUser($this->dono, $this->unidade)
            ->post(route('app.promocoes.store'), $this->payload())
            ->assertSessionHasErrors('conflitos');
        $this->assertSame(1, Promocao::withoutGlobalScopes()->count());

        $erro = session('errors')->first('conflitos');
        $this->assertStringContainsString('BIQUINI ESTAMPADO', $erro);
        $this->assertStringContainsString('Natal', $erro);
    }

    public function test_sem_conflito_quando_outra_loja_outro_periodo_ou_encerrada_antes(): void
    {
        $this->promocao([[$this->produto, 'percentual', 10]], [$this->loja2], '2026-11-12', '2026-11-15', 'Outra loja');
        $this->promocao([[$this->produto, 'percentual', 10]], [$this->unidade], '2026-11-16', '2026-11-30', 'Depois');
        $cancelada = $this->promocao([[$this->produto, 'percentual', 10]], [$this->unidade], '2026-11-12', '2026-11-15', 'Cancelada');
        $cancelada->update(['encerrada_em' => '2026-11-10 09:00:00']);

        $this->actingAsUser($this->dono, $this->unidade)
            ->post(route('app.promocoes.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(4, Promocao::withoutGlobalScopes()->count());
    }

    public function test_validacoes_do_assistente(): void
    {
        $u = $this->actingAsUser($this->dono, $this->unidade);

        $u->post(route('app.promocoes.store'), $this->payload(['data_inicio' => '2026-11-09']))->assertSessionHasErrors('data_inicio');
        $u->post(route('app.promocoes.store'), $this->payload(['data_fim' => '2026-11-11']))->assertSessionHasErrors('data_fim');
        $u->post(route('app.promocoes.store'), $this->payload(['unidades' => []]))->assertSessionHasErrors('unidades');
        $u->post(route('app.promocoes.store'), $this->payload(['itens' => [['produto_id' => $this->produto->id, 'tipo' => 'preco', 'valor' => 100]]]))
            ->assertSessionHasErrors('itens.0.valor');
        $u->post(route('app.promocoes.store'), $this->payload(['itens' => [['produto_id' => $this->produto->id, 'tipo' => 'percentual', 'valor' => 100]]]))
            ->assertSessionHasErrors('itens.0.valor');

        // Produto de outra empresa
        [$outraEmpresa] = $this->createTenant('2');
        $alheio = $this->createProduto($outraEmpresa);
        $u->post(route('app.promocoes.store'), $this->payload(['itens' => [['produto_id' => $alheio->id, 'tipo' => 'percentual', 'valor' => 10]]]))
            ->assertSessionHasErrors('itens.0.produto_id');

        $this->assertSame(0, Promocao::withoutGlobalScopes()->count());
    }

    public function test_duplicar_traz_produtos_e_precos_e_a_tela_nova_sugere_a_ultima(): void
    {
        $origem = $this->promocao([[$this->produto, 'percentual', 15], [$this->outro, 'preco', 39.9]], [$this->unidade]);

        $tela = $this->actingAsUser($this->dono, $this->unidade)
            ->get(route('app.promocoes.create', ['duplicar' => $origem->id]))->assertOk();
        $tela->assertSee('Duplicar promoção');
        $tela->assertSee('"tipo":"percentual","valor":15', false);
        $tela->assertSee('"origem_id":' . $origem->id, false);

        $this->actingAsUser($this->dono, $this->unidade)
            ->get(route('app.promocoes.create'))->assertOk()->assertSee('Usar a lista da última promoção', false);

        $itens = $this->actingAsUser($this->dono, $this->unidade)
            ->getJson(route('app.promocoes.itens', $origem))->assertOk()->json('itens');
        $this->assertCount(2, $itens);

        // Duplicada e salva: guarda a origem
        $this->actingAsUser($this->dono, $this->unidade)->post(route('app.promocoes.store'), $this->payload([
            'origem_id' => $origem->id, 'data_inicio' => '2026-12-01', 'data_fim' => '2026-12-05',
        ]))->assertSessionHasNoErrors();
        $this->assertSame($origem->id, Promocao::withoutGlobalScopes()->latest('id')->first()->origem_id);
    }

    public function test_telas_abrem_e_quem_nao_edita_produto_nao_entra(): void
    {
        $promo = $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        $u = $this->actingAsUser($this->dono, $this->unidade);
        $u->get(route('app.promocoes.index'))->assertOk()->assertSee('Black Friday');
        $u->get(route('app.promocoes.show', $promo))->assertOk()->assertSee('R$ 80,00');
        $u->getJson(route('app.promocoes.buscar-produtos', ['q' => 'biquini']))->assertOk()->assertJsonPath('0.preco_referencia', 100);

        $caixa = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $this->actingAsUser($caixa, $this->unidade)->get(route('app.promocoes.index'))->assertForbidden();
        $this->actingAsUser($caixa, $this->unidade)->post(route('app.promocoes.store'), $this->payload())->assertForbidden();

        // Outra empresa não vê a promoção
        [$e2, $u2] = $this->createTenant('3');
        $dono2 = $this->createUser($e2, $u2, 'dono');
        $this->actingAsUser($dono2, $u2)->get(route('app.promocoes.show', $promo))->assertNotFound();
    }

    public function test_excluir_so_agendada(): void
    {
        $vigente = $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        $agendada = $this->promocao([[$this->outro, 'percentual', 20]], [$this->unidade], '2026-12-01', '2026-12-02', 'Dezembro');
        $u = $this->actingAsUser($this->dono, $this->unidade);

        $u->delete(route('app.promocoes.destroy', $vigente))->assertSessionHas('error');
        $u->delete(route('app.promocoes.destroy', $agendada))->assertRedirect(route('app.promocoes.index'));
        $this->assertSame([$vigente->id], Promocao::withoutGlobalScopes()->pluck('id')->all());
    }

    /* ---------------- cartão por produto + todos os produtos (05/11, STILO) ---------------- */

    private function promoComCartao(string $cartaoTipo, float $cartaoValor): Promocao
    {
        $p = $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        $p->itens()->first()->update(['cartao_tipo' => $cartaoTipo, 'cartao_valor' => $cartaoValor]);

        return $p;
    }

    public function test_cartao_em_reais_vale_para_debito_e_credito(): void
    {
        $this->promoComCartao('preco', 90);

        $this->assertEquals(
            ['dinheiro_pix' => 80.0, 'debito' => 90.0, 'credito' => 90.0, 'atacado' => 100.0],
            $this->precos($this->produto, $this->unidade)
        );
    }

    public function test_cartao_em_percentual_sobre_o_normal_de_cada_um(): void
    {
        $this->promoComCartao('percentual', 15); // débito normal 105, crédito normal 110

        $p = $this->precos($this->produto, $this->unidade);
        $this->assertSame(80.0, $p['dinheiro_pix']);
        $this->assertSame(89.25, $p['debito']);
        $this->assertSame(93.5, $p['credito']);
    }

    public function test_assistente_grava_o_cartao_e_valida(): void
    {
        $u = $this->actingAsUser($this->dono, $this->unidade);
        $itens = fn (array $extra) => ['itens' => [['produto_id' => $this->produto->id, 'tipo' => 'percentual', 'valor' => 20] + $extra]];

        // Cartão maior ou igual ao cartão normal (110)
        $u->post(route('app.promocoes.store'), $this->payload($itens(['cartao_tipo' => 'preco', 'cartao_valor' => 110])))
            ->assertSessionHasErrors('itens.0.cartao_valor');
        // % de 100
        $u->post(route('app.promocoes.store'), $this->payload($itens(['cartao_tipo' => 'percentual', 'cartao_valor' => 100])))
            ->assertSessionHasErrors('itens.0.cartao_valor');
        // Escolheu cartão e não pôs valor
        $u->post(route('app.promocoes.store'), $this->payload($itens(['cartao_tipo' => 'preco', 'cartao_valor' => ''])))
            ->assertSessionHasErrors('itens.0.cartao_valor');
        $this->assertSame(0, Promocao::withoutGlobalScopes()->count());

        // Certo: cartão R$ 95; e outro produto pela regra da loja (vazio)
        $u->post(route('app.promocoes.store'), $this->payload(['itens' => [
            ['produto_id' => $this->produto->id, 'tipo' => 'percentual', 'valor' => 20, 'cartao_tipo' => 'preco', 'cartao_valor' => 95],
            ['produto_id' => $this->outro->id, 'tipo' => 'preco', 'valor' => 39.9, 'cartao_tipo' => '', 'cartao_valor' => ''],
        ]]))->assertSessionHasNoErrors();

        $p = Promocao::withoutGlobalScopes()->with('itens')->sole();
        $i1 = $p->itens->firstWhere('produto_id', $this->produto->id);
        $i2 = $p->itens->firstWhere('produto_id', $this->outro->id);
        $this->assertSame('preco', $i1->cartao_tipo);
        $this->assertEquals(95, $i1->cartao_valor);
        $this->assertEquals(110, $i1->preco_cartao_referencia);
        $this->assertNull($i2->cartao_tipo);

        // Duplicar traz o cartão junto
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.promocoes.create', ['duplicar' => $p->id]))
            ->assertOk()->assertSee('"cartao_tipo":"preco","cartao_valor":95', false);
        // Detalhe mostra o cartão
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->actingAsUser($this->dono, $this->unidade)->get(route('app.promocoes.show', $p))
            ->assertOk()->assertSee('R$ 95,00')->assertSee('regra da loja');
    }

    public function test_todos_os_produtos_sem_buscar_e_sem_os_de_preco_zero(): void
    {
        $brinde = $this->createProduto($this->empresa, ['descricao' => 'Brinde Esmalte', 'preco_venda' => 0]);
        $this->createProduto($this->empresa, ['descricao' => 'Inativo', 'preco_venda' => 30, 'status' => 'inativo']);

        $r = $this->actingAsUser($this->dono, $this->unidade)->getJson(route('app.promocoes.todos-produtos'))->assertOk()->json();
        $ids = collect($r['itens'])->pluck('produto_id')->all();
        $this->assertEqualsCanonicalizing([$this->produto->id, $this->outro->id], $ids);
        $this->assertSame(1, $r['sem_preco']);
        $this->assertEquals(110, collect($r['itens'])->firstWhere('produto_id', $this->produto->id)['preco_cartao_referencia']);

        // E o servidor recusa produto sem preço, se vier
        $this->actingAsUser($this->dono, $this->unidade)->post(route('app.promocoes.store'), $this->payload(['itens' => [
            ['produto_id' => $brinde->id, 'tipo' => 'percentual', 'valor' => 10],
        ]]))->assertSessionHasErrors('itens.0.produto_id');
    }

    /* ---------------- agente WhatsApp ---------------- */

    public function test_agente_informa_e_vende_pelo_preco_da_promocao_da_loja(): void
    {
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        AgenteIaConfig::create(['empresa_id' => $this->empresa->id, 'ativo' => true]);
        [, $claro] = IntegracaoToken::gerar($this->empresa->id, 'agente', null);
        $h = ['Authorization' => 'Bearer ' . $claro];

        $r = $this->withHeaders($h)->getJson('/api/integracao/v1/produtos/' . $this->produto->id . '?unidade_id=' . $this->unidade->id)->assertOk();
        $r->assertJsonPath('dados.preco', 80);
        $r->assertJsonPath('dados.promocao.nome', 'Black Friday');
        $r->assertJsonPath('dados.promocao.preco_normal', 100);

        // Sem loja: preço de sempre
        $this->withHeaders($h)->getJson('/api/integracao/v1/produtos/' . $this->produto->id)
            ->assertOk()->assertJsonPath('dados.preco', 100)->assertJsonPath('dados.promocao', null);
        // Outra loja: preço de sempre
        $this->withHeaders($h)->getJson('/api/integracao/v1/produtos/' . $this->produto->id . '?unidade_id=' . $this->loja2->id)
            ->assertOk()->assertJsonPath('dados.preco', 100);

        // Pedido pelo WhatsApp na loja da promoção sai com o preço dela
        $pedido = $this->withHeaders($h)->postJson('/api/integracao/v1/pedidos', [
            'unidade_id' => $this->unidade->id,
            'cliente' => ['nome' => 'Maria Teste', 'telefone' => '11988887777'],
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2], ['produto_id' => $this->outro->id, 'quantidade' => 1]],
        ]);
        $pedido->assertSuccessful();
        $itens = \App\Models\PedidoItem::query()->orderBy('id')->get();
        $this->assertEquals([80, 50], $itens->pluck('preco_unitario')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals(160, (float) $itens->first()->total);
    }

    public function test_cache_por_requisicao_nao_vaza_entre_lojas(): void
    {
        $this->promocao([[$this->produto, 'percentual', 20]], [$this->unidade]);
        $s = app(PromocaoService::class);
        $this->assertNotNull($s->itemVigente($this->empresa->id, $this->unidade->id, $this->produto->id));
        $this->assertNull($s->itemVigente($this->empresa->id, $this->loja2->id, $this->produto->id));
        $this->assertNull($s->itemVigente($this->empresa->id, $this->unidade->id, $this->outro->id));
    }
}
