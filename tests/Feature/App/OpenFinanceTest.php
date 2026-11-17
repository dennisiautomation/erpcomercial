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
            // saldo: por padrão o banco ainda não informou (não pode atrapalhar o extrato)
            self::BASE . '/api/v1/balance/openfinance/realtime*' => Http::response(['code' => 404, 'message' => 'Verifique se a conta cadastrada possui o extrato ativo'], 404),
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

    /* ---------------- despesa nova a partir do extrato ---------------- */

    private function classificacao(): array
    {
        $plano = \App\Models\PlanoContas::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'codigo' => '3.2.01', 'nome' => 'Aluguel', 'tipo' => 'despesa', 'natureza' => 'analitica', 'ativo' => true,
        ]);
        $centro = \App\Models\CentroCusto::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'codigo' => 'ADM', 'nome' => 'Administrativo', 'ativo' => true,
        ]);

        return [$plano, $centro];
    }

    public function test_saida_sem_conta_vira_despesa_paga_classificada_e_o_sistema_aprende(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        [$plano, $centro] = $this->classificacao();
        $l = $this->lancamento($conta, 'debito', 2500.00, '2026-11-05', [
            'contraparte_documento' => '44555666000177', 'contraparte_nome' => 'IMOBILIARIA CENTRAL LTDA', 'operacao' => 'PIX',
        ]);

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertOk()
            ->assertSee('Lançar despesa')->assertSee('falta classificar')->assertSee('Aluguel')->assertSee('Administrativo');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $l), [
            'descricao' => 'Aluguel da loja', 'plano_conta_id' => $plano->id, 'centro_custo_id' => $centro->id, 'lembrar' => 1,
        ])->assertSessionHas('success');

        $titulo = ContaPagar::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('paga', $titulo->status);
        $this->assertSame('2500.00', $titulo->valor_pago);
        $this->assertSame('2026-11-05', $titulo->pago_em->toDateString());
        $this->assertSame('2026-11-01', $titulo->competencia->toDateString());
        $this->assertSame($plano->id, $titulo->plano_conta_id);
        $this->assertSame('Aluguel', $titulo->categoria);
        $this->assertSame($centro->id, $titulo->centro_custo_id);
        $this->assertSame('IMOBILIARIA CENTRAL LTDA', $titulo->favorecido_nome);
        $this->assertSame('pix', $titulo->forma_pagamento);
        $this->assertSame($this->unidade->id, $titulo->unidade_id);
        $this->assertSame('conciliado', $l->fresh()->status);
        $this->assertTrue($l->fresh()->titulo_criado);

        // mês seguinte, mesmo CNPJ: já vem conhecida e lança com um clique
        $prox = $this->lancamento($conta, 'debito', 2600.00, '2026-12-05', ['contraparte_documento' => '44555666000177', 'contraparte_nome' => 'IMOBILIARIA CENTRAL LTDA']);
        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertSee('Já conhecida')->assertSee('Lançar as 1 já conhecidas');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $prox), ['usar_proposta' => 1])->assertSessionHas('success');
        $novo = ContaPagar::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->assertSame('Aluguel da loja', $novo->descricao);
        $this->assertSame($plano->id, $novo->plano_conta_id);
        $this->assertSame($centro->id, $novo->centro_custo_id);
        $this->assertSame('2600.00', $novo->valor);
        $this->assertSame(2, \App\Models\RegraConciliacao::withoutGlobalScopes()->first()->usos);
    }

    public function test_lancar_as_conhecidas_de_uma_vez_e_desfazer_apaga_a_despesa_criada(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        [$plano, $centro] = $this->classificacao();
        \App\Models\RegraConciliacao::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'chave_tipo' => 'descricao', 'chave' => 'tarifa pacote de servicos',
            'descricao_padrao' => 'Tarifa bancária', 'plano_conta_id' => $plano->id, 'centro_custo_id' => $centro->id, 'usos' => 3,
        ]);
        $a = $this->lancamento($conta, 'debito', 49.90, '2026-11-03', ['descricao' => 'TARIFA PACOTE DE SERVICOS 11/2026']);
        $b = $this->lancamento($conta, 'debito', 49.90, '2026-12-03', ['descricao' => 'Tarifa Pacote de Serviços 12/2026']);
        $desconhecido = $this->lancamento($conta, 'debito', 10.00, '2026-11-04', ['descricao' => 'COMPRA CARTAO 1234']);
        $entrada = $this->lancamento($conta, 'credito', 49.90, '2026-11-03', ['descricao' => 'TARIFA PACOTE DE SERVICOS']);

        $this->eu()->post(route('app.bancos.lancar-conhecidos'))->assertSessionHas('success', '2 despesa(s) lançada(s) com a classificação já conhecida.');

        $this->assertSame(2, ContaPagar::withoutGlobalScopes()->where('descricao', 'Tarifa bancária')->where('status', 'paga')->count());
        $this->assertSame('pendente', $desconhecido->fresh()->status);
        $this->assertSame('pendente', $entrada->fresh()->status);                      // entrada nunca vira despesa

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $entrada), [])->assertSessionHas('error');

        $this->eu()->post(route('app.bancos.lancamentos.desfazer', $a))->assertSessionHas('success');
        $this->assertSame('pendente', $a->fresh()->status);
        $this->assertFalse($a->fresh()->titulo_criado);
        $this->assertSame(1, ContaPagar::withoutGlobalScopes()->whereNull('deleted_at')->count());   // a despesa criada foi apagada
        $this->assertSame('conciliado', $b->fresh()->status);
    }

    public function test_confirmar_conta_cadastrada_ensina_a_classificacao_e_fornecedor_por_cpf_e_reconhecido(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        [$plano, $centro] = $this->classificacao();
        $pessoa = $this->createFornecedor($this->empresa, ['cpf_cnpj' => '321.654.987-00', 'razao_social' => 'José Pedreiro']);
        $titulo = $this->pagar(800.00, '2026-11-10', ['fornecedor_id' => $pessoa->id, 'plano_conta_id' => $plano->id, 'centro_custo_id' => $centro->id]);
        $l = $this->lancamento($conta, 'debito', 800.00, '2026-11-10', ['contraparte_documento' => '32165498700', 'contraparte_nome' => 'JOSE PEDREIRO']);
        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);

        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $l))->assertSessionHas('success');
        $this->assertSame('paga', $titulo->fresh()->status);

        // nova saída para o mesmo CPF, sem conta cadastrada: proposta vem com fornecedor, conta e centro
        $novo = $this->lancamento($conta, 'debito', 350.00, '2026-11-20', ['contraparte_documento' => '32165498700', 'contraparte_nome' => 'JOSE PEDREIRO']);
        $p = app(\App\Services\OpenFinance\ClassificadorLancamentos::class)->propor($novo);
        $this->assertSame('regra', $p['origem']);
        $this->assertSame($pessoa->id, $p['fornecedor_id']);
        $this->assertSame('José Pedreiro', $p['favorecido']);
        $this->assertSame($plano->id, $p['plano_conta_id']);
        $this->assertSame($centro->id, $p['centro_custo_id']);

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $novo), ['usar_proposta' => 1]);
        $criada = ContaPagar::withoutGlobalScopes()->orderByDesc('id')->first();
        $this->assertSame($pessoa->id, $criada->fornecedor_id);
        $this->assertNull($criada->favorecido_nome);
    }

    public function test_banco_que_manda_cpf_no_lugar_do_nome_usa_o_nome_do_texto(): void
    {
        $conta = $this->conta();
        $l = $this->lancamento($conta, 'debito', 500.00, '2026-11-17', [
            'contraparte_nome' => '082.399.694-88', 'contraparte_documento' => '08239969488', 'descricao' => 'Pix enviado SANDERSON RICARDO DA SILVA',
        ]);

        $p = app(\App\Services\OpenFinance\ClassificadorLancamentos::class)->propor($l);

        $this->assertSame('SANDERSON RICARDO DA SILVA', $p['favorecido']);
        $this->assertSame('Pagamento a Sanderson Ricardo Da Silva', $p['descricao']);
    }

    public function test_cadastra_conta_do_plano_e_centro_de_custo_na_hora_sem_duplicar(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $a = $this->lancamento($conta, 'debito', 90.00, '2026-11-05', ['contraparte_nome' => 'POSTO SHELL', 'contraparte_documento' => '11222333000144']);
        $b = $this->lancamento($conta, 'debito', 70.00, '2026-11-06', ['contraparte_nome' => 'POSTO IPIRANGA', 'contraparte_documento' => '55666777000188']);

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertOk()
            ->assertSee('Ligar a uma conta a pagar já cadastrada')->assertSee('Cadastrar uma conta nova agora')->assertSee('Cadastrar um centro de custo novo agora');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $a), [
            'descricao' => 'Combustível', 'plano_conta_novo' => '  Combustível ', 'centro_custo_novo' => 'Entregas', 'lembrar' => 1,
        ])->assertSessionHas('success');

        $plano = \App\Models\PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->firstOrFail();
        $centro = \App\Models\CentroCusto::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->firstOrFail();
        $this->assertSame(['9.01', 'Combustível', 'despesa', 'analitica'], [$plano->codigo, $plano->nome, $plano->tipo, $plano->natureza]);
        $this->assertSame(['ENTREG', 'Entregas'], [$centro->codigo, $centro->nome]);
        $titulo = ContaPagar::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([$plano->id, $centro->id, 'Combustível', 'Entregas'], [$titulo->plano_conta_id, $titulo->centro_custo_id, $titulo->categoria, $titulo->centro_custo]);

        // mesmo nome de novo (com outra caixa): usa o que existe, não duplica
        $this->eu()->post(route('app.bancos.lancamentos.lancar', $b), ['plano_conta_novo' => 'COMBUSTÍVEL', 'centro_custo_novo' => 'entregas'])->assertSessionHas('success');
        $this->assertSame(1, \App\Models\PlanoContas::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count());
        $this->assertSame(1, \App\Models\CentroCusto::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count());

        // a conta criada na hora já aparece nas telas normais do ERP
        $this->eu()->get(route('app.plano-contas.index'))->assertOk()->assertSee('Combustível');

        // nome curto demais é recusado e nada é criado
        $c = $this->lancamento($conta, 'debito', 5.00, '2026-11-07');
        $this->eu()->post(route('app.bancos.lancamentos.lancar', $c), ['plano_conta_novo' => 'X'])->assertSessionHasErrors('plano_conta_novo');
        $this->assertSame('pendente', $c->fresh()->status);
    }

    public function test_cadastra_fornecedor_simples_na_hora_so_com_nome_e_documento(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $a = $this->lancamento($conta, 'debito', 500.00, '2026-11-17', ['contraparte_nome' => 'SANDERSON RICARDO DA SILVA', 'contraparte_documento' => '08239969488']);
        $b = $this->lancamento($conta, 'debito', 300.00, '2026-11-18', ['contraparte_nome' => 'SANDERSON R SILVA', 'contraparte_documento' => '08239969488']);
        $semDoc = $this->lancamento($conta, 'debito', 49.90, '2026-11-18', ['descricao' => 'TARIFA PACOTE']);

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertOk()->assertSee('Cadastrar como fornecedor agora');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $a), ['fornecedor_novo' => 'Sanderson Ricardo', 'lembrar' => 1])->assertSessionHas('success');

        $f = \App\Models\Fornecedor::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->firstOrFail();
        $this->assertSame(['Sanderson Ricardo', '082.399.694-88', null, null], [$f->razao_social, $f->cpf_cnpj, $f->cep, $f->telefone]);
        $titulo = ContaPagar::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($f->id, $titulo->fornecedor_id);
        $this->assertNull($titulo->favorecido_nome);

        // mesmo CPF de novo: usa o fornecedor que já existe (com outro nome digitado, não duplica)
        $this->eu()->post(route('app.bancos.lancamentos.lancar', $b), ['fornecedor_novo' => 'Outro nome'])->assertSessionHas('success');
        $this->assertSame(1, \App\Models\Fornecedor::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->count());
        $this->assertSame($f->id, ContaPagar::withoutGlobalScopes()->orderByDesc('id')->first()->fornecedor_id);

        // sem CPF/CNPJ do banco: entra só com o nome
        $this->eu()->post(route('app.bancos.lancamentos.lancar', $semDoc), ['fornecedor_novo' => 'Banco Itaú'])->assertSessionHas('success');
        $banco = \App\Models\Fornecedor::withoutGlobalScopes()->where('razao_social', 'Banco Itaú')->firstOrFail();
        $this->assertNull($banco->cpf_cnpj);

        // o fornecedor simples abre nas telas normais e a tela completa continua exigindo endereço
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->actingAsUser($dono, $this->unidade)->get(route('app.fornecedores.index'))->assertOk()->assertSee('Sanderson Ricardo');
        $this->actingAsUser($dono, $this->unidade)->get(route('app.fornecedores.show', $f))->assertOk();
        $this->actingAsUser($dono, $this->unidade)->get(route('app.fornecedores.edit', $f))->assertOk();
        $this->actingAsUser($dono, $this->unidade)->post(route('app.fornecedores.store'), ['cpf_cnpj' => '11.222.333/0001-81', 'razao_social' => 'Sem endereço'])
            ->assertSessionHasErrors(['cep', 'logradouro', 'telefone']);
    }

    public function test_plano_de_contas_de_outra_empresa_e_recusado_ao_lancar(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        [$outra] = $this->createTenant('2');
        $alheio = \App\Models\PlanoContas::withoutGlobalScopes()->create([
            'empresa_id' => $outra->id, 'codigo' => '9', 'nome' => 'Alheia', 'tipo' => 'despesa', 'natureza' => 'analitica', 'ativo' => true,
        ]);
        $l = $this->lancamento($conta, 'debito', 10.00, '2026-11-05');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $l), ['plano_conta_id' => $alheio->id])->assertSessionHasErrors('plano_conta_id');
        $this->assertSame(0, ContaPagar::withoutGlobalScopes()->count());
    }

    public function test_fornecedor_pessoa_fisica_com_cpf_e_aceito_no_cadastro(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($dono, $this->unidade)->get(route('app.fornecedores.create'))->assertOk()
            ->assertSee('CPF ou CNPJ')->assertSee('data-mask="cpfCnpj"', false);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.fornecedores.store'), [
            'cpf_cnpj' => '321.654.987-00', 'razao_social' => 'José Pedreiro', 'cep' => '01001-000', 'logradouro' => 'Rua A',
            'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'uf' => 'SP', 'telefone' => '(11) 99999-0000',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, \App\Models\Fornecedor::withoutGlobalScopes()->where('cpf_cnpj', '321.654.987-00')->count());
    }

    /* ---------------- cartão de crédito ---------------- */

    private function cartao(ContaBancaria $conta, array $extra = []): \App\Models\CartaoCredito
    {
        return \App\Models\CartaoCredito::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $conta->empresa_id, 'conta_bancaria_id' => $conta->id, 'final' => '2524', 'importar' => true,
        ]);
    }

    public function test_busca_os_cartoes_da_conta_e_a_empresa_escolhe_quais_trazer(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $this->fakeTecnospeed([self::BASE . '/api/v1/statement/credit-card/openfinance*' => Http::response([
            ['cardNumber' => '2524', 'additionalCards' => '7012,4714', 'availableCreditLimit' => '1500.50', 'creditLimit' => '5000'],
            ['cardNumber' => '6869', 'additionalCards' => null, 'availableCreditLimit' => '100', 'creditLimit' => '100'],
        ])]);

        $this->eu()->post(route('app.bancos.contas.cartoes', $conta))->assertSessionHas('success');
        $this->eu()->post(route('app.bancos.contas.cartoes', $conta));                 // de novo não duplica

        $cartoes = \App\Models\CartaoCredito::withoutGlobalScopes()->orderBy('final')->get();
        $this->assertSame(['2524', '6869'], $cartoes->pluck('final')->all());
        $this->assertSame('7012,4714', $cartoes[0]->adicionais);
        $this->assertSame('5000.00', $cartoes[0]->limite);
        $this->assertFalse($cartoes[0]->importar);                                     // nasce desligado
        $this->assertSame(1, $this->empresa->openFinanceContasUsadas());               // cartão não ocupa vaga

        $this->eu()->post(route('app.bancos.cartoes.alternar', $cartoes[0]));
        $this->assertTrue($cartoes[0]->fresh()->importar);
        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('Cartão final 2524')->assertSee('1 trazendo as compras')->assertSee('7012, 4714');
    }

    public function test_compras_do_cartao_entram_e_credito_no_cartao_nao_vira_receita(): void
    {
        $conta = $this->conta(['proxima_sincronizacao_em' => now()->addHours(3)]);     // a conta em si não está na hora
        $cartao = $this->cartao($conta);
        $desligado = $this->cartao($conta, ['final' => '6869', 'importar' => false]);
        $compras = $this->extrato(
            [$this->transacao('PG1', 'credit', 900.00, '2026-11-10', ['description' => 'PAGAMENTO EFETUADO'])],
            [
                $this->transacao('CP1', 'debit', 120.00, '2026-11-03', ['description' => 'POSTO BOA VIAGEM', 'operationType' => 'OUTROS', 'participantReceiver' => null, 'creditCardInstallmentNumber' => 2, 'creditCardTotalInstallments' => 4]),
                $this->transacao('CP2', 'debit', 59.90, '2026-11-04', ['description' => 'DL*GOOGLE YouTube', 'operationType' => 'OUTROS', 'participantReceiver' => null]),
            ],
        );
        $this->fakeTecnospeed([
            self::BASE . '/api/v1/statement/openfinance' => Http::response(['uniqueId' => 'PROTC', 'status' => 'PROCESSING']),
            self::BASE . '/api/v1/statement/openfinance/PROTC' => Http::response($compras),
        ]);
        $s = app(SincronizadorExtrato::class);

        $this->assertStringContainsString('Cartão final 2524: compras pedidas ao banco', $s->sincronizar($conta));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/statement/openfinance')
            && $r->data()['statementType'] === 'CREDIT_CARD' && $r->data()['cardNumber'] === '2524');
        $this->assertNull($desligado->fresh()->protocolo_pendente);                    // cartão desligado não gasta consulta

        $this->assertStringContainsString('2 compra(s) nova(s)', $s->sincronizar($conta->fresh()));

        $l = LancamentoBancario::withoutGlobalScopes()->orderBy('data')->get();
        $this->assertCount(3, $l);
        $this->assertTrue($l->every(fn ($x) => $x->cartao_credito_id === $cartao->id));
        $this->assertSame('cc2524:CP1', $l[0]->transaction_id);
        $this->assertSame([2, 4], [$l[0]->parcela_numero, $l[0]->parcela_total]);
        $this->assertSame('pendente', $l[0]->status);
        $credito = $l->firstWhere('tipo', 'credito');
        $this->assertSame('ignorado', $credito->status);                               // pagamento de fatura no cartão
        $this->assertNotNull($cartao->fresh()->ultima_sincronizacao_em);
        $this->assertTrue($cartao->fresh()->proxima_sincronizacao_em->gt(now()->addHours(5)));

        // crédito no cartão nunca casa com conta a receber, mesmo com valor igual
        $this->receber(900.00, '2026-11-10');
        $credito->update(['status' => 'pendente']);
        $this->assertSame(0, app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id) - LancamentoBancario::withoutGlobalScopes()->where('status', 'sugerido')->where('tipo', 'debito')->count());
        $this->assertSame('pendente', $credito->fresh()->status);
    }

    public function test_periodo_sem_compras_no_cartao_nao_vira_erro(): void
    {
        $conta = $this->conta(['proxima_sincronizacao_em' => now()->addHours(3)]);
        $cartao = $this->cartao($conta, ['protocolo_pendente' => 'PROTV']);
        $this->fakeTecnospeed([self::BASE . '/api/v1/statement/openfinance/PROTV' => Http::response(['statement' => [
            'status' => 'FAILED', 'reason' => 'Falha ao criar extrato: nenhuma transação foi localizada no período solicitado',
            'creditCardCurrentCreditLimit' => '5000.00', 'creditCardCurrentAvaliableCreditLimit' => '4200.10',
        ], 'transaction' => ['credit' => [], 'debit' => []]])]);

        $this->assertStringContainsString('sem compras no período', app(SincronizadorExtrato::class)->sincronizar($conta));
        $cartao->refresh();
        $this->assertNull($cartao->ultimo_erro);
        $this->assertNull($cartao->protocolo_pendente);
        $this->assertSame('4200.10', $cartao->limite_disponivel);
    }

    public function test_compra_do_cartao_vira_despesa_com_forma_cartao_e_parcela_na_descricao(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $cartao = $this->cartao($conta);
        [$plano, $centro] = $this->classificacao();
        $compra = $this->lancamento($conta, 'debito', 120.00, '2026-11-03', [
            'cartao_credito_id' => $cartao->id, 'descricao' => 'POSTO BOA VIAGEM', 'parcela_numero' => 2, 'parcela_total' => 4,
        ]);

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem', 'cartao' => $cartao->id]))->assertOk()
            ->assertSee('POSTO BOA VIAGEM')->assertSee('parcela 2/4')->assertSee('Cartão final 2524');

        $this->eu()->post(route('app.bancos.lancamentos.lancar', $compra), ['plano_conta_id' => $plano->id, 'centro_custo_id' => $centro->id, 'lembrar' => 1])
            ->assertSessionHas('success');

        $d = ContaPagar::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('POSTO BOA VIAGEM (2/4)', $d->descricao);
        $this->assertSame('cartao_credito', $d->forma_pagamento);
        $this->assertSame('paga', $d->status);
        $this->assertSame($centro->id, $d->centro_custo_id);

        // parcela seguinte no mesmo posto: já conhecida, com a parcela certa na descrição
        $prox = $this->lancamento($conta, 'debito', 120.00, '2026-12-03', [
            'cartao_credito_id' => $cartao->id, 'descricao' => 'POSTO BOA VIAGEM', 'parcela_numero' => 3, 'parcela_total' => 4,
        ]);
        $p = app(\App\Services\OpenFinance\ClassificadorLancamentos::class)->propor($prox);
        $this->assertSame('regra', $p['origem']);
        $this->assertSame('POSTO BOA VIAGEM (3/4)', $p['descricao']);
        $this->assertSame($plano->id, $p['plano_conta_id']);
    }

    public function test_pagamento_da_fatura_na_conta_nao_vira_despesa_de_novo(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $cartao = $this->cartao($conta);
        $this->lancamento($conta, 'credito', 900.00, '2026-11-10', ['cartao_credito_id' => $cartao->id, 'status' => 'ignorado', 'descricao' => 'PAGAMENTO EFETUADO']);
        $saida = $this->lancamento($conta, 'debito', 900.00, '2026-11-09', ['descricao' => 'PAGTO FATURA CARTAO']);
        $outra = $this->lancamento($conta, 'debito', 900.00, '2026-11-09', ['descricao' => 'PIX ENVIADO']);   // mesmo valor, mas só há um crédito no cartão

        $this->assertSame(1, app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id));
        $this->assertSame($cartao->id, $saida->fresh()->sugestao_fatura_cartao_id);
        $this->assertSame('pendente', $outra->fresh()->status);

        $this->eu()->get(route('app.bancos.index'))->assertOk()->assertSee('Pagamento da fatura')->assertSee('Não entra como despesa');
        $this->eu()->post(route('app.bancos.lancamentos.confirmar', $saida))->assertSessionHas('success');

        $saida->refresh();
        $this->assertSame('conciliado', $saida->status);
        $this->assertSame($cartao->id, $saida->fatura_cartao_id);
        $this->assertSame(0, ContaPagar::withoutGlobalScopes()->count());              // nenhuma despesa criada

        $this->eu()->post(route('app.bancos.lancamentos.desfazer', $saida));
        $this->assertNull($saida->fresh()->fatura_cartao_id);

        // à mão, e cartão de outra empresa é recusado
        $this->eu()->post(route('app.bancos.lancamentos.fatura', $outra), ['cartao_id' => $cartao->id])->assertSessionHas('success');
        $this->assertSame($cartao->id, $outra->fresh()->fatura_cartao_id);
        [$emp2] = $this->createTenant('2');
        $alheio = $this->cartao($this->conta(['empresa_id' => $emp2->id, 'account_hash' => 'H2']), ['final' => '9999']);
        $x = $this->lancamento($conta, 'debito', 5.00, '2026-11-09');
        $this->eu()->post(route('app.bancos.lancamentos.fatura', $x), ['cartao_id' => $alheio->id])->assertSessionHasErrors('cartao_id');
        $this->eu()->post(route('app.bancos.cartoes.alternar', $alheio))->assertNotFound();
    }

    public function test_internet_fora_do_ar_nao_derruba_a_rotina(): void
    {
        $conta = $this->conta();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host'));

        $resultado = app(SincronizadorExtrato::class)->sincronizar($conta);

        $this->assertStringStartsWith('erro: Não foi possível falar com o banco agora', $resultado);
        $this->assertTrue($conta->fresh()->proxima_sincronizacao_em->between(now()->addMinutes(50), now()->addMinutes(70)));
        $this->artisan('openfinance:sincronizar')->assertSuccessful();
    }

    /* ---------------- saldo e painel ---------------- */

    public function test_saldo_vem_do_banco_respeita_o_intervalo_e_falha_nao_atrapalha_o_extrato(): void
    {
        $conta = $this->conta(['proxima_sincronizacao_em' => now()->addHours(3)]);
        $this->fakeTecnospeed([self::BASE . '/api/v1/balance/openfinance/realtime*' => Http::sequence()
            ->push(['balance' => 1234.56, 'blockedBalance' => 0, 'automaticallyInvestedBalance' => 800.10, 'currencyCode' => 'BRL',
                'updateDateTime' => now()->subMinutes(10)->toIso8601String(), 'nextUpdate' => now()->addHours(6)->toIso8601String()])
            ->push(['code' => 404, 'message' => 'Verifique se a conta cadastrada possui o extrato ativo'], 404)]);
        $s = app(SincronizadorExtrato::class);

        $this->assertSame('ainda não é hora', $s->sincronizar($conta));
        $conta->refresh();
        $this->assertSame('1234.56', $conta->saldo);
        $this->assertSame('800.10', $conta->saldo_aplicado);
        $this->assertTrue($conta->saldo_proxima_em->gt(now()->addHours(5)));

        $s->sincronizar($conta);                                                       // dentro das 6 h: não consulta de novo
        Http::assertSentCount(2);                                                      // token + 1 saldo

        $conta->update(['saldo_proxima_em' => now()->subMinute()]);
        $this->assertSame('ainda não é hora', $s->sincronizar($conta->fresh()));      // saldo falhou, extrato segue normal
        $this->assertSame('1234.56', $conta->fresh()->saldo);                          // mantém o último saldo bom
        $this->assertNull($conta->fresh()->ultimo_erro);
    }

    public function test_painel_mostra_saldo_o_que_falta_e_o_mes_sem_misturar_cartao(): void
    {
        $this->liberar(2);
        $conta = $this->conta(['saldo' => 1000.00, 'saldo_aplicado' => 250.00, 'saldo_atualizado_em' => now()->subHour()]);
        $this->conta(['account_hash' => 'HASH2', 'apelido' => 'Sicredi', 'conta' => '999', 'saldo' => 500.50, 'saldo_atualizado_em' => now()]);
        $cartao = $this->cartao($conta);
        $hoje = now()->toDateString();
        $this->lancamento($conta, 'credito', 300.00, $hoje, ['status' => 'conciliado']);
        $this->lancamento($conta, 'debito', 120.00, $hoje, ['status' => 'sugerido', 'sugestao_confianca' => 90]);
        $this->lancamento($conta, 'debito', 80.00, $hoje);
        $this->lancamento($conta, 'debito', 999.00, $hoje, ['cartao_credito_id' => $cartao->id]);   // compra no cartão: fora do "saíram da conta"
        $this->lancamento($conta, 'debito', 50.00, $hoje, ['status' => 'removido']);

        $this->eu()->get(route('app.bancos.index'))->assertOk()
            ->assertSee('Saldo nas contas')->assertSee('R$ 1.500,50')->assertDontSee('November')->assertSee('R$ 250,00 em aplicação automática')
            ->assertSee('R$ 120,00 já com sugestão')
            ->assertSee('R$ 1.079,00 para classificar')                                // 80 da conta + 999 do cartão
            ->assertSee('+ R$ 300,00')->assertSee('− R$ 200,00')                       // só a conta: 120 + 80
            ->assertSee('1 de 4 conciliados (25%)');
    }

    public function test_fila_separa_entradas_e_saidas(): void
    {
        $this->liberar(1);
        $conta = $this->conta();
        $this->lancamento($conta, 'credito', 111.11, '2026-11-10', ['descricao' => 'ENTRADA UM']);
        $this->lancamento($conta, 'debito', 222.22, '2026-11-10', ['descricao' => 'SAIDA UM']);
        $this->lancamento($conta, 'debito', 333.33, '2026-11-11', ['descricao' => 'SAIDA DOIS']);

        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem']))->assertOk()
            ->assertSee('ENTRADA UM')->assertSee('SAIDA UM')->assertSeeInOrder(['Tudo', '3', 'Entradas', '1', 'Saídas', '2']);
        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem', 'lado' => 'entradas']))->assertOk()
            ->assertSee('ENTRADA UM')->assertDontSee('SAIDA UM')->assertDontSee('SAIDA DOIS');
        $this->eu()->get(route('app.bancos.index', ['ver' => 'sem', 'lado' => 'saidas']))->assertOk()
            ->assertDontSee('ENTRADA UM')->assertSee('SAIDA UM')->assertSee('SAIDA DOIS')
            ->assertSee('lado=saidas', false);                                         // as abas e o filtro de conta mantêm o lado

        // "Confirmar os certos" com o lado escolhido só mexe naquele lado
        $t1 = $this->receber(111.11, '2026-11-10');
        $t2 = $this->pagar(222.22, '2026-11-10');
        app(ConciliadorSugestoes::class)->sugerirPendentes($this->empresa->id);
        $this->eu()->post(route('app.bancos.confirmar-certos'), ['lado' => 'saidas'])->assertSessionHas('success', '1 lançamento(s) conciliado(s).');
        $this->assertSame('paga', $t2->fresh()->status);
        $this->assertSame('pendente', $t1->fresh()->status);
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
