<?php

namespace Tests\Feature\App;

use App\Enums\StatusPedido;
use App\Enums\TipoMovimentacaoEstoque;
use App\Models\EmpresaGateway;
use App\Models\Estoque;
use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Models\PedidoCobranca;
use App\Models\ProdutoFoto;
use App\Services\LojaOnline\ModelosLoja;
use App\Services\LojaOnline\PagamentoLoja;
use App\Services\LojaOnline\TemaCss;
use App\Services\SaldoEstoque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Loja online (e-commerce), 14/11/2026.
 *
 * O módulo é ligado por empresa pela plataforma (`lojas_online.ativo`); o lojista
 * marca os produtos, monta a aparência e põe no ar. A compra vira um PEDIDO comum
 * do ERP (rascunho, canal online) na loja/estoque configurados; o pagamento usa os
 * gateways que a empresa já tem.
 */
class LojaOnlineTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private $dono;
    private $produto;
    private int $principal;
    private LojaOnline $loja;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->produto = $this->createProduto($this->empresa, ['preco_venda' => 100, 'descricao' => 'Camisa Azul']);
        $this->principal = $this->estoquePrincipal($this->unidade);
        SaldoEstoque::registrar($this->empresa->id, $this->unidade->id, $this->principal, $this->produto->id,
            TipoMovimentacaoEstoque::Entrada->value, 5, ['custo_unitario' => 50, 'user_id' => $this->dono->id]);

        $this->empresa->update(['exige_documento_cadastro' => false]);
        $this->produto->forceFill(['loja_online' => true])->save();
        $this->loja = LojaOnline::create([
            'empresa_id' => $this->empresa->id, 'ativo' => true, 'publicada' => true, 'slug' => 'minha-loja',
            'nome' => 'Minha Loja', 'unidade_id' => $this->unidade->id, 'modelo' => 'vitrine',
        ]);
    }

    private function eu()
    {
        return $this->actingAsUser($this->dono, $this->unidade);
    }

    private function comprar(array $extra = [])
    {
        $this->post('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 2]);

        return $this->post('/loja/minha-loja/finalizar', array_merge([
            'nome' => 'Maria Cliente', 'telefone' => '(86) 98888-1111', 'entrega' => 'retirada', 'pagamento' => 'combinar',
        ], $extra));
    }

    /* ───────────── chave da plataforma e do lojista ───────────── */

    public function test_sem_liberar_a_loja_nao_existe_nem_para_o_dono(): void
    {
        $this->loja->update(['ativo' => false]);

        $this->get('/loja/minha-loja')->assertNotFound();
        $this->eu()->get(route('app.loja-online.index'))->assertNotFound();
        $this->eu()->get(route('app.produtos.edit', $this->produto))->assertOk()->assertDontSee('name="loja_online"', false);
    }

    public function test_fora_do_ar_o_publico_nao_ve_e_o_lojista_ve_a_previa(): void
    {
        $this->loja->update(['publicada' => false]);

        $this->get('/loja/minha-loja')->assertStatus(503)->assertDontSee('Camisa Azul');
        $this->eu()->get('/loja/minha-loja')->assertOk()->assertSee('ainda não está no ar')->assertSee('Camisa Azul');

        [$outra, $unidadeOutra] = $this->createTenant('2');
        $estranho = $this->createUser($outra, $unidadeOutra, 'dono');
        $this->actingAsUser($estranho, $unidadeOutra)->get('/loja/minha-loja')->assertStatus(503);
    }

    public function test_so_o_admin_da_plataforma_libera_e_o_endereco_e_unico(): void
    {
        $this->loja->delete();
        $this->eu()->post(route('admin.empresas.loja-online.toggle', $this->empresa));
        $this->assertSame(0, LojaOnline::count());

        $admin = $this->createUser($this->empresa, $this->unidade, 'admin');
        $admin->forceFill(['is_admin' => true, 'empresa_id' => null])->save();
        $this->actingAs($admin)->post(route('admin.empresas.loja-online.toggle', $this->empresa))->assertRedirect();

        $loja = LojaOnline::firstOrFail();
        $this->assertTrue($loja->ativo);
        $this->assertFalse($loja->publicada);
        $this->assertSame($this->unidade->id, $loja->unidade_id);

        $this->actingAs($admin)->post(route('admin.empresas.loja-online.salvar', $this->empresa), ['slug' => 'admin', 'dominio' => '']);
        $this->assertNotSame('admin', $loja->fresh()->slug);
        $this->actingAs($admin)->post(route('admin.empresas.loja-online.salvar', $this->empresa), ['slug' => 'nova', 'dominio' => 'HTTPS://Loja.Cliente.com.br/']);
        $this->assertSame(['nova', 'loja.cliente.com.br'], [$loja->fresh()->slug, $loja->fresh()->dominio]);
    }

    /* ───────────── vitrine ───────────── */

    public function test_vitrine_mostra_so_produto_marcado_ativo_e_da_empresa(): void
    {
        $fora = $this->createProduto($this->empresa, ['descricao' => 'Produto Fora da Loja']);
        $inativo = $this->createProduto($this->empresa, ['descricao' => 'Produto Inativo']);
        $inativo->forceFill(['loja_online' => true, 'status' => 'inativo'])->save();
        [$outra] = $this->createTenant('2');
        $alheio = $this->createProduto($outra, ['descricao' => 'Produto de Outra Empresa']);
        $alheio->forceFill(['loja_online' => true])->save();

        $this->get('/loja/minha-loja/produtos')->assertOk()->assertSee('Camisa Azul')
            ->assertDontSee('Produto Fora da Loja')->assertDontSee('Produto Inativo')->assertDontSee('Produto de Outra Empresa');
        foreach ([$fora, $inativo, $alheio] as $p) {
            $this->get("/loja/minha-loja/p/{$p->id}-x")->assertNotFound();
        }
        $this->get("/loja/minha-loja/p/{$this->produto->id}")->assertRedirect("/loja/minha-loja/p/{$this->produto->id}-camisa-azul");
        $this->get("/loja/minha-loja/p/{$this->produto->id}-camisa-azul")->assertOk()->assertSee('R$ 100,00')->assertSee('Adicionar ao carrinho');
    }

    public function test_estoque_separado_decide_o_que_esta_disponivel(): void
    {
        $ecommerce = Estoque::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'E-commerce', 'codigo' => 'ECOM',
            'permite_venda' => true, 'is_padrao' => false, 'status' => 'ativo',
        ]);
        $this->loja->update(['estoque_id' => $ecommerce->id]);

        $this->get("/loja/minha-loja/p/{$this->produto->id}-camisa-azul")->assertOk()->assertSee('Esgotado no momento');
        $this->postJson('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id])->assertStatus(422)->assertJson(['ok' => false]);

        $this->loja->update(['config' => ['vender_sem_estoque' => true]]);
        $this->postJson('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id])->assertOk()->assertJson(['ok' => true, 'total' => 1]);
    }

    /* ───────────── compra → pedido do ERP ───────────── */

    public function test_fechar_a_compra_cria_pedido_do_erp_no_canal_online_sem_baixar_estoque(): void
    {
        $resposta = $this->comprar(['observacoes' => 'Embalar <b>bem</b>']);

        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();
        $resposta->assertRedirect('/loja/minha-loja/pedido/' . $pedido->loja_token);

        $this->assertSame(StatusPedido::Rascunho, $pedido->status);
        $this->assertSame('online', $pedido->canal->value);
        $this->assertSame($this->unidade->id, $pedido->unidade_id);
        $this->assertNull($pedido->estoque_id);
        $this->assertEquals(200.00, (float) $pedido->total);
        $this->assertSame('retirada', $pedido->metodo_entrega);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $pedido->loja_token);
        $this->assertSame(1, $pedido->itens()->count());
        $this->assertSame('Maria Cliente', $pedido->cliente->nome_razao_social);
        $this->assertSame('86988881111', $pedido->cliente->telefone);
        $this->assertStringContainsString('loja online', $pedido->observacoes_internas);
        $this->assertEquals(5.0, SaldoEstoque::noEstoque($this->principal, $this->produto->id)); // quem baixa é o faturamento

        $this->get('/loja/minha-loja/pedido/' . $pedido->loja_token)->assertOk()
            ->assertSee('Pedido #' . $pedido->numero)->assertSee('Embalar &lt;b&gt;bem&lt;/b&gt;', false)->assertDontSee('<b>bem</b>', false);
        $this->get('/loja/minha-loja/pedido/' . str_repeat('a', 32))->assertNotFound();
        $this->get('/loja/minha-loja/carrinho')->assertSee('Seu carrinho está vazio');
    }

    public function test_cliente_que_volta_e_o_mesmo_cadastro_e_a_numeracao_segue_a_da_empresa(): void
    {
        $this->comprar();
        $this->comprar(['nome' => 'Maria C.', 'telefone' => '86 8888-1111']); // sem o 9º dígito

        $pedidos = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->orderBy('id')->get();
        $this->assertCount(2, $pedidos);
        $this->assertSame($pedidos[0]->cliente_id, $pedidos[1]->cliente_id);
        $this->assertSame($pedidos[0]->numero + 1, $pedidos[1]->numero);
    }

    public function test_fechamento_valida_dados_endereco_e_quantidade(): void
    {
        $this->comprar(['nome' => '', 'telefone' => '123'])->assertSessionHasErrors(['nome', 'telefone']);
        $this->comprar(['entrega' => 'entrega'])->assertSessionHasErrors(['cep', 'logradouro', 'numero', 'bairro']);
        $this->comprar(['pagamento' => 'pix'])->assertSessionHasErrors(['pagamento']); // sem provedor de PIX ligado
        $this->assertSame(0, Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->count());

        // quantidade acima do saldo é reduzida no carrinho, com aviso, antes de fechar
        $this->post('/loja/minha-loja/carrinho/atualizar', ['produto_id' => $this->produto->id, 'quantidade' => 50]);
        $this->get('/loja/minha-loja/carrinho')->assertSee('ajustamos a quantidade');

        $this->empresa->update(['exige_documento_cadastro' => true]);
        $this->comprar()->assertSessionHasErrors(['cpf_cnpj']);
    }

    /* ───────────── pagamento ───────────── */

    public function test_pix_do_asaas_gera_qr_na_tela_e_o_webhook_confirma_o_pedido(): void
    {
        EmpresaGateway::create(['empresa_id' => $this->empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_ASAAS, 'ativo' => true,
            'client_secret' => '$aact_teste', 'config' => ['sandbox' => true, 'webhook_token' => 'segredo']]);
        Http::fake([
            '*/customers*' => Http::response(['id' => 'cus_1', 'data' => []]),
            '*/pixQrCode' => Http::response(['payload' => '00020126COPIA-E-COLA', 'encodedImage' => base64_encode('png')]),
            '*/payments' => Http::response(['id' => 'pay_teste_1']),
        ]);

        $meios = PagamentoLoja::meios($this->loja->fresh());
        $this->assertSame(['asaas', 'asaas'], [$meios['pix'], $meios['cartao']]);

        $this->comprar(['pagamento' => 'pix']);
        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();

        $this->get('/loja/minha-loja/pedido/' . $pedido->loja_token)->assertOk()
            ->assertSee('00020126COPIA-E-COLA')->assertSee('data:image/png;base64,' . base64_encode('png'), false);
        $cobranca = PedidoCobranca::where('pedido_id', $pedido->id)->firstOrFail();
        $this->assertSame(['asaas', 'pay_teste_1', 'ATIVA'], [$cobranca->provedor, $cobranca->txid, $cobranca->status]);

        // a rotina do Sicredi não toca na cobrança do Asaas
        $this->artisan('agente:pix-sincronizar')->assertSuccessful();
        $this->assertSame('ATIVA', $cobranca->fresh()->status);

        $this->postJson('/api/integracao/v1/webhooks/asaas', ['event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_teste_1', 'value' => 200, 'billingType' => 'PIX', 'externalReference' => 'pedido:' . $pedido->id],
        ], ['asaas-access-token' => 'segredo'])->assertOk();

        $pedido->refresh();
        $this->assertSame(StatusPedido::Confirmado, $pedido->status);
        $this->assertSame('pix', $pedido->condicao_pagamento);
        $this->assertStringContainsString('PIX PAGO via Asaas', $pedido->observacoes_internas);
        $this->assertTrue($cobranca->fresh()->paga());
        $this->getJson('/loja/minha-loja/pedido/' . $pedido->loja_token . '/situacao')->assertJson(['situacao' => 'pago', 'pago' => true]);
    }

    public function test_sem_provedor_ligado_combinar_com_a_loja_vale_sempre(): void
    {
        $this->loja->update(['config' => ['combinar' => false]]);

        $meios = PagamentoLoja::meios($this->loja->fresh());
        $this->assertNull($meios['pix']);
        $this->assertNull($meios['cartao']);
        $this->assertTrue($meios['combinar']);
    }

    /* ───────────── painel do lojista, produtos e editor ───────────── */

    public function test_painel_marca_produtos_e_respeita_perfil_e_empresa(): void
    {
        $outro = $this->createProduto($this->empresa, ['descricao' => 'Segundo Produto']);
        [$empresaAlheia] = $this->createTenant('2');
        $alheio = $this->createProduto($empresaAlheia);

        $this->eu()->post(route('app.loja-online.produtos.marcar'), ['acao' => 'destacar', 'alcance' => 'marcados', 'ids' => [$outro->id, $alheio->id]]);
        $this->assertTrue($outro->fresh()->loja_online && $outro->fresh()->loja_online_destaque);
        $this->assertFalse((bool) $alheio->fresh()->loja_online);

        $this->eu()->post(route('app.loja-online.produtos.marcar'), ['acao' => 'sair', 'alcance' => 'todos']);
        $this->assertFalse((bool) $this->produto->fresh()->loja_online);
        $this->assertFalse((bool) $outro->fresh()->loja_online_destaque);

        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');
        $this->actingAsUser($vendedor, $this->unidade)->get(route('app.loja-online.index'))->assertForbidden();
        $this->actingAsUser($vendedor, $this->unidade)->post(route('app.loja-online.produtos.marcar'), ['acao' => 'entrar', 'alcance' => 'todos'])->assertForbidden();
        $this->assertFalse((bool) $this->produto->fresh()->loja_online);
    }

    public function test_por_no_ar_exige_loja_de_origem_e_produto_marcado(): void
    {
        $this->loja->update(['publicada' => false]);
        $this->produto->forceFill(['loja_online' => false])->save();

        $this->eu()->post(route('app.loja-online.publicar'), ['publicar' => 1]);
        $this->assertFalse($this->loja->fresh()->publicada);

        $this->produto->fresh()->forceFill(['loja_online' => true])->save();
        $this->eu()->post(route('app.loja-online.publicar'), ['publicar' => 1]);
        $this->assertTrue($this->loja->fresh()->publicada);
    }

    public function test_cadastro_do_produto_guarda_mais_fotos_e_o_interruptor(): void
    {
        Storage::fake('public');
        $dados = array_merge($this->produto->only(['descricao', 'unidade_medida', 'preco_custo', 'preco_venda', 'status']), ['loja_online_bloco' => 1]);

        $this->eu()->put(route('app.produtos.update', $this->produto), array_merge($dados, [
            'loja_online' => 1, 'loja_online_destaque' => 1,
            'fotos_extras' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ]))->assertSessionHasNoErrors();

        $produto = $this->produto->fresh();
        $this->assertCount(2, $produto->fotos);
        $this->assertTrue($produto->loja_online && $produto->loja_online_destaque);
        Storage::disk('public')->assertExists($produto->fotos[0]->caminho);

        // tirar uma foto e desligar o interruptor
        $primeira = $produto->fotos[0];
        $this->eu()->put(route('app.produtos.update', $this->produto), array_merge($dados, ['remover_fotos' => [$primeira->id]]))->assertSessionHasNoErrors();
        $this->assertSame(1, ProdutoFoto::where('produto_id', $this->produto->id)->count());
        Storage::disk('public')->assertMissing($primeira->caminho);
        $this->assertFalse((bool) $this->produto->fresh()->loja_online);

        // tela antiga (sem o bloco) não desmarca
        $this->produto->fresh()->forceFill(['loja_online' => true])->save();
        unset($dados['loja_online_bloco']);
        $this->eu()->put(route('app.produtos.update', $this->produto), $dados)->assertSessionHasNoErrors();
        $this->assertTrue((bool) $this->produto->fresh()->loja_online);

        // arquivo que não é imagem é recusado
        $this->eu()->put(route('app.produtos.update', $this->produto), array_merge($dados, ['fotos_extras' => [UploadedFile::fake()->create('x.pdf', 10)]]))
            ->assertSessionHasErrors(['fotos_extras.0']);
    }

    public function test_editor_higieniza_o_rascunho_e_so_publica_quando_mandar(): void
    {
        $this->eu()->postJson(route('app.loja-online.editor.rascunho'), [
            'modelo' => 'feira',
            'tema' => ['cor_primaria' => '#AA0011', 'cor_fundo' => 'vermelho', 'logo' => 'http://fora/x.png', 'raio' => '999'],
            'blocos' => [
                ['id' => 'b1', 'tipo' => 'banner', 'titulo' => 'Banner Novo <script>x</script>', 'botao_link' => 'javascript:alert(1)', 'imagem' => '../../etc/passwd', 'estilo' => 'cheio'],
                ['id' => 'b2', 'tipo' => 'inventado', 'titulo' => 'some'],
                ['id' => 'b3', 'tipo' => 'vitrine', 'titulo' => 'Fileira', 'origem' => 'categoria', 'categoria_id' => 987654],
            ],
        ])->assertOk();

        $r = $this->loja->fresh()->rascunhoEfetivo();
        $this->assertSame(['banner', 'vitrine'], array_column($r['blocos'], 'tipo'));
        $this->assertSame(['', ''], [$r['blocos'][0]['botao_link'], $r['blocos'][0]['imagem']]);
        $this->assertNull($r['blocos'][1]['categoria_id']);
        $this->assertSame('#aa0011', $r['tema']['cor_primaria']);
        $this->assertSame(ModelosLoja::tema('feira')['cor_fundo'], $r['tema']['cor_fundo']);
        $this->assertSame('', $r['tema']['logo']);
        $this->assertSame('20', $r['tema']['raio']);

        // o público segue vendo o publicado; o lojista vê a prévia, com o texto escapado
        auth()->logout();
        $this->get('/loja/minha-loja?rascunho=1')->assertOk()->assertDontSee('Banner Novo');
        $this->eu()->get('/loja/minha-loja?rascunho=1')->assertOk()->assertSee('Banner Novo &lt;script&gt;x&lt;/script&gt;', false)->assertDontSee('<script>x</script>', false);

        $this->eu()->postJson(route('app.loja-online.editor.publicar'))->assertOk();
        $loja = $this->loja->fresh();
        $this->assertSame('feira', $loja->modelo);
        $this->assertSame(['cor_primaria' => '#aa0011'], $loja->tema);
        $this->assertNull($loja->rascunho);
        auth()->logout();
        $this->get('/loja/minha-loja')->assertOk()->assertSee('--marca:#aa0011', false);
    }

    public function test_os_modelos_tem_texto_legivel_sobre_a_cor_da_marca_e_da_oferta(): void
    {
        $this->assertCount(6, ModelosLoja::todos());
        foreach (ModelosLoja::todos() as $chave => $modelo) {
            $t = $modelo['tema'];
            foreach (['cor_primaria', 'cor_destaque'] as $cor) {
                $this->assertGreaterThanOrEqual(4.5, TemaCss::contraste($t[$cor], TemaCss::sobre($t[$cor])), "{$chave}: texto sobre {$cor}");
            }
            $this->assertGreaterThanOrEqual(7, TemaCss::contraste($t['cor_texto'], $t['cor_fundo']), "{$chave}: texto sobre o fundo");
            $this->assertGreaterThanOrEqual(4.5, TemaCss::contraste($t['cor_cabecalho_texto'], $t['cor_cabecalho']), "{$chave}: cabeçalho");
            $this->assertGreaterThanOrEqual(4.5, TemaCss::contraste($t['cor_rodape_texto'], $t['cor_rodape']), "{$chave}: rodapé");
            $this->assertGreaterThanOrEqual(4.5, TemaCss::contraste(TemaCss::tintaDaMarca($t), $t['cor_fundo']), "{$chave}: link sobre o fundo");
            $this->assertNotEmpty(ModelosLoja::blocos($chave));
            $this->assertSame(ModelosLoja::blocos($chave), ModelosLoja::limparBlocos(ModelosLoja::blocos($chave)), "{$chave}: os blocos do modelo passam pela própria higiene");
        }
    }

    /* ───────────── subdomínio e domínio próprio ───────────── */

    public function test_subdominio_e_dominio_proprio_abrem_a_loja_sem_o_prefixo(): void
    {
        config(['loja_online.dominio_base' => 'lojas.teste']);
        $this->loja->update(['dominio' => 'loja.cliente.com.br']);

        foreach (['http://minha-loja.lojas.teste', 'http://loja.cliente.com.br', 'http://www.loja.cliente.com.br'] as $host) {
            $this->get($host . '/')->assertOk()->assertSee('Minha Loja')->assertSee('href="/produtos"', false)->assertDontSee('/loja/minha-loja/', false);
            $this->get($host . '/produtos')->assertOk()->assertSee('Camisa Azul');
        }
        $this->get('http://loja.cliente.com.br/login')->assertNotFound();       // no domínio do cliente não existe o login do ERP
        $this->get('http://nao-existe.lojas.teste/')->assertDontSee('Minha Loja'); // host sem loja cai no site do ERP
        $this->get('/login')->assertOk()->assertSee('name="email"', false);      // host do ERP intacto
    }

    /* ───────────── 2ª rodada: promoções, e-mails, políticas, cookies, rastreamento ───────────── */

    public function test_cupom_desconta_no_pedido_conta_o_uso_e_respeita_minimo_e_limite(): void
    {
        $this->eu()->post(route('app.loja-online.cupons.criar'), ['codigo' => 'dez off!', 'tipo' => 'percentual', 'valor' => '10', 'minimo' => '150', 'limite_usos' => 1])->assertSessionHasNoErrors();
        $cupom = \App\Models\LojaCupom::firstOrFail();
        $this->assertSame(['DEZOFF', 1], [$cupom->codigo, $cupom->empresa_id === $this->empresa->id ? 1 : 0]);
        $this->eu()->post(route('app.loja-online.cupons.criar'), ['codigo' => 'DEZOFF', 'tipo' => 'valor', 'valor' => '5'])->assertSessionHasErrors(['codigo']);
        $this->eu()->post(route('app.loja-online.cupons.criar'), ['codigo' => 'CEM', 'tipo' => 'percentual', 'valor' => '150'])->assertSessionHasErrors(['valor']);
        auth()->logout();

        $this->post('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 1]);   // R$ 100
        $this->post('/loja/minha-loja/carrinho/cupom', ['codigo' => 'dezoff'])->assertSessionHas('loja_erro');           // mínimo R$ 150
        $this->post('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 1]);   // R$ 200
        $this->post('/loja/minha-loja/carrinho/cupom', ['codigo' => 'dezoff'])->assertSessionHas('loja_ok');
        $this->get('/loja/minha-loja/carrinho')->assertSee('class="lj-cupom-aplicado"', false)->assertSee('R$ 180,00');

        $this->post('/loja/minha-loja/finalizar', ['nome' => 'Maria Cliente', 'telefone' => '(86) 98888-1111', 'entrega' => 'retirada', 'pagamento' => 'combinar']);
        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();
        $this->assertEquals([200.00, 20.00, 180.00, 'DEZOFF'], [(float) $pedido->subtotal, (float) $pedido->desconto_valor, (float) $pedido->total, $pedido->loja_cupom]);
        $this->assertSame(1, $cupom->fresh()->usos);

        // limite de 1 uso: não aplica de novo
        $this->post('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 2]);
        $this->post('/loja/minha-loja/carrinho/cupom', ['codigo' => 'DEZOFF'])->assertSessionHas('loja_erro');

        // dono exclui o próprio cupom; o de outra empresa não
        $this->eu()->delete(route('app.loja-online.cupons.excluir', $cupom->id))->assertRedirect();
        $this->assertSame(0, \App\Models\LojaCupom::count());
        [$outra] = $this->createTenant('2');
        $alheio = \App\Models\LojaCupom::create(['empresa_id' => $outra->id, 'codigo' => 'ALHEIO', 'tipo' => 'valor', 'valor' => 5, 'ativo' => true]);
        $this->eu()->delete(route('app.loja-online.cupons.excluir', $alheio->id))->assertNotFound();
    }

    public function test_emails_do_pedido_saem_ao_fechar_e_quando_a_situacao_muda(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->loja->update(['config' => ['email_pedidos' => 'pedidos@loja.test']]);
        $quantos = fn (string $tipo) => \Illuminate\Support\Facades\Mail::queued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === $tipo)->count();

        $this->comprar(['email' => 'comprador@teste.test']);
        $pedido = Pedido::withoutGlobalScopes()->whereNotNull('loja_token')->firstOrFail();
        $this->assertSame([1, 1], [$quantos('recebido'), $quantos('loja_novo')]);
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === 'recebido' && $m->hasTo('comprador@teste.test'));
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\PedidoLoja::class, fn ($m) => $m->tipo === 'loja_novo' && $m->hasTo('pedidos@loja.test'));

        $pedido->update(['status' => StatusPedido::Confirmado]);
        $pedido->update(['observacoes_internas' => 'mexida que não muda a situação']);
        $pedido->update(['status' => StatusPedido::Faturado]);
        $this->assertSame([1, 1], [$quantos('confirmado'), $quantos('faturado')]);

        $html = (new \App\Mail\PedidoLoja($pedido->id, $this->loja->id, 'recebido'))->render();
        $this->assertStringContainsString('/loja/minha-loja/pedido/' . $pedido->loja_token, $html);
        $this->assertStringContainsString('Camisa Azul', $html);

        // lojista desliga o andamento: cancelar não avisa
        $this->loja->update(['config' => ['email_cliente_andamento' => false]]);
        $pedido->update(['status' => StatusPedido::Cancelado]);
        $this->assertSame(0, $quantos('cancelado'));
    }

    public function test_politicas_tem_texto_de_partida_e_aceitam_o_texto_da_loja_sem_html(): void
    {
        foreach (['privacidade' => 'Lei Geral de Proteção de Dados', 'trocas' => 'artigo 49', 'entrega' => 'Retirada na loja', 'termos' => 'Estes termos valem'] as $tipo => $trecho) {
            $this->get('/loja/minha-loja/politica/' . $tipo)->assertOk()->assertSee($trecho);
        }
        $this->get('/loja/minha-loja/politica/outra')->assertNotFound();

        $this->eu()->put(route('app.loja-online.secao.salvar', 'politicas'), ['politica_privacidade' => "Nosso jeito:\n\nGuardamos <script>x</script> pouco."])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/loja/minha-loja/politica/privacidade')->assertOk()->assertSee('<h2>Nosso jeito</h2>', false)
            ->assertSee('&lt;script&gt;x&lt;/script&gt;', false)->assertDontSee('<script>x</script>', false)->assertDontSee('Lei Geral');
    }

    public function test_rastreamento_valida_os_ids_e_nao_poe_tag_de_terceiro_no_html(): void
    {
        $rota = route('app.loja-online.secao.salvar', 'rastreamento');
        $this->eu()->put($rota, ['meta_pixel' => 'abc'])->assertSessionHasErrors(['meta_pixel']);
        $this->eu()->put($rota, ['ga4' => 'UA-1'])->assertSessionHasErrors(['ga4']);
        $this->eu()->put($rota, ['google_ads' => '"><script>'])->assertSessionHasErrors(['google_ads']);
        $this->eu()->put($rota, ['meta_pixel' => '1234567890123456', 'ga4' => 'G-AB12CD34EF', 'verificacao_google' => 'abcDEF123456_-xyz'])->assertSessionHasNoErrors();
        $this->assertTrue($this->loja->fresh()->temRastreamento());
        auth()->logout();

        $html = $this->get('/loja/minha-loja')->assertOk()->getContent();
        $this->assertStringContainsString('"meta":"1234567890123456"', $html);
        $this->assertStringContainsString('name="google-site-verification" content="abcDEF123456_-xyz"', $html);
        $this->assertDoesNotMatchRegularExpression('#<script[^>]+src="https://(www\.googletagmanager|connect\.facebook)#', $html); // só carrega depois do aceite
        $this->assertMatchesRegularExpression('#<button[^>]*data-lj-cookies-recusar#', $html);                                    // aviso com escolha é obrigatório
        $this->assertStringContainsString('Meta (Facebook e Instagram)', $this->get('/loja/minha-loja/politica/privacidade')->getContent());

        // valor adulterado direto no banco não chega à página
        $this->loja->update(['config' => ['meta_pixel' => '1"};alert(1);//']]);
        $this->assertStringContainsString('"meta":""', $this->get('/loja/minha-loja')->getContent());
    }

    public function test_popup_redes_e_cadastro_para_novidades(): void
    {
        $promo = route('app.loja-online.secao.salvar', 'promocoes');
        $this->eu()->put($promo, ['popup_ativo' => 1, 'popup_titulo' => '', 'popup_gatilho' => 'tempo'])->assertSessionHasErrors(['popup_titulo']);
        $this->eu()->put($promo, ['popup_ativo' => 1, 'popup_titulo' => 'Oi', 'popup_cupom' => 'NAOHA', 'popup_gatilho' => 'tempo'])->assertSessionHasErrors(['popup_cupom']);
        \App\Models\LojaCupom::create(['empresa_id' => $this->empresa->id, 'codigo' => 'OLA5', 'tipo' => 'valor', 'valor' => 5, 'ativo' => true]);
        $this->eu()->put($promo, ['popup_ativo' => 1, 'popup_capturar' => 1, 'popup_titulo' => 'Ganhe R$ 5', 'popup_cupom' => 'ola5', 'popup_gatilho' => 'entrar',
            'popup_link' => 'javascript:alert(1)', 'frete_gratis_acima' => '150,00'])->assertSessionHasNoErrors();
        $loja = $this->loja->fresh();
        $this->assertSame(['OLA5', '', 150.0], [$loja->opcao('popup_cupom'), $loja->opcao('popup_link'), (float) $loja->opcao('frete_gratis_acima')]);

        $this->eu()->put(route('app.loja-online.configuracoes.salvar'), ['nome' => 'Minha Loja', 'unidade_id' => $this->unidade->id, 'retirada' => 1, 'entrega' => 1,
            'whatsapp' => '(86) 99999-0000', 'instagram' => '@minhaloja', 'tiktok' => '@minhaloja', 'whatsapp_flutuante' => 1])->assertSessionHasNoErrors();
        auth()->logout();

        $html = $this->get('/loja/minha-loja')->assertOk()->getContent();
        $this->assertStringContainsString('data-lj-popup ', $html);
        $this->assertStringContainsString('instagram.com/minhaloja', $html);
        $this->assertStringContainsString('tiktok.com/@minhaloja', $html);
        $this->assertStringContainsString('class="lj-zap"', $html);
        $this->post('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 1]);
        $this->assertStringNotContainsString('data-lj-popup ', $this->get('/loja/minha-loja/finalizar')->getContent()); // nada de popup no fechamento
        $this->get('/loja/minha-loja/carrinho')->assertSee('para o frete grátis');

        $this->postJson('/loja/minha-loja/contato', ['email' => 'a@b.test', 'origem' => 'popup'])->assertStatus(422);              // sem aceite
        $this->postJson('/loja/minha-loja/contato', ['email' => 'A@B.test', 'aceite' => 1, 'origem' => 'popup'])->assertOk()->assertJson(['ok' => true, 'cupom' => 'OLA5']);
        $this->postJson('/loja/minha-loja/contato', ['email' => 'a@b.test', 'aceite' => 1])->assertOk()->assertJson(['cupom' => null]);
        $this->assertSame(1, \App\Models\LojaContato::where('empresa_id', $this->empresa->id)->count());
        $this->assertNotNull(\App\Models\LojaContato::first()->aceitou_em);
    }

    public function test_blocos_novos_passam_pela_higiene_do_editor(): void
    {
        $limpos = ModelosLoja::limparBlocos([
            ['id' => 'a1', 'tipo' => 'video', 'titulo' => 'v', 'video' => 'https://youtu.be/dQw4w9WgXcQ?t=3'],
            ['id' => 'a2', 'tipo' => 'video', 'titulo' => 'v', 'video' => 'https://exemplo.com/x" onload="alert(1)'],
            ['id' => 'a3', 'tipo' => 'oferta', 'titulo' => 'o', 'termina_em' => '2099-01-01T10:00', 'botao_link' => 'data:text/html,x'],
            ['id' => 'a4', 'tipo' => 'oferta', 'titulo' => 'o', 'termina_em' => 'ontem'],
            ['id' => 'a5', 'tipo' => 'faq', 'titulo' => 'f', 'lista' => array_fill(0, 12, ['nome' => str_repeat('p', 300), 'texto' => 'r'])],
        ]);
        $this->assertSame(['dQw4w9WgXcQ', ''], [$limpos[0]['video'], $limpos[1]['video']]);
        $this->assertSame(['2099-01-01T10:00', '', ''], [$limpos[2]['termina_em'], $limpos[2]['botao_link'], $limpos[3]['termina_em']]);
        $this->assertCount(8, $limpos[4]['lista']);
        $this->assertSame(120, mb_strlen($limpos[4]['lista'][0]['nome']));
        $this->assertSame(['completo', 'correndo'], [ModelosLoja::tema('vitrine')['movimento'], ModelosLoja::limparTema(['faixa' => 'correndo', 'movimento' => 'girando'])['faixa']]);
        $this->assertArrayNotHasKey('movimento', ModelosLoja::limparTema(['movimento' => 'girando']));
    }

    /* ───────────── 3ª rodada: vitrine no nível de tema pronto ───────────── */

    public function test_busca_com_sugestoes_sacola_lateral_e_vistos_so_mostram_a_vitrine(): void
    {
        $fora = $this->createProduto($this->empresa, ['preco_venda' => 30, 'descricao' => 'Camisa Escondida']);

        $j = $this->getJson('/loja/minha-loja/buscar?q=camisa')->assertOk()->json('produtos');
        $this->assertSame(['Camisa Azul'], array_column($j, 'nome'));
        $this->assertStringContainsString('/loja/minha-loja/p/' . $this->produto->id, $j[0]['url']);
        $this->assertSame([], $this->getJson('/loja/minha-loja/buscar?q=c')->json('produtos'));

        $this->assertSame(0, $this->getJson('/loja/minha-loja/carrinho/resumo')->assertOk()->json('quantidade'));
        $item = $this->postJson('/loja/minha-loja/carrinho/adicionar', ['produto_id' => $this->produto->id, 'quantidade' => 2])->assertOk()->json('item');
        $this->assertSame('Camisa Azul', $item['nome']);
        $r = $this->getJson('/loja/minha-loja/carrinho/resumo')->json();
        $this->assertSame([2, 200.0, 1], [$r['quantidade'], (float) $r['subtotal'], count($r['itens'])]);

        $html = $this->get('/loja/minha-loja/vistos?ids=' . $fora->id . ',' . $this->produto->id . ',abc')->assertOk()->getContent();
        $this->assertStringContainsString('Camisa Azul', $html);
        $this->assertStringNotContainsString('Camisa Escondida', $html);
    }

    public function test_filtro_de_preco_e_parcela_mostrada_so_com_cartao_ligado(): void
    {
        $barato = $this->createProduto($this->empresa, ['preco_venda' => 40, 'descricao' => 'Meia Lisa']);
        $barato->forceFill(['loja_online' => true])->save();

        $this->get('/loja/minha-loja/produtos?preco_max=50')->assertOk()->assertSee('Meia Lisa')->assertDontSee('Camisa Azul');
        $this->get('/loja/minha-loja/produtos?preco_min=50')->assertOk()->assertSee('Camisa Azul')->assertDontSee('Meia Lisa');
        $this->get('/loja/minha-loja/produtos?preco_min=abc&preco_max=-1')->assertOk()->assertSee('Camisa Azul')->assertSee('Meia Lisa');

        $this->loja->update(['config' => ['parcelas_max' => 6, 'parcela_minima' => 20]]);
        $catalogo = new \App\Services\LojaOnline\CatalogoLoja($this->loja->fresh());
        $this->assertNull($catalogo->parcelas(100.0), 'sem cartão configurado a vitrine não promete parcela');

        EmpresaGateway::create(['empresa_id' => $this->empresa->id, 'provedor' => EmpresaGateway::PROVEDOR_INFINITEPAY, 'ativo' => true, 'config' => ['handle' => 'lojateste']]);
        $catalogo = new \App\Services\LojaOnline\CatalogoLoja($this->loja->fresh());
        $this->assertSame(['vezes' => 5, 'valor' => 20.0, 'sem_juros' => false], $catalogo->parcelas(100.0));
        $this->assertNull($catalogo->parcelas(39.0));
    }

    public function test_painel_grava_parcelamento_e_o_editor_higieniza_carrossel_e_banners(): void
    {
        $this->eu()->put('/app/loja-online/secao/promocoes', ['frete_gratis_acima' => '0', 'popup_gatilho' => 'tempo',
            'parcelas_max' => '10', 'parcela_minima' => '15,50', 'parcelas_sem_juros' => '1', 'selo_novo_dias' => '7'])->assertRedirect();
        $c = $this->loja->fresh();
        $this->assertSame([10, 15.5, true, 7], [$c->opcao('parcelas_max'), (float) $c->opcao('parcela_minima'), $c->opcao('parcelas_sem_juros'), $c->opcao('selo_novo_dias')]);
        $this->eu()->put('/app/loja-online/secao/promocoes', ['frete_gratis_acima' => '0', 'popup_gatilho' => 'tempo', 'parcelas_max' => '40'])->assertSessionHasErrors('parcelas_max');

        $limpos = ModelosLoja::limparBlocos([
            ['id' => 'c1', 'tipo' => 'carrossel', 'altura' => 'gigante', 'lista' => array_fill(0, 9, ['imagem' => 'http://x/y.jpg', 'titulo' => 'T', 'botao_link' => 'javascript:alert(1)', 'tom' => 'neon', 'lado' => 'cima'])],
            ['id' => 'c2', 'tipo' => 'banners', 'arranjo' => 'torto', 'lista' => [['imagem' => 'loja-online/1/abc.jpg', 'titulo' => 'B', 'botao_link' => 'produtos?promocao=1']]],
            ['id' => 'c3', 'tipo' => 'vitrine', 'titulo' => 'V', 'origem' => 'todos', 'formato' => 'espiral'],
            ['id' => 'c4', 'tipo' => 'categorias', 'titulo' => 'C', 'estilo' => 'circulos'],
        ]);
        $this->assertCount(5, $limpos[0]['lista']);
        $this->assertSame(['media', '', '', 'claro', 'esquerda'], [$limpos[0]['altura'], $limpos[0]['lista'][0]['imagem'], $limpos[0]['lista'][0]['botao_link'], $limpos[0]['lista'][0]['tom'], $limpos[0]['lista'][0]['lado']]);
        $this->assertSame(['iguais', 'loja-online/1/abc.jpg', 'produtos?promocao=1'], [$limpos[1]['arranjo'], $limpos[1]['lista'][0]['imagem'], $limpos[1]['lista'][0]['botao_link']]);
        $this->assertSame(['grade', 'circulos'], [$limpos[2]['formato'], $limpos[3]['estilo']]);
        $this->assertSame('compacto', ModelosLoja::limparTema(['cabecalho' => 'compacto'])['cabecalho']);

        foreach (array_keys(ModelosLoja::todos()) as $modelo) {
            $this->loja->update(['modelo' => $modelo, 'tema' => null, 'blocos' => null]);
            $this->get('/loja/minha-loja')->assertOk()->assertSee('data-lj-gaveta', false)->assertSee('<h1', false);
        }
    }
}
