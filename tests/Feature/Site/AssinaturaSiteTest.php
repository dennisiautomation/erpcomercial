<?php

namespace Tests\Feature\Site;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\PlataformaFatura;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Plataforma\AssinaturaSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Assinatura pela própria empresa (20/11/2026): "Assinar agora" com PIX, boleto ou cartão,
 * 1ª mensalidade no caixa.ia365, link de cartão da InfinitePay, ativação e bloqueio.
 * O caixa.ia365 e a InfinitePay são simulados (Http::fake): nada sai para fora.
 */
class AssinaturaSiteTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;
    private User $dono;
    private array $caixaPedidos = [];
    private array $ipPedidos = [];
    private bool $caixaFora = false;
    private bool $ipPago = false;
    private int $ipValorPago = 59000;
    private ?string $caixaRecusa = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Mail::fake();
        config([
            'services.caixa_ia365.url' => 'http://caixa.teste',
            'services.caixa_ia365.token' => 'segredo-teste',
            'services.infinitepay.base_url' => 'http://infinitepay.teste',
            'app.url' => 'http://erp.teste',
        ]);

        $plano = Plano::create([
            'nome' => 'Basico', 'slug' => 'basico', 'preco_mensal' => 590, 'preco_anual' => 5600,
            'max_unidades' => 1, 'max_usuarios' => 5, 'max_produtos' => 100, 'max_notas_mes' => 100,
            'dias_trial' => 14, 'ativo' => true, 'ordem' => 1, 'pdv_habilitado' => true, 'fiscal_habilitado' => true,
        ]);
        [$this->empresa, $this->dono] = $this->novaEmpresa($plano, 'site', '11222333000181', 'dono@loja.com');

        Http::fake(function (HttpRequest $req) {
            $url = $req->url();
            $d = $req->data();
            if (str_starts_with($url, 'http://caixa.teste/api/erp/maquina/')) {
                $caminho = substr($url, strlen('http://caixa.teste/api/erp/maquina/'));
                $this->caixaPedidos[] = [$caminho, $d, $req->header('Authorization')[0] ?? null];
                if ($this->caixaFora) {
                    return Http::response(['message' => 'fora'], 500);
                }
                if ($this->caixaRecusa) {
                    return Http::response(['message' => $this->caixaRecusa], 422);
                }
                $estado = [
                    'empresa_id' => $d['empresa_id'], 'ref' => $d['ref'] ?? 7001, 'cliente_id' => 55, 'cliente_nome' => 'Loja Teste',
                    'situacao' => $caminho === 'baixa' ? 'paga' : 'aberta', 'valor' => 590.00,
                    'vencimento' => $d['vencimento'] ?? now()->addDays(14)->format('Y-m-d'), 'competencia' => now()->format('Y-m'),
                    'descricao' => $d['descricao'] ?? 'Mensalidade ERP Comercial — plano Basico', 'pago_em' => $caminho === 'baixa' ? now()->format('Y-m-d') : null,
                    'link' => 'https://caixa.ia365.com.br/f/abc', 'tem_nota' => false,
                    'pix_copia_cola' => ($d['forma'] ?? $d['tipo'] ?? 'pix') === 'pix' || $caminho === 'emitir' ? '000201PIXTESTE' : null,
                    'linha_digitavel' => ($d['forma'] ?? $d['tipo'] ?? '') === 'boleto' ? '07790001161234567890123456789012345678901234' : null,
                ];

                return Http::response(['ok' => true, 'estado' => $estado]);
            }
            if ($url === 'http://infinitepay.teste/links') {
                $this->ipPedidos[] = ['links', $d];

                return Http::response(['url' => 'https://checkout.infinitepay.io/ia365/' . $d['order_nsu']]);
            }
            if ($url === 'http://infinitepay.teste/payment_check') {
                $this->ipPedidos[] = ['payment_check', $d];

                return Http::response(['success' => true, 'paid' => $this->ipPago, 'amount' => 59000, 'paid_amount' => $this->ipPago ? $this->ipValorPago : 0]);
            }

            return Http::response(['message' => 'rede bloqueada no teste: ' . $url], 599);
        });
    }

    /** @return array{0: Empresa, 1: User} */
    private function novaEmpresa(Plano $plano, string $origem, string $cnpj, string $email, string $perfil = 'dono'): array
    {
        $empresa = new Empresa([
            'cnpj' => $cnpj, 'razao_social' => 'Loja Teste LTDA', 'nome_fantasia' => 'Loja Teste', 'regime_tributario' => 'simples_nacional',
            'email' => $email, 'telefone' => '(86) 99999-0000', 'cep' => '64000-000', 'logradouro' => 'Rua A', 'numero' => '10',
            'bairro' => 'Centro', 'cidade' => 'Teresina', 'uf' => 'PI', 'status' => 'ativo', 'plano' => $plano->slug, 'plano_id' => $plano->id,
            'em_trial' => true, 'trial_inicio' => now(), 'trial_fim' => now()->addDays(14),
        ]);
        $empresa->origem_cadastro = $origem;
        $empresa->save();
        $unidade = Unidade::withoutGlobalScopes()->create(['empresa_id' => $empresa->id, 'nome' => 'Matriz', 'cnpj' => $cnpj, 'status' => 'ativa',
            'cep' => '64000-000', 'logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'Teresina', 'uf' => 'PI', 'telefone' => '(86) 99999-0000']);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'perfil' => $perfil, 'status' => 'ativo', 'email' => $email]);
        $user->unidades()->sync([$unidade->id]);

        return [$empresa, $user];
    }

    private function como(User $user, ?Empresa $empresa = null): static
    {
        $empresa ??= $this->empresa;

        return $this->actingAs($user->fresh())->withSession(['unidade_id' => $user->unidades()->value('unidades.id'), 'empresa_id' => $empresa->id]);
    }

    private function faturaDoCaixa(string $situacao, int $ref = 7001, ?string $vencimento = null): void
    {
        $this->postJson('/api/integracao/v1/plataforma/caixa/fatura', [
            'empresa_id' => $this->empresa->id, 'ref' => $ref, 'situacao' => $situacao, 'valor' => 590, 'cliente_id' => 55, 'cliente_nome' => 'Loja Teste',
            'vencimento' => $vencimento ?? now()->addDays(14)->format('Y-m-d'), 'descricao' => 'Mensalidade ERP Comercial — plano Basico',
            'link' => 'https://caixa.ia365.com.br/f/abc', 'pix_copia_cola' => $situacao === 'aberta' ? '000201PIXTESTE' : null,
            'pago_em' => $situacao === 'paga' ? now()->format('Y-m-d') : null,
        ], ['Authorization' => 'Bearer segredo-teste'])->assertOk();
    }

    public function test_tela_de_assinar_so_para_quem_veio_do_site_e_cuida_das_faturas(): void
    {
        // em teste: a faixa chama para assinar e a tela mostra plano, valor e vencimento no fim do teste
        $this->como($this->dono)->get('/app/dashboard')->assertOk()->assertSee('data-teste-assinar', false);
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertOk()
            ->assertSee('R$ 590,00')->assertSee(now()->addDays(14)->format('d/m/Y'))->assertSee('PIX')->assertSee('Boleto')
            ->assertDontSee('Cartão de crédito'); // sem InfiniteTag da IA365 o cartão não aparece
        AssinaturaSite::definirHandle('$ia365');
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertSee('Cartão de crédito')->assertSee('não é débito automático');

        // vendedor não assina
        $vend = User::factory()->create(['empresa_id' => $this->empresa->id, 'perfil' => 'vendedor', 'status' => 'ativo']);
        $vend->unidades()->sync([$this->dono->unidades()->value('unidades.id')]);
        $this->como($vend)->get(route('app.assinatura.index'))->assertForbidden();
        $this->como($vend)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertForbidden();

        // empresa criada pelo admin: a tela não existe e nada muda nas faturas
        [$antiga, $donoAntigo] = $this->novaEmpresa(Plano::first(), 'admin', '22333444000181', 'dono@antiga.com');
        $this->como($donoAntigo, $antiga)->get(route('app.assinatura.index'))->assertNotFound();
        $this->como($donoAntigo, $antiga)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertNotFound();
        $this->como($donoAntigo, $antiga)->get('/app/dashboard')->assertOk()->assertDontSee('data-teste-assinar', false);
        $this->como($donoAntigo, $antiga)->get(route('app.faturas.index'))->assertOk()->assertDontSee('Assinar agora')->assertDontSee('data-fatura-cartao', false);
        $this->assertSame([], $this->caixaPedidos);
    }

    public function test_assinar_por_pix_pede_a_primeira_mensalidade_ao_caixa_e_liga_o_bloqueio(): void
    {
        AssinaturaSite::definirTolerancia(7);
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])
            ->assertRedirect(route('app.faturas.index'))->assertSessionHas('success');

        $this->assertCount(1, $this->caixaPedidos);
        [$caminho, $d, $auth] = $this->caixaPedidos[0];
        $this->assertSame('assinatura', $caminho);
        $this->assertSame('Bearer segredo-teste', $auth);
        $this->assertSame($this->empresa->id, $d['empresa_id']);
        $this->assertSame('11222333000181', $d['cnpj']);
        $this->assertEquals(590, $d['valor']);
        $this->assertSame('pix', $d['forma']);
        $this->assertSame(now()->addDays(14)->format('Y-m-d'), $d['vencimento']); // vence no fim do teste
        $this->assertSame('Teresina', $d['cidade']);
        $this->assertSame('Mensalidade ERP Comercial — plano Basico', $d['descricao']);

        $f = PlataformaFatura::firstOrFail();
        $this->assertSame([7001, 'caixa', 'pendente', '000201PIXTESTE'], [(int) $f->caixa_ref, $f->origem, $f->status, $f->pix_copia_cola]);
        $e = $this->empresa->fresh();
        $this->assertSame('pix', $e->assinatura_forma);
        $this->assertNotNull($e->assinatura_solicitada_em);
        $this->assertNull($e->assinatura_ativada_em);
        $this->assertTrue((bool) $e->cobranca_bloqueio_automatico);
        $this->assertSame(7, (int) $e->cobranca_tolerancia_dias);
        $this->assertSame(55, (int) $e->caixa_cliente_id);
        $this->assertTrue((bool) $e->em_trial); // segue em teste até pagar

        // a tela de faturas mostra o PIX e oferece o boleto; a de assinar some; a faixa do teste dá lugar à da fatura
        $this->como($this->dono)->get(route('app.faturas.index'))->assertOk()->assertSee('data-fatura-pix', false)
            ->assertSee('data-fatura-gerar-boleto', false)->assertDontSee('data-fatura-gerar-pix', false)->assertDontSee('data-fatura-cartao', false);
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertRedirect(route('app.faturas.index'));
        $this->como($this->dono)->get('/app/dashboard')->assertOk()->assertDontSee('data-teste-assinar', false)->assertSee('data-fatura-faixa', false);

        // segundo pedido (toque repetido, outra aba) não pede outra cobrança
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'boleto'])->assertRedirect(route('app.faturas.index'));
        $this->assertCount(1, $this->caixaPedidos);
        $this->assertSame(1, PlataformaFatura::count());
    }

    public function test_assinar_por_boleto_e_gerar_o_que_falta_na_fatura(): void
    {
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'boleto'])->assertRedirect(route('app.faturas.index'));
        $this->assertSame('boleto', $this->caixaPedidos[0][1]['forma']);
        $f = PlataformaFatura::firstOrFail();
        $this->assertNotNull($f->linha_digitavel);
        $this->assertNull($f->pix_copia_cola);
        $this->como($this->dono)->get(route('app.faturas.index'))->assertSee('data-fatura-gerar-pix', false)->assertDontSee('data-fatura-gerar-boleto', false);

        $this->como($this->dono)->post(route('app.faturas.emitir', $f->id), ['tipo' => 'pix'])->assertSessionHas('success');
        $this->assertSame(['emitir', ['empresa_id' => $this->empresa->id, 'ref' => 7001, 'tipo' => 'pix']], [$this->caixaPedidos[1][0], $this->caixaPedidos[1][1]]);
        $this->assertSame('000201PIXTESTE', $f->fresh()->pix_copia_cola);

        // o caixa recusa (cadastro sem endereço, banco fora): a mensagem dele chega à tela e nada muda
        // o caixa recusa por cadastro incompleto: a empresa lê o que falta, em palavras dela
        $this->caixaRecusa = 'o Inter exige estes dados do pagador e o cadastro de Loja Teste está sem: CEP, cidade';
        $this->como($this->dono)->post(route('app.faturas.emitir', $f->id), ['tipo' => 'boleto'])
            ->assertSessionHas('error', 'Para gerar o boleto falta completar o cadastro da empresa: CEP, cidade. Complete em Gestão → Empresa ou escolha o PIX.');
        // recusa por motivo interno (banco fora, configuração): texto interno NÃO chega à tela do cliente
        $this->caixaRecusa = 'o banco ainda não está configurado (falta clientId) — Ajustes → Banco (Inter)';
        $this->como($this->dono)->post(route('app.faturas.emitir', $f->id), ['tipo' => 'boleto'])
            ->assertSessionHas('error', 'Não foi possível gerar a cobrança agora. Tente de novo em alguns minutos ou fale com a IA365.');
        $this->como($this->dono)->post(route('app.faturas.emitir', $f->id), ['tipo' => 'cheque'])->assertSessionHasErrors('tipo');

        // fatura de outra empresa não é alcançada
        [$outra] = $this->novaEmpresa(Plano::first(), 'site', '33444555000181', 'dono@outra.com');
        $alheia = PlataformaFatura::create(['empresa_id' => $outra->id, 'origem' => 'caixa', 'caixa_ref' => 9, 'competencia' => '2026-11', 'valor' => 590, 'vencimento' => now(), 'status' => 'pendente']);
        $this->como($this->dono)->post(route('app.faturas.emitir', $alheia->id), ['tipo' => 'pix'])->assertNotFound();
        $this->como($this->dono)->post(route('app.faturas.cartao', $alheia->id))->assertNotFound();
    }

    public function test_caixa_desligado_ou_recusando_nao_marca_a_empresa(): void
    {
        config(['services.caixa_ia365.url' => '']);
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertOk()->assertSee('data-assinatura-desligada', false);
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertSessionHasErrors('forma');
        $this->assertSame([], $this->caixaPedidos);

        config(['services.caixa_ia365.url' => 'http://caixa.teste']);
        $this->caixaRecusa = 'o banco ainda não está configurado';
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertSessionHasErrors(['forma' => 'Não foi possível gerar a cobrança agora. Tente de novo em alguns minutos ou fale com a IA365.']);
        $this->caixaRecusa = null;
        $this->caixaFora = true;
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertSessionHasErrors('forma');
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'dinheiro'])->assertSessionHasErrors('forma');

        $e = $this->empresa->fresh();
        $this->assertNull($e->assinatura_solicitada_em);
        $this->assertNull($e->assinatura_forma);
        $this->assertFalse((bool) $e->cobranca_bloqueio_automatico);
        $this->assertSame(0, PlataformaFatura::count());
    }

    public function test_primeira_mensalidade_paga_no_banco_vira_assinatura(): void
    {
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix']);
        $this->faturaDoCaixa('paga');

        $e = $this->empresa->fresh();
        $this->assertNotNull($e->assinatura_ativada_em);
        $this->assertFalse((bool) $e->em_trial);
        $this->assertSame('paga', PlataformaFatura::firstOrFail()->status);

        // passou a data do teste: segue entrando, sem faixa de teste e sem tela de assinar
        $this->travel(30)->days();
        $this->assertTrue($e->fresh()->isAssinaturaAtiva());
        $this->como($this->dono)->get('/app/produtos')->assertOk();
        $this->como($this->dono)->get('/app/dashboard')->assertOk()->assertDontSee('data-teste-assinar', false);
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertRedirect(route('app.faturas.index'));
    }

    public function test_teste_vencido_sem_assinar_bloqueia_mas_deixa_assinar_e_pagar(): void
    {
        $this->empresa->forceFill(['trial_fim' => now()->subDay()])->save();
        $this->como($this->dono)->get('/app/produtos')->assertRedirect(route('app.plano-expirado'));
        $this->como($this->dono)->get(route('app.plano-expirado'))->assertOk()->assertSee('data-expirado-assinar-botao', false)->assertDontSee('wa.me');
        $this->como($this->dono)->get(route('app.assinatura.index'))->assertOk()->assertSee('vence')->assertSee('hoje');

        // assina com o teste vencido: a mensalidade vence hoje e o acesso volta dentro da tolerância
        AssinaturaSite::definirTolerancia(3);
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix'])->assertRedirect(route('app.faturas.index'));
        $this->assertSame(now()->format('Y-m-d'), $this->caixaPedidos[0][1]['vencimento']);
        $this->como($this->dono)->get(route('app.faturas.index'))->assertOk();
        $this->como($this->dono)->get('/app/produtos')->assertOk();

        // tolerância estourada sem pagar: bloqueia de novo, com a fatura para pagar
        $this->travel(4)->days();
        $this->como($this->dono)->get('/app/produtos')->assertRedirect(route('app.plano-expirado'));
        $this->como($this->dono)->get(route('app.plano-expirado'))->assertOk()->assertSee('data-expirado-pagar', false);
        $this->como($this->dono)->get(route('app.faturas.index'))->assertOk()->assertSee('data-fatura-pix', false);

        // pagou: entra na hora
        $this->faturaDoCaixa('paga', 7001, now()->subDays(4)->format('Y-m-d'));
        $this->como($this->dono)->get('/app/produtos')->assertOk();
    }

    public function test_mensalidade_seguinte_em_atraso_suspende_depois_da_tolerancia_e_pagar_libera(): void
    {
        AssinaturaSite::definirTolerancia(5);
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'pix']);
        $this->faturaDoCaixa('paga');

        // 2ª mensalidade (vinda da recorrência do caixa), vencendo em 10 dias
        $venc = now()->addDays(10)->format('Y-m-d');
        $this->faturaDoCaixa('aberta', 7002, $venc);

        $this->travel(13)->days(); // 3 dias de atraso: dentro da tolerância
        $this->artisan('plataforma:processar-cobrancas')->assertSuccessful();
        $this->assertFalse($this->empresa->fresh()->estaSuspensa());
        $this->como($this->dono)->get('/app/produtos')->assertOk();

        $this->travel(3)->days(); // 6 dias de atraso: suspende
        $this->artisan('plataforma:processar-cobrancas')->assertSuccessful();
        $this->assertTrue($this->empresa->fresh()->estaSuspensa());
        $this->como($this->dono)->get('/app/produtos')->assertRedirect(route('acesso-suspenso'));
        $this->como($this->dono)->get(route('acesso-suspenso'))->assertOk()->assertSee('data-suspenso-faturas', false);
        $this->como($this->dono)->get(route('app.faturas.index'))->assertOk()->assertSee('data-fatura-gerar-boleto', false);

        $this->faturaDoCaixa('paga', 7002, $venc);
        $this->assertFalse($this->empresa->fresh()->estaSuspensa());
        $this->como($this->dono)->get('/app/produtos')->assertOk();
    }

    public function test_cartao_gera_link_da_infinitepay_e_so_baixa_com_a_confirmacao_dela(): void
    {
        AssinaturaSite::definirHandle('$ia365');
        $resposta = $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'cartao']);
        $f = PlataformaFatura::firstOrFail();
        $resposta->assertRedirect('https://checkout.infinitepay.io/ia365/' . $f->cartao_nsu);

        $this->assertSame('pix', $this->caixaPedidos[0][1]['forma']); // cartão nasce com PIX de reserva
        $this->assertSame('cartao', $this->empresa->fresh()->assinatura_forma);
        [$onde, $d] = $this->ipPedidos[0];
        $this->assertSame('links', $onde);
        $this->assertSame('ia365', $d['handle']);
        $this->assertSame([['quantity' => 1, 'price' => 59000, 'description' => 'Mensalidade ERP Comercial — plano Basico']], $d['items']);
        $this->assertStringStartsWith("fat{$f->id}v59000-", $d['order_nsu']);
        $this->assertSame('http://erp.teste/api/integracao/v1/webhooks/infinitepay-plataforma/' . AssinaturaSite::webhookToken(), $d['webhook_url']);
        $this->assertStringEndsWith('/app/faturas/cartao/retorno', $d['redirect_url']);

        // segundo toque em "Pagar com cartão": o MESMO link, sem criar outro
        $this->como($this->dono)->post(route('app.faturas.cartao', $f->id))->assertRedirect($f->cartao_link);
        $this->assertCount(1, array_filter($this->ipPedidos, fn ($p) => $p[0] === 'links'));

        $webhook = '/api/integracao/v1/webhooks/infinitepay-plataforma/';
        // aviso com token errado, de link desconhecido ou sem pagamento confirmado: nada baixa
        $this->postJson($webhook . 'token-errado', ['order_nsu' => $f->cartao_nsu])->assertOk()->assertJson(['ignored' => 'nao_autorizado']);
        $this->postJson($webhook . AssinaturaSite::webhookToken(), ['order_nsu' => 'fat999v1-xxxx'])->assertOk()->assertJson(['ignored' => 'fatura_desconhecida']);
        $this->postJson($webhook . AssinaturaSite::webhookToken(), ['order_nsu' => $f->cartao_nsu, 'transaction_nsu' => 'T1'])->assertOk()->assertJson(['pago' => false]);
        $this->assertSame('pendente', $f->fresh()->status);

        // pago com valor menor que a fatura: não baixa
        $this->ipPago = true;
        $this->ipValorPago = 100;
        $this->postJson($webhook . AssinaturaSite::webhookToken(), ['order_nsu' => $f->cartao_nsu])->assertJson(['pago' => false]);
        $this->assertSame('pendente', $f->fresh()->status);

        // pago de verdade, com o caixa fora do ar: baixa aqui, ativa a assinatura e deixa a baixa do caixa na fila
        $this->ipValorPago = 59000;
        $this->caixaFora = true;
        $this->postJson($webhook . AssinaturaSite::webhookToken(), ['order_nsu' => $f->cartao_nsu, 'transaction_nsu' => 'T1', 'invoice_slug' => 'S1'])->assertJson(['pago' => true]);
        $f->refresh();
        $this->assertSame(['paga', 'cartão (InfinitePay)', true, 'T1', 'S1'], [$f->status, $f->forma_pagamento, $f->caixa_baixa_pendente, $f->cartao_transaction_nsu, $f->cartao_slug]);
        $this->assertNotNull($this->empresa->fresh()->assinatura_ativada_em);

        // o caixa ainda diz "aberta" (não recebeu a baixa): a fatura NÃO reabre
        $this->faturaDoCaixa('aberta');
        $this->assertSame('paga', $f->fresh()->status);

        // a rotina entrega a baixa quando o caixa volta; aviso repetido não faz nada
        $this->caixaFora = false;
        $this->artisan('plataforma:conferir-assinaturas')->assertSuccessful();
        $this->assertFalse($f->fresh()->caixa_baixa_pendente);
        $baixa = collect($this->caixaPedidos)->last();
        $this->assertSame('baixa', $baixa[0]);
        $this->assertSame([$this->empresa->id, 7001, $f->cartao_nsu], [$baixa[1]['empresa_id'], $baixa[1]['ref'], $baixa[1]['nsu']]);
        $this->postJson($webhook . AssinaturaSite::webhookToken(), ['order_nsu' => $f->cartao_nsu])->assertJson(['ignored' => 'ja_processado']);
    }

    public function test_volta_do_cartao_e_rotina_confirmam_sem_o_aviso(): void
    {
        AssinaturaSite::definirHandle('ia365');
        $this->como($this->dono)->post(route('app.assinatura.assinar'), ['forma' => 'cartao']);
        $f = PlataformaFatura::firstOrFail();

        // voltou do checkout sem pagamento confirmado
        $this->como($this->dono)->get(route('app.faturas.cartao.retorno', ['order_nsu' => $f->cartao_nsu]))->assertRedirect(route('app.faturas.index'))->assertSessionHas('warning');
        $this->assertSame('pendente', $f->fresh()->status);

        // a rotina pergunta à InfinitePay e baixa
        $this->ipPago = true;
        $this->artisan('plataforma:conferir-assinaturas')->assertSuccessful();
        $this->assertSame('paga', $f->fresh()->status);
        $this->assertFalse($f->fresh()->caixa_baixa_pendente);

        // volta com o pagamento já confirmado; referência de outra empresa não acha nada
        $this->como($this->dono)->get(route('app.faturas.cartao.retorno', ['order_nsu' => $f->cartao_nsu]))->assertSessionHas('success');
        $this->como($this->dono)->get(route('app.faturas.cartao.retorno', ['order_nsu' => 'nao-existe']))->assertRedirect(route('app.faturas.index'));
    }

    public function test_admin_grava_tolerancia_e_infinitetag(): void
    {
        $rota = route('admin.planos.contratacao-site.cobranca');
        $this->post($rota, ['tolerancia' => 3])->assertRedirect(route('login'));
        $this->como($this->dono)->post($rota, ['tolerancia' => 3])->assertForbidden();
        $this->assertSame(AssinaturaSite::TOLERANCIA_PADRAO, AssinaturaSite::toleranciaDias());

        $admin = User::factory()->create(['is_admin' => true, 'empresa_id' => null]);
        $this->actingAs($admin)->get(route('admin.planos.index'))->assertOk()->assertSee('data-quadro-cobranca-assinatura', false);
        $this->actingAs($admin)->post($rota, ['tolerancia' => 10, 'handle' => ' $ia365-pag '])->assertSessionHas('success');
        $this->assertSame(10, AssinaturaSite::toleranciaDias());
        $this->assertSame('ia365-pag', AssinaturaSite::handle());
        $this->assertTrue(AssinaturaSite::cartaoDisponivel());

        $this->actingAs($admin)->post($rota, ['tolerancia' => 99])->assertSessionHasErrors('tolerancia');
        $this->actingAs($admin)->post($rota, ['tolerancia' => 10, 'handle' => 'tag com/barra'])->assertSessionHasErrors('handle');
        $this->actingAs($admin)->post($rota, ['tolerancia' => 0, 'handle' => ''])->assertSessionHas('success');
        $this->assertSame(0, AssinaturaSite::toleranciaDias());
        $this->assertFalse(AssinaturaSite::cartaoDisponivel());
    }
}
