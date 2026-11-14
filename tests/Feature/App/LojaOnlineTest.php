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
}
