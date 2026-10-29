<?php

namespace Tests\Feature\Api;

use App\Models\Caixa;
use App\Models\ConfiguracaoLoja;
use App\Models\MovimentacaoCaixa;
use App\Models\Pedido;
use App\Models\PosEvento;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * API do app de vendas na maquininha Stone (api/integracao/pos/v1, 28/10/2026).
 * O dinheiro nunca pode sumir: cotação = valor gravado, Stone ≠ ERP → estorno,
 * reenvio com a mesma chave → uma venda só.
 */
class PosApiTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private const BASE = '/api/integracao/pos/v1';

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function operador(string $perfil = 'caixa'): \App\Models\User
    {
        $user = $this->createUser($this->empresa, $this->unidade, $perfil);
        $user->unidades()->attach($this->unidade->id);

        return $user;
    }

    private function token(\App\Models\User $user): string
    {
        $r = $this->postJson(self::BASE . '/auth/login', [
            'email' => $user->email, 'senha' => 'password', 'serial' => 'PB0921TESTE', 'modelo' => 'P2',
        ]);
        $r->assertOk();

        return $r->json('token');
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
    }

    private function venda(array $pagamento, array $extra = []): array
    {
        return array_merge([
            'chave' => (string) \Illuminate\Support\Str::uuid(),
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2]],
            'pagamento' => $pagamento,
        ], $extra);
    }

    private \App\Models\Produto $produto;

    private function prepararVenda(string $perfil = 'caixa'): array
    {
        $user = $this->operador($perfil);
        $this->produto = $this->createProduto($this->empresa, ['preco_venda' => 50.00, 'codigo_barras' => '7891000100103']);
        $this->darEntradaEstoque($this->unidade, $this->produto, 10, ['user_id' => $user->id]);
        $token = $this->token($user);
        $this->api($token)->postJson(self::BASE . '/caixa/abrir', ['valor_abertura' => 100])->assertOk();

        return [$user, $token];
    }

    public function test_login_devolve_token_e_loja_unica(): void
    {
        $user = $this->operador('vendedor');

        $r = $this->postJson(self::BASE . '/auth/login', ['email' => $user->email, 'senha' => 'password', 'serial' => 'X1']);

        $r->assertOk()->assertJsonPath('precisa_escolher_loja', false)->assertJsonCount(1, 'lojas');
        $this->assertStringStartsWith('pos_', $r->json('token'));
        $this->assertDatabaseHas('pos_tokens', ['user_id' => $user->id, 'unidade_id' => $this->unidade->id, 'dispositivo_serial' => 'X1']);
        $this->assertDatabaseMissing('pos_tokens', ['token_hash' => $r->json('token')]); // só o hash é salvo
    }

    public function test_login_com_senha_errada_e_sem_token(): void
    {
        $user = $this->operador();

        $this->postJson(self::BASE . '/auth/login', ['email' => $user->email, 'senha' => 'errada'])->assertStatus(422);
        $this->getJson(self::BASE . '/me')->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer pos_invalido'])->getJson(self::BASE . '/me')->assertStatus(401);
    }

    public function test_muitas_requisicoes_do_aparelho_nao_travam_o_login(): void
    {
        // Loja com várias maquininhas atrás do mesmo IP: o limite é por aparelho/e-mail, não um balde único por IP.
        $user = $this->operador();
        $token = $this->token($user);
        for ($i = 0; $i < 25; $i++) {
            $this->api($token)->getJson(self::BASE . '/me')->assertOk();
        }

        $outro = $this->operador('vendedor');
        $this->postJson(self::BASE . '/auth/login', ['email' => $outro->email, 'senha' => 'password'])->assertOk();
    }

    public function test_perfil_consulta_nao_entra_no_app(): void
    {
        $user = $this->operador('consulta');

        $this->postJson(self::BASE . '/auth/login', ['email' => $user->email, 'senha' => 'password'])->assertStatus(403);
    }

    public function test_me_traz_permissoes_do_perfil(): void
    {
        $token = $this->token($this->operador('caixa'));

        $this->api($token)->getJson(self::BASE . '/me')
            ->assertOk()
            ->assertJsonPath('permissoes.vender', true)
            ->assertJsonPath('permissoes.emitir_pedido', false) // caixa não tira pedido, como na web
            ->assertJsonPath('loja.id', $this->unidade->id)
            ->assertJsonPath('caixa', null);
    }

    public function test_busca_produto_por_codigo_de_barras_com_preco_e_estoque(): void
    {
        [, $token] = $this->prepararVenda();

        $this->api($token)->getJson(self::BASE . '/produtos?q=7891000100103')
            ->assertOk()
            ->assertJsonPath('produtos.0.id', $this->produto->id)
            ->assertJsonPath('produtos.0.precos.dinheiro_pix', 50)
            ->assertJsonPath('produtos.0.estoque', 10);
    }

    public function test_produto_de_outra_empresa_nao_aparece(): void
    {
        [, $token] = $this->prepararVenda();
        [$outra] = $this->createTenant('2');
        $alheio = $this->createProduto($outra, ['descricao' => 'Produto alheio']);

        $this->api($token)->getJson(self::BASE . '/produtos/' . $alheio->id)->assertNotFound();
        $this->api($token)->getJson(self::BASE . '/produtos?q=alheio')->assertJsonCount(0, 'produtos');
    }

    public function test_vender_sem_caixa_aberto_e_bloqueado(): void
    {
        $user = $this->operador();
        $this->produto = $this->createProduto($this->empresa);
        $token = $this->token($user);

        $this->api($token)->postJson(self::BASE . '/vendas/cotar', $this->venda(['forma' => 'dinheiro']))
            ->assertStatus(409)->assertJsonPath('codigo', 'caixa_fechado');
    }

    public function test_cotar_nao_grava_nada(): void
    {
        [, $token] = $this->prepararVenda();

        $this->api($token)->postJson(self::BASE . '/vendas/cotar', $this->venda(['forma' => 'pix']))
            ->assertOk()->assertJsonPath('total', 100)->assertJsonPath('total_centavos', 10000);

        $this->assertSame(0, Venda::withoutGlobalScopes()->count());
    }

    public function test_cotar_credito_parcelado_inclui_juros_da_loja(): void
    {
        ConfiguracaoLoja::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id,
            'juros_por_parcela' => ['3' => 5], 'max_parcelas' => 12,
        ]);
        [, $token] = $this->prepararVenda();

        $r = $this->api($token)->postJson(self::BASE . '/vendas/cotar', $this->venda(['forma' => 'cartao_credito', 'parcelas' => 3]));

        $r->assertOk();
        $this->assertGreaterThan(100, $r->json('total'));
        $this->assertGreaterThan(0, $r->json('juros'));
        $this->assertSame(100.0, (float) $r->json('valor_base'));
    }

    public function test_venda_em_dinheiro_com_troco_entra_no_caixa(): void
    {
        [$user, $token] = $this->prepararVenda();

        $r = $this->api($token)->postJson(self::BASE . '/vendas', $this->venda([
            'forma' => 'dinheiro', 'valor_base' => 100, 'valor_recebido' => 150,
        ]));

        $r->assertOk()->assertJsonPath('venda.total', 100)->assertJsonPath('venda.troco', 50);
        $this->assertNotEmpty($r->json('cupom'));
        $this->assertTrue(collect($r->json('cupom'))->contains('v', 'CUPOM NÃO FISCAL'));

        $venda = Venda::withoutGlobalScopes()->first();
        $this->assertSame($user->id, $venda->vendedor_id);
        $this->assertSame(100.0, (float) MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)->sum('valor'));
    }

    public function test_venda_no_credito_grava_dados_da_stone_e_e_idempotente(): void
    {
        [, $token] = $this->prepararVenda();
        $payload = $this->venda([
            'forma' => 'cartao_credito', 'parcelas' => 1, 'valor_base' => 100,
            'stone' => ['atk' => 'ATK123', 'autorizacao' => '654321', 'bandeira' => 'VISA', 'valor_centavos' => 10000, 'tipo' => 'CREDIT'],
        ]);

        $this->api($token)->postJson(self::BASE . '/vendas', $payload)->assertOk()->assertJsonPath('repetida', false);
        // Rede caiu depois da resposta: o app reenvia com a MESMA chave.
        $this->api($token)->postJson(self::BASE . '/vendas', $payload)->assertOk()->assertJsonPath('repetida', true);

        $this->assertSame(1, Venda::withoutGlobalScopes()->count());
        $pg = Venda::withoutGlobalScopes()->first()->pagamento_detalhes[0];
        $this->assertSame('01', $pg['bandeira']); // tBand Visa na NFC-e
        $this->assertSame('16501555000157', $pg['cnpj_credenciadora']);
        $this->assertSame('654321', $pg['autorizacao']);
        $this->assertSame('ATK123', $pg['stone_atk']);
    }

    public function test_valor_da_stone_diferente_do_total_pede_estorno_e_nao_grava(): void
    {
        [, $token] = $this->prepararVenda();

        $this->api($token)->postJson(self::BASE . '/vendas', $this->venda([
            'forma' => 'cartao_debito', 'valor_base' => 100,
            'stone' => ['atk' => 'ATK9', 'valor_centavos' => 9000],
        ]))->assertStatus(409)->assertJsonPath('estornar', true)->assertJsonPath('total_centavos', 10000);

        $this->assertSame(0, Venda::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('pos_eventos', ['tipo' => 'valor_divergente']);
    }

    public function test_cancelar_venda_propria_estorna_o_caixa(): void
    {
        [, $token] = $this->prepararVenda();
        $id = $this->api($token)->postJson(self::BASE . '/vendas', $this->venda([
            'forma' => 'pix', 'valor_base' => 100, 'stone' => ['atk' => 'PIX1', 'valor_centavos' => 10000],
        ]))->json('venda.id');

        $this->api($token)->postJson(self::BASE . "/vendas/{$id}/cancelamento/validar")
            ->assertOk()->assertJsonPath('estornos.0.atk', 'PIX1')->assertJsonPath('estornos.0.valor_centavos', 10000);

        $this->api($token)->postJson(self::BASE . "/vendas/{$id}/cancelar", ['motivo' => 'Cliente desistiu', 'estornos' => [['atk' => 'PIX1', 'ok' => true]]])
            ->assertOk()->assertJsonPath('venda.status', 'cancelada');

        $this->assertDatabaseHas('movimentacoes_caixa', ['venda_id' => $id, 'tipo' => 'estorno', 'valor' => 100]);
    }

    public function test_vendedor_nao_cancela_venda_de_outro(): void
    {
        [, $tokenCaixa] = $this->prepararVenda();
        $id = $this->api($tokenCaixa)->postJson(self::BASE . '/vendas', $this->venda(['forma' => 'dinheiro', 'valor_base' => 100]))->json('venda.id');

        $outro = $this->operador('vendedor');
        $tokenOutro = $this->token($outro);

        $this->api($tokenOutro)->postJson(self::BASE . "/vendas/{$id}/cancelamento/validar")->assertStatus(422);
    }

    public function test_pedido_emitido_confirmado_e_recebido_no_pix(): void
    {
        [, $token] = $this->prepararVenda('gerente');
        $cliente = $this->createCliente($this->empresa);
        $chave = (string) \Illuminate\Support\Str::uuid();
        $payload = ['chave' => $chave, 'cliente_id' => $cliente->id, 'confirmar' => true,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 3]], 'condicao_pagamento' => 'pix'];

        $r = $this->api($token)->postJson(self::BASE . '/pedidos', $payload);
        $r->assertOk()->assertJsonPath('pedido.status', 'confirmado')->assertJsonPath('pedido.total', 150);
        $this->assertTrue(collect($r->json('cupom'))->contains(fn ($l) => str_starts_with($l['v'] ?? '', 'PEDIDO Nº')));
        $this->api($token)->postJson(self::BASE . '/pedidos', $payload)->assertJsonPath('repetida', true);
        $this->assertSame(1, Pedido::withoutGlobalScopes()->count());

        $id = $r->json('pedido.id');
        $this->api($token)->postJson(self::BASE . "/pedidos/{$id}/faturar", [
            'chave' => (string) \Illuminate\Support\Str::uuid(),
            'pagamento' => ['forma' => 'pix', 'stone' => ['atk' => 'PIXPED', 'valor_centavos' => 15000]],
        ])->assertOk()->assertJsonPath('venda.total', 150);

        $this->assertSame('faturado', Pedido::withoutGlobalScopes()->find($id)->status->value);
        $venda = Venda::withoutGlobalScopes()->where('pedido_id', $id)->first();
        $this->assertSame('PIXPED', $venda->pagamento_detalhes[0]['stone_atk']);
        $this->assertSame(150.0, (float) MovimentacaoCaixa::withoutGlobalScopes()->where('venda_id', $venda->id)->sum('valor'));
    }

    public function test_perfil_caixa_nao_emite_pedido(): void
    {
        [, $token] = $this->prepararVenda('caixa');
        $cliente = $this->createCliente($this->empresa);

        $this->api($token)->postJson(self::BASE . '/pedidos', [
            'chave' => 'chave-123456', 'cliente_id' => $cliente->id,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertForbidden();
    }

    public function test_fechar_caixa_e_comprovante(): void
    {
        [, $token] = $this->prepararVenda();
        $this->api($token)->postJson(self::BASE . '/vendas', $this->venda(['forma' => 'dinheiro', 'valor_base' => 100]))->assertOk();

        $r = $this->api($token)->postJson(self::BASE . '/caixa/fechar', ['valor_contado' => 200]);
        $r->assertOk()->assertJsonPath('caixa.status', 'fechado')->assertJsonPath('caixa.resumo.esperado_dinheiro', 200);

        $this->api($token)->getJson(self::BASE . '/caixa/' . $r->json('caixa.id') . '/comprovante')
            ->assertOk()->assertJsonFragment(['v' => 'FECHAMENTO DE CAIXA']);
        $this->assertSame('fechado', Caixa::withoutGlobalScopes()->find($r->json('caixa.id'))->status->value);
    }

    public function test_eventos_do_app_ficam_no_log(): void
    {
        $token = $this->token($this->operador());

        $this->api($token)->postJson(self::BASE . '/eventos', ['eventos' => [
            ['tipo' => 'stone_pagamento_recusado', 'mensagem' => 'Senha inválida', 'dados' => ['codigo' => '-6']],
            ['tipo' => 'impressao_sem_papel'],
        ]])->assertOk()->assertJsonPath('gravados', 2);

        $this->assertSame(1, PosEvento::where('tipo', 'app_stone_pagamento_recusado')->count());
        $this->assertSame(1, PosEvento::where('tipo', 'login')->count());
    }
}
