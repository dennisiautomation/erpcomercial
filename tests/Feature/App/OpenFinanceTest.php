<?php

namespace Tests\Feature\App;

use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\LancamentoBancario;
use App\Models\Plano;
use App\Services\OpenFinance\ConciliadorSugestoes;
use App\Services\OpenFinance\SincronizadorExtrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Open Finance (17/11/2026): liberação por plano / contas extras / não mostrar; conta bancária
 * com CPF/CNPJ do dono; extrato vindo da Tecnospeed (respostas no formato da documentação);
 * cruzamento com contas a receber e a pagar; confirmar, confirmar os certos, desfazer.
 */
class OpenFinanceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private const BASE = 'https://tecnospeed.teste';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.tecnospeed.base_url' => self::BASE,
            'services.tecnospeed.client_id' => 'op_teste',
            'services.tecnospeed.client_secret' => 'segredo',
        ]);
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->financeiro = $this->createUser($this->empresa, $this->unidade, 'financeiro');
    }

    /* ---------------- ajudantes ---------------- */

    private function liberar(int $contasDoPlano = 1, int $extras = 0): void
    {
        $plano = Plano::create([
            'nome' => 'Com banco', 'slug' => 'com-banco-' . uniqid(), 'preco_mensal' => 100, 'preco_anual' => 1000,
            'max_unidades' => 5, 'max_usuarios' => 10, 'max_produtos' => 1000, 'max_notas_mes' => 100, 'dias_trial' => 0,
            'ativo' => true, 'ordem' => 1,
            'open_finance_habilitado' => $contasDoPlano > 0, 'open_finance_contas' => $contasDoPlano,
        ]);
        $this->empresa->update(['plano_id' => $plano->id, 'open_finance_contas_extras' => $extras]);
        $this->empresa->refresh();
    }

    private function eu()
    {
        return $this->actingAsUser($this->financeiro, $this->unidade);
    }

    private function fakeTecnospeed(array $extra = []): void
    {
        Http::fake($extra + [
            self::BASE . '/oauth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 900]),
            self::BASE . '/api/v1/account' => fn ($req) => $req->method() === 'DELETE'
                ? Http::response(['accounts' => []])
                : Http::response(['accounts' => [[
                    'bankCode' => '341', 'accountHash' => 'HASH' . substr(md5(json_encode($req->data())), 0, 6),
                    'statementActived' => true, 'openfinanceLink' => self::BASE . '/gui/abc',
                    'statusOpenfinance' => 'PENDENTE_ATIVACAO',
                ]]], 201),
            self::BASE . '/api/v1/account/*/openfinance/revoke' => Http::response(['message' => 'Conta revogada com sucesso.']),
        ]);
    }

    private function conta(array $extra = []): ContaBancaria
    {
        return ContaBancaria::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $this->empresa->id, 'apelido' => 'Itaú loja', 'banco_codigo' => '341', 'banco_nome' => 'Itaú',
            'agencia' => '1234', 'conta' => '56789', 'conta_digito' => '0', 'titular_documento' => '12345678000195',
            'account_hash' => 'HASH1', 'link_conexao' => self::BASE . '/gui/abc', 'situacao' => 'conectada', 'conectada_em' => now(),
        ]);
    }

    private function transacao(string $id, string $tipo, float $valor, string $data, array $extra = []): array
    {
        return $extra + [
            'transactionId' => $id, 'transactionType' => $tipo, 'amount' => number_format($valor, 2, '.', ''),
            'date' => $data, 'description' => 'PIX RECEBIDO', 'operationType' => 'PIX', 'status' => 'POSTED',
            'paymentName' => null, 'paymentDigitableLine' => null,
            'participantPayer' => ['name' => 'Fulano Pagador', 'documentNumber' => ['type' => 'CPF', 'value' => '11122233344']],
            'participantReceiver' => ['name' => 'Loja Recebedora', 'documentNumber' => ['type' => 'CNPJ', 'value' => '12345678000195']],
        ];
    }

    private function extrato(array $creditos = [], array $debitos = [], string $status = 'SUCCESS'): array
    {
        return [
            'statement' => ['uniqueId' => 'PROT1', 'status' => $status, 'reason' => 'Extrato realizado com sucesso'],
            'transaction' => ['credit' => $creditos, 'debit' => $debitos],
            'transactionDuplicated' => ['credit' => [], 'debit' => []],
        ];
    }

    private function receber(float $valor, string $vencimento, array $extra = []): ContaReceber
    {
        return ContaReceber::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'descricao' => 'Venda a prazo',
            'valor' => $valor, 'valor_pago' => 0, 'vencimento' => $vencimento, 'forma_pagamento' => 'boleto',
            'parcela' => 1, 'total_parcelas' => 1, 'status' => 'pendente',
        ]);
    }

    private function pagar(float $valor, string $vencimento, array $extra = []): ContaPagar
    {
        return ContaPagar::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'descricao' => 'Compra de mercadoria',
            'valor' => $valor, 'valor_pago' => 0, 'vencimento' => $vencimento, 'parcela' => 1, 'total_parcelas' => 1, 'status' => 'pendente',
        ]);
    }

    private function lancamento(ContaBancaria $conta, string $tipo, float $valor, string $data, array $extra = []): LancamentoBancario
    {
        static $n = 0;

        return LancamentoBancario::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $conta->empresa_id, 'conta_bancaria_id' => $conta->id, 'transaction_id' => 'T' . (++$n),
            'data' => $data, 'tipo' => $tipo, 'valor' => $valor, 'descricao' => 'PIX', 'status' => 'pendente',
        ]);
    }

    /* ---------------- liberação ---------------- */

    public function test_sem_liberacao_mostra_convite_e_nao_deixa_cadastrar(): void
    {
        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('não faz parte do seu plano');
        $this->eu()->post(route('app.bancos.contas.store'), [])->assertForbidden();
        $this->eu()->get(route('app.dashboard'))->assertSee(route('app.bancos.index'));
    }

    public function test_nao_mostrar_esconde_do_menu_e_a_tela_some(): void
    {
        $this->empresa->update(['open_finance_ocultar' => true]);

        $this->eu()->get(route('app.bancos.index'))->assertNotFound();
        $this->eu()->get(route('app.dashboard'))->assertDontSee(route('app.bancos.index'));
    }

    public function test_nao_mostrar_nao_esconde_de_quem_tem_a_funcao(): void
    {
        $this->liberar(1);
        $this->empresa->update(['open_finance_ocultar' => true]);

        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('0 de 1 conta usada');
    }

    public function test_limite_soma_plano_e_extras_e_plano_desligado_nao_conta(): void
    {
        $this->liberar(2, 3);
        $this->assertSame(5, $this->empresa->openFinanceLimiteContas());

        $this->empresa->planoAssinatura->update(['open_finance_habilitado' => false]);
        $this->assertSame(3, $this->empresa->fresh()->openFinanceLimiteContas());

        $this->empresa->update(['open_finance_contas_extras' => 0]);
        $this->assertFalse($this->empresa->fresh()->openFinanceLiberado());
    }

    public function test_venda_avulsa_libera_empresa_sem_plano_com_a_funcao(): void
    {
        $this->empresa->update(['open_finance_contas_extras' => 1]);

        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('Ligar uma conta');
    }

    /* ---------------- conta bancária ---------------- */

    public function test_cadastra_conta_com_documento_do_dono_e_guarda_o_link_do_banco(): void
    {
        $this->liberar(1);
        $this->fakeTecnospeed();

        $this->eu()->post(route('app.bancos.contas.store'), [
            'apelido' => 'Itaú loja', 'banco_codigo' => '341', 'agencia' => '1234', 'conta' => '56789', 'conta_digito' => '0',
            'titular_documento' => '12.345.678/0001-95', 'titular_nome' => 'Empresa Teste',
        ])->assertRedirect(route('app.bancos.index'));

        $conta = ContaBancaria::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('12345678000195', $conta->titular_documento);
        $this->assertSame('aguardando', $conta->situacao);
        $this->assertStringStartsWith('HASH', $conta->account_hash);
        $this->assertSame(self::BASE . '/gui/abc', $conta->link_conexao);
        $this->assertSame($this->empresa->id, $conta->empresa_id);

        Http::assertSent(fn ($r) => $r->url() === self::BASE . '/api/v1/account' && $r->method() === 'POST'
            && $r->data()[0]['bankCode'] === '341' && $r->data()[0]['statementActived'] === true
            && $r->hasHeader('Authorization', 'Bearer tok'));

        $this->eu()->get(route('app.bancos.index'))->assertSee('Conectar ao banco')->assertSee('12.345.678/0001-95');
        // a lista de bancos com busca vem na tela, por número e nome
        $this->eu()->get(route('app.bancos.index'))->assertSee('data-codigo="341"', false)->assertSee('Nubank')->assertSee('Open Finance');
    }

    public function test_documento_do_dono_invalido_e_recusado(): void
    {
        $this->liberar(1);
        $this->fakeTecnospeed();

        $this->eu()->post(route('app.bancos.contas.store'), [
            'apelido' => 'X', 'banco_codigo' => '341', 'agencia' => '1', 'conta' => '2', 'titular_documento' => '123',
        ])->assertSessionHasErrors('titular_documento');

        // sem banco escolhido também não passa
        $this->eu()->post(route('app.bancos.contas.store'), [
            'apelido' => 'X', 'banco_codigo' => '', 'agencia' => '1', 'conta' => '2', 'titular_documento' => '12345678000195',
        ])->assertSessionHasErrors('banco_codigo');

        $this->assertSame(0, ContaBancaria::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    public function test_limite_de_contas_trava_e_desconectar_libera_a_vaga(): void
    {
        $this->liberar(1);
        $this->fakeTecnospeed();
        $dados = ['apelido' => 'A', 'banco_codigo' => '341', 'agencia' => '1', 'conta' => '111', 'titular_documento' => '12345678000195'];

        $this->eu()->post(route('app.bancos.contas.store'), $dados);
        $this->eu()->post(route('app.bancos.contas.store'), ['conta' => '222', 'apelido' => 'B'] + $dados)
            ->assertSessionHas('error');
        $this->assertSame(1, ContaBancaria::withoutGlobalScopes()->count());

        $conta = ContaBancaria::withoutGlobalScopes()->first();
        $hash = $conta->account_hash;
        $this->eu()->post(route('app.bancos.contas.desconectar', $conta))->assertSessionHas('success');

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->data() === ['accountHash' => [$hash]]);
        $this->assertSame('desconectada', $conta->fresh()->situacao);
        $this->assertNull($conta->fresh()->account_hash);
        $this->assertSame(0, $this->empresa->openFinanceContasUsadas());

        $this->eu()->post(route('app.bancos.contas.store'), ['conta' => '222', 'apelido' => 'B'] + $dados);
        $this->assertSame(2, ContaBancaria::withoutGlobalScopes()->count());
    }

    public function test_conta_de_outra_empresa_nao_e_alcancada(): void
    {
        $this->liberar(1);
        [$outra] = $this->createTenant('2');
        $alheia = $this->conta(['empresa_id' => $outra->id, 'account_hash' => 'ALHEIA']);
        $lanc = $this->lancamento($alheia, 'credito', 10, '2026-11-10');
        $this->fakeTecnospeed();

        $this->eu()->post(route('app.bancos.contas.desconectar', $alheia))->assertNotFound();
        $this->eu()->post(route('app.bancos.lancamentos.ignorar', $lanc))->assertNotFound();
        $this->assertSame('conectada', $alheia->fresh()->situacao);
    }

    /* ---------------- extrato ---------------- */

    public function test_conta_aguardando_vira_conectada_quando_o_dono_autoriza(): void
    {
        $conta = $this->conta(['situacao' => 'aguardando', 'conectada_em' => null]);
        $this->fakeTecnospeed([self::BASE . '/api/v1/account/HASH1' => Http::sequence()
            ->push(['accountHash' => 'HASH1', 'statusOpenfinance' => 'PENDENTE_ATIVACAO', 'openfinanceId' => ''])
            ->push(['accountHash' => 'HASH1', 'statusOpenfinance' => 'ATIVO', 'openfinanceId' => 'abc-123'])]);

        $s = app(SincronizadorExtrato::class);
        $s->sincronizar($conta);
        $this->assertSame('aguardando', $conta->fresh()->situacao);

        $s->sincronizar($conta->fresh());
        $this->assertSame('conectada', $conta->fresh()->situacao);
        $this->assertNotNull($conta->fresh()->conectada_em);
    }

    public function test_pede_extrato_busca_grava_sem_duplicar_e_so_o_efetivado(): void
    {
        $conta = $this->conta();
        $resposta = $this->extrato(
            [$this->transacao('C1', 'credit', 150.00, '2026-11-10'), $this->transacao('C2', 'credit', 99.90, '2026-11-11', ['status' => 'PENDING'])],
            [$this->transacao('D1', 'debit', 320.50, '2026-11-12', ['description' => 'PAGAMENTO BOLETO', 'operationType' => 'BOLETO', 'paymentDigitableLine' => '34191.79001 01043.510047 91020.150008 1 99990000032050'])],
        );
        $this->fakeTecnospeed([
            self::BASE . '/api/v1/statement/openfinance' => Http::response(['uniqueId' => 'PROT1', 'status' => 'PROCESSING'], 201),
            self::BASE . '/api/v1/statement/openfinance/PROT1' => Http::response($resposta),
        ]);
        $s = app(SincronizadorExtrato::class);

        $this->assertSame('extrato pedido ao banco', $s->sincronizar($conta));
        $conta->refresh();
        $this->assertSame('PROT1', $conta->protocolo_pendente);
        $this->assertTrue($conta->proxima_sincronizacao_em->between(now()->addHours(5), now()->addHours(7)));

        $s->sincronizar($conta);
        $conta->refresh();
        $this->assertNull($conta->protocolo_pendente);
        $this->assertNotNull($conta->ultima_sincronizacao_em);

        $l = LancamentoBancario::withoutGlobalScopes()->orderBy('transaction_id')->get();
        $this->assertSame(['C1', 'D1'], $l->pluck('transaction_id')->all());          // C2 não efetivado fica fora
        $this->assertSame('credito', $l[0]->tipo);
        $this->assertSame('11122233344', $l[0]->contraparte_documento);               // crédito: quem pagou
        $this->assertSame('debito', $l[1]->tipo);
        $this->assertSame('12345678000195', $l[1]->contraparte_documento);            // débito: quem recebeu
        $this->assertSame('34191790010104351004791020150008199990000032050', $l[1]->linha_digitavel);
        $this->assertSame('320.50', $l[1]->valor);

        // fora da hora não pede de novo; forçando, pede — e gravar o mesmo extrato não duplica
        $this->assertSame('ainda não é hora', $s->sincronizar($conta));
        $s->sincronizar($conta, true);
        $s->sincronizar($conta->fresh());
        $this->assertSame(2, LancamentoBancario::withoutGlobalScopes()->count());
    }

    public function test_limite_de_seis_horas_da_tecnospeed_nao_vira_laço(): void
    {
        $conta = $this->conta();
        $this->fakeTecnospeed([self::BASE . '/api/v1/statement/openfinance' => Http::response(['code' => 429, 'message' => 'Rate limit'], 429)]);

        $this->assertStringStartsWith('erro', app(SincronizadorExtrato::class)->sincronizar($conta));
        $conta->refresh();
        $this->assertNull($conta->protocolo_pendente);
        $this->assertTrue($conta->proxima_sincronizacao_em->gt(now()->addHours(5)));
    }

    public function test_lancamento_que_o_banco_parou_de_mandar_sai_da_fila_e_conciliado_fica_marcado(): void
    {
        $conta = $this->conta(['protocolo_pendente' => 'PROT1', 'protocolo_inicio' => '2026-11-01', 'protocolo_fim' => '2026-11-17']);
        $some = $this->lancamento($conta, 'credito', 10, '2026-11-05', ['transaction_id' => 'SOME']);
        $baixado = $this->lancamento($conta, 'credito', 20, '2026-11-06', ['transaction_id' => 'BAIXADO', 'status' => 'conciliado']);
        $antigo = $this->lancamento($conta, 'credito', 30, '2026-10-01', ['transaction_id' => 'ANTIGO']);
        $this->fakeTecnospeed([self::BASE . '/api/v1/statement/openfinance/PROT1' => Http::response(
            $this->extrato([$this->transacao('NOVO', 'credit', 10, '2026-11-05')]))]);

        app(SincronizadorExtrato::class)->sincronizar($conta);

        $this->assertSame('removido', $some->fresh()->status);
        $this->assertTrue($baixado->fresh()->sumiu_no_banco);
        $this->assertSame('conciliado', $baixado->fresh()->status);
        $this->assertSame('pendente', $antigo->fresh()->status);                       // fora do período pedido: intocado
    }

    /* ---------------- cruzamento ---------------- */

    public function test_recebimento_com_mesmo_valor_e_cpf_do_cliente_vira_sugestao_certa(): void
    {
        $conta = $this->conta();
        $cliente = $this->createCliente($this->empresa, ['cpf_cnpj' => '111.222.333-44']);
        $titulo = $this->receber(150.00, '2026-11-12', ['cliente_id' => $cliente->id]);
        $this->receber(150.00, '2026-11-12');                                          // outro título igual, sem cliente
        $l = $this->lancamento($conta, 'credito', 150.00, '2026-11-10', ['contraparte_documento' => '11122233344']);

        $this->assertSame(1, app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id));

        $l->refresh();
        $this->assertSame('sugerido', $l->status);
        $this->assertSame($titulo->id, $l->sugestao_conta_receber_id);
        $this->assertTrue($l->confiancaAlta());
        $this->assertStringContainsString('mesmo CPF/CNPJ', $l->sugestao_motivo);
        $this->assertSame('pendente', $titulo->fresh()->status);                       // sugerir não baixa nada
    }

    public function test_pagamento_acha_a_conta_a_pagar_do_fornecedor(): void
    {
        $conta = $this->conta();
        $forn = $this->createFornecedor($this->empresa, ['cpf_cnpj' => '99.888.777/0001-66', 'razao_social' => 'Distribuidora Sul']);
        $titulo = $this->pagar(320.50, '2026-11-12', ['fornecedor_id' => $forn->id]);
        $l = $this->lancamento($conta, 'debito', 320.50, '2026-11-12', ['contraparte_documento' => '99888777000166']);

        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);

        $this->assertSame($titulo->id, $l->fresh()->sugestao_conta_pagar_id);
        $this->assertSame(100, $l->fresh()->sugestao_confianca);
    }

    public function test_valor_diferente_ou_muito_longe_do_vencimento_nao_sugere(): void
    {
        $conta = $this->conta();
        $this->receber(150.00, '2026-11-12');
        $this->receber(80.00, '2026-08-01');
        $a = $this->lancamento($conta, 'credito', 150.01, '2026-11-12');
        $b = $this->lancamento($conta, 'credito', 80.00, '2026-11-12');

        $this->assertSame(0, app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id));
        $this->assertSame('pendente', $a->fresh()->status);
        $this->assertSame('pendente', $b->fresh()->status);
    }

    public function test_dois_recebimentos_iguais_nao_pegam_o_mesmo_titulo(): void
    {
        $conta = $this->conta();
        $t1 = $this->receber(50.00, '2026-11-10');
        $t2 = $this->receber(50.00, '2026-11-11');
        $a = $this->lancamento($conta, 'credito', 50.00, '2026-11-10');
        $b = $this->lancamento($conta, 'credito', 50.00, '2026-11-11');

        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);

        $ids = [$a->fresh()->sugestao_conta_receber_id, $b->fresh()->sugestao_conta_receber_id];
        sort($ids);
        $this->assertSame([$t1->id, $t2->id], $ids);
    }

    public function test_conta_direto_com_a_loja_nao_entra_no_cruzamento(): void
    {
        $conta = $this->conta();
        $this->receber(70.00, '2026-11-10', ['forma_pagamento' => \App\Services\FiadoService::FORMA]);
        $l = $this->lancamento($conta, 'credito', 70.00, '2026-11-10');

        $this->assertSame(0, app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id));
        $this->assertSame('pendente', $l->fresh()->status);
    }

    /* ---------------- confirmar / desfazer ---------------- */

    public function test_confirmar_baixa_o_titulo_com_data_e_valor_do_banco_e_desfazer_reabre(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $titulo = $this->receber(150.00, '2026-11-12');
        $l = $this->lancamento($conta, 'credito', 150.00, '2026-11-10');
        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);

        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('Venda a prazo')->assertSee('Confirmar');
        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $l))->assertSessionHas('success');

        $titulo->refresh();
        $this->assertSame('paga', $titulo->status);
        $this->assertSame('2026-11-10', $titulo->pago_em->toDateString());
        $this->assertSame('150.00', $titulo->valor_pago);
        $this->assertSame('conciliado', $l->fresh()->status);
        $this->assertSame($titulo->id, $l->fresh()->conta_receber_id);
        $this->assertSame($this->financeiro->id, $l->fresh()->conciliado_por);

        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $l))->assertSessionHas('error');   // duas vezes não

        $this->eu()->post(route('app.bancos.lancamentos.desfazer', $l))->assertSessionHas('success');
        $this->assertSame('pendente', $titulo->fresh()->status);
        $this->assertNull($titulo->fresh()->pago_em);
        $this->assertSame('sugerido', $l->fresh()->status);                            // volta já com a sugestão
    }

    public function test_confirmar_os_certos_so_pega_nota_alta(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $cliente = $this->createCliente($this->empresa, ['cpf_cnpj' => '11122233344']);
        $certo = $this->receber(150.00, '2026-11-10', ['cliente_id' => $cliente->id]);
        $duvida1 = $this->pagar(60.00, '2026-11-01');
        $duvida2 = $this->pagar(60.00, '2026-11-02');
        $a = $this->lancamento($conta, 'credito', 150.00, '2026-11-10', ['contraparte_documento' => '11122233344']);
        $b = $this->lancamento($conta, 'debito', 60.00, '2026-11-08');
        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);
        $this->assertFalse($b->fresh()->confiancaAlta());

        $this->eu()->post(route('app.bancos.confirmar-certos'))->assertSessionHas('success', '1 lançamento(s) conciliado(s).');

        $this->assertSame('paga', $certo->fresh()->status);
        $this->assertSame('pendente', $duvida1->fresh()->status);
        $this->assertSame('pendente', $duvida2->fresh()->status);
        $this->assertSame('sugerido', $b->fresh()->status);
    }

    public function test_escolher_a_mao_recusar_e_ignorar(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $titulo = $this->pagar(500.00, '2026-09-01');                                  // longe demais para sugerir
        $l = $this->lancamento($conta, 'debito', 500.00, '2026-11-10');

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertOk()->assertSee('Compra de mercadoria');
        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $l), ['titulo_id' => $titulo->id])->assertSessionHas('success');
        $this->assertSame('paga', $titulo->fresh()->status);
        $this->assertSame('2026-11-10', $titulo->fresh()->pago_em->toDateString());

        $outro = $this->lancamento($conta, 'debito', 12.00, '2026-11-10');
        $this->eu()->post(route('app.bancos.lancamentos.ignorar', $outro));
        $this->assertSame('ignorado', $outro->fresh()->status);
        $this->eu()->post(route('app.bancos.lancamentos.desfazer', $outro));
        $this->assertSame('pendente', $outro->fresh()->status);
    }

    public function test_titulo_de_outra_empresa_nao_pode_ser_escolhido(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        [$outra, $unidadeOutra] = $this->createTenant('2');
        $alheio = ContaPagar::withoutGlobalScopes()->create([
            'empresa_id' => $outra->id, 'unidade_id' => $unidadeOutra->id, 'descricao' => 'Alheia', 'valor' => 40, 'valor_pago' => 0,
            'vencimento' => '2026-11-10', 'parcela' => 1, 'total_parcelas' => 1, 'status' => 'pendente',
        ]);
        $l = $this->lancamento($conta, 'debito', 40.00, '2026-11-10');

        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $l), ['titulo_id' => $alheio->id])->assertSessionHas('error');
        $this->assertSame('pendente', $alheio->fresh()->status);
        $this->assertSame('pendente', $l->fresh()->status);
    }

    /* ---------------- rotina e admin ---------------- */

    public function test_rotina_pula_empresa_sem_liberacao_e_sem_chave_nao_faz_nada(): void
    {
        $this->conta();
        Http::fake();

        $this->artisan('openfinance:sincronizar')->expectsOutputToContain('empresa sem liberação')->assertSuccessful();
        Http::assertNothingSent();

        config(['services.tecnospeed.client_id' => '']);
        $this->artisan('openfinance:sincronizar')->expectsOutputToContain('sem chave')->assertSuccessful();
    }

    public function test_admin_grava_contas_do_plano_extras_e_nao_mostrar(): void
    {
        $admin = \App\Models\User::withoutGlobalScopes()->create([
            'name' => 'Admin', 'email' => 'admin-of@teste.com', 'password' => bcrypt('x'), 'perfil' => 'admin', 'status' => 'ativo', 'is_admin' => true,
        ]);
        $this->liberar(1);
        $plano = $this->empresa->planoAssinatura;

        $this->actingAs($admin)->put(route('admin.planos.update', $plano), [
            'nome' => $plano->nome, 'slug' => $plano->slug, 'preco_mensal' => 100, 'preco_anual' => 1000, 'max_unidades' => 5,
            'max_usuarios' => 10, 'max_produtos' => 1000, 'max_notas_mes' => 100, 'dias_trial' => 0, 'ordem' => 1, 'ativo' => 1,
            'open_finance_habilitado' => 1, 'open_finance_contas' => 4,
        ])->assertRedirect();
        $this->assertSame(4, $plano->fresh()->open_finance_contas);

        $e = Empresa::withoutGlobalScopes()->find($this->empresa->id);
        $this->actingAs($admin)->put(route('admin.empresas.update', $e), [
            'cnpj' => '11.222.333/0001-81', 'regime_cobranca' => 'padrao',
            'razao_social' => $e->razao_social, 'regime_tributario' => 'simples_nacional', 'status' => 'ativo',
            'open_finance_contas_extras' => 2, 'open_finance_ocultar' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $e->refresh();
        $this->assertSame(2, $e->open_finance_contas_extras);
        $this->assertTrue($e->open_finance_ocultar);
        $this->assertSame(6, $e->openFinanceLimiteContas());

        // as duas telas do admin abrem com o quadro novo
        $this->actingAs($admin)->get(route('admin.planos.edit', $plano))->assertOk()->assertSee('Este plano inclui a ligação com o banco');
        $this->actingAs($admin)->get(route('admin.empresas.edit', $e))->assertOk()->assertSee('Contas extras (venda avulsa)')->assertSee('0 de 6');
    }
}
