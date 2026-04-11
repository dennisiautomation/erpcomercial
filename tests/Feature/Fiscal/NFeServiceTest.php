<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\StatusVenda;
use App\Enums\TipoNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFeServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;
    private FocusNFeClient $client;
    private NFeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->config = ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'test-token-fake-123',
            'serie_nfe'           => 1,
            'serie_nfce'          => 1,
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'      => 'fiscal',
        ]);

        $this->client  = new FocusNFeClient('test-token-fake-123', 'homologacao');
        $this->service = new NFeService($this->client);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function createVendaComItens(): Venda
    {
        $cliente = $this->createCliente($this->empresa, [
            'tipo_pessoa'       => 'pj',
            'cpf_cnpj'          => '12345678000199',
            'nome_razao_social' => 'Cliente PJ Teste',
            'email'             => 'cliente@teste.com',
        ]);

        $venda = Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'cliente_id'      => $cliente->id,
            'numero'          => 1,
            'subtotal'        => 500.00,
            'desconto_valor'  => 0,
            'total'           => 500.00,
            'forma_pagamento' => 'pix',
            'troco'           => 0,
            'status'          => StatusVenda::Concluida,
            'tipo'            => 'balcao',
        ]);

        $produto = $this->createProduto($this->empresa, [
            'ncm'  => '61091000',
            'cfop' => '5102',
        ]);

        VendaItem::create([
            'venda_id'       => $venda->id,
            'produto_id'     => $produto->id,
            'descricao'      => $produto->descricao,
            'quantidade'     => 5,
            'preco_unitario' => 100.00,
            'desconto_valor' => 0,
            'total'          => 500.00,
        ]);

        return $venda->load(['itens.produto', 'cliente', 'unidade.empresa']);
    }

    private function createNotaAutorizada(): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFe,
            'status'            => StatusNotaFiscal::Autorizada,
            'focus_ref'         => 'nfe-test-456',
            'focus_status'      => 'autorizado',
            'chave_acesso'      => 'NFe35190607504505000132550010000000011987654321',
            'numero'            => '100',
            'serie'             => 1,
            'valor_total'       => 500.00,
            'natureza_operacao' => 'Venda de Mercadoria',
            'ambiente'          => 'homologacao',
            'emitida_em'        => now(),
        ]);
    }

    private function createNotaPendente(): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFe,
            'status'            => StatusNotaFiscal::Pendente,
            'focus_ref'         => 'nfe-test-789',
            'focus_status'      => 'processando_autorizacao',
            'valor_total'       => 500.00,
            'natureza_operacao' => 'Venda de Mercadoria',
            'ambiente'          => 'homologacao',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_nfe_emissao_returns_nota_fiscal_pendente(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe*' => Http::response([
                'status'         => 'processando_autorizacao',
                'status_sefaz'   => '103',
                'mensagem_sefaz' => 'Lote recebido com sucesso',
            ], 202),
        ]);

        $venda = $this->createVendaComItens();

        $nota = $this->service->emitir($venda, $this->config);

        $this->assertInstanceOf(NotaFiscal::class, $nota);
        $this->assertTrue($nota->exists);
        $this->assertEquals(StatusNotaFiscal::Pendente, $nota->status);
        $this->assertEquals(TipoNotaFiscal::NFe, $nota->tipo);
        $this->assertEquals('processando_autorizacao', $nota->focus_status);
        $this->assertNull($nota->chave_acesso);
        $this->assertNull($nota->emitida_em);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Pendente->value,
            'tipo'   => TipoNotaFiscal::NFe->value,
        ]);
    }

    public function test_nfe_consulta_updates_status_to_autorizada(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*' => Http::response([
                'status'                  => 'autorizado',
                'status_sefaz'            => '100',
                'mensagem_sefaz'          => 'Autorizado o uso da NF-e',
                'chave_nfe'               => 'NFe35190607504505000132550010000000011987654321',
                'numero'                  => '100',
                'serie'                   => '1',
                'caminho_xml_nota_fiscal' => '/arquivos/nfe-xml.xml',
                'caminho_danfe'           => '/arquivos/nfe-danfe.pdf',
            ], 200),
        ]);

        $nota = $this->createNotaPendente();

        $result = $this->service->consultar($nota);

        $this->assertEquals(StatusNotaFiscal::Autorizada, $result->status);
        $this->assertEquals('NFe35190607504505000132550010000000011987654321', $result->chave_acesso);
        $this->assertEquals('100', $result->numero);
        $this->assertNotNull($result->emitida_em);
        $this->assertEquals('/arquivos/nfe-xml.xml', $result->xml_url);
        $this->assertEquals('/arquivos/nfe-danfe.pdf', $result->danfe_url);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Autorizada->value,
        ]);
    }

    public function test_nfe_cancelamento_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*' => Http::response([
                'status'    => 'cancelado',
                'protocolo' => '135200000000002',
            ], 200),
        ]);

        $nota = $this->createNotaAutorizada();

        $result = $this->service->cancelar($nota, 'Erro na venda, cancelamento solicitado pelo cliente');

        $this->assertEquals(StatusNotaFiscal::Cancelada, $result->status);
        $this->assertEquals('Erro na venda, cancelamento solicitado pelo cliente', $result->cancelamento_motivo);
        $this->assertEquals('135200000000002', $result->cancelamento_protocolo);
        $this->assertNotNull($result->cancelada_em);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Cancelada->value,
        ]);
    }

    public function test_nfe_carta_correcao_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/carta_correcao' => Http::response([
                'status'                  => 'autorizado',
                'status_sefaz'            => '135',
                'mensagem_sefaz'          => 'Evento registrado e vinculado a NF-e',
                'caminho_xml_carta_correcao' => '/arquivos/cce.xml',
                'numero_carta_correcao'   => '1',
            ], 200),
        ]);

        $nota = $this->createNotaAutorizada();

        $result = $this->service->cartaCorrecao($nota, 'Correcao do endereco de entrega');

        $this->assertTrue($result['success']);
        $this->assertEquals('autorizado', $result['data']['status']);
        $this->assertEquals('1', $result['data']['numero_carta_correcao']);
    }

    public function test_nfe_inutilizacao_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/inutilizacao*' => Http::response([
                'status'         => 'autorizado',
                'status_sefaz'   => '102',
                'mensagem_sefaz' => 'Inutilizacao de numero homologado',
            ], 200),
        ]);

        $result = $this->service->inutilizar($this->config, 1, 10, 20, 'Pulo de numeracao');

        $this->assertTrue($result['success']);
        $this->assertEquals('autorizado', $result['data']['status']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['modelo'] === '55'
                && $body['serie'] === '1'
                && $body['numero_inicial'] === '10'
                && $body['numero_final'] === '20'
                && $body['justificativa'] === 'Pulo de numeracao';
        });
    }
}
