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
use App\Services\FocusNFe\NFCeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFCeServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;
    private FocusNFeClient $client;
    private NFCeService $service;

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
            'csc_nfce'            => 'CSC-TESTE-000001',
            'csc_id_nfce'         => '1',
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'      => 'fiscal',
        ]);

        $this->client  = new FocusNFeClient('test-token-fake-123', 'homologacao');
        $this->service = new NFCeService($this->client);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function createVendaComItens(int $qtdItens = 2): Venda
    {
        $venda = Venda::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'numero'          => 1,
            'subtotal'        => 200.00,
            'desconto_valor'  => 0,
            'total'           => 200.00,
            'forma_pagamento' => 'dinheiro',
            'troco'           => 0,
            'status'          => StatusVenda::Concluida,
            'tipo'            => 'pdv',
        ]);

        for ($i = 1; $i <= $qtdItens; $i++) {
            $produto = $this->createProduto($this->empresa, [
                'ncm'            => '61091000',
                'cfop'           => '5102',
                'codigo_barras'  => '789000000000' . $i,
            ]);

            VendaItem::create([
                'venda_id'        => $venda->id,
                'produto_id'      => $produto->id,
                'descricao'       => $produto->descricao,
                'quantidade'      => 1,
                'preco_unitario'  => 100.00,
                'desconto_valor'  => 0,
                'total'           => 100.00,
            ]);
        }

        return $venda->load(['itens.produto', 'unidade.empresa']);
    }

    private function createNotaAutorizada(): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'      => $this->empresa->id,
            'unidade_id'      => $this->unidade->id,
            'tipo'            => TipoNotaFiscal::NFCe,
            'status'          => StatusNotaFiscal::Autorizada,
            'focus_ref'       => 'nfce-test-123',
            'focus_status'    => 'autorizado',
            'chave_acesso'    => 'NFe35190607504505000132650010000000011987654321',
            'numero'          => '1',
            'serie'           => 1,
            'valor_total'     => 200.00,
            'natureza_operacao' => 'Venda ao Consumidor',
            'ambiente'        => 'homologacao',
            'emitida_em'      => now(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_nfce_emissao_returns_nota_fiscal_autorizada(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'                    => 'autorizado',
                'status_sefaz'              => '100',
                'mensagem_sefaz'            => 'Autorizado o uso da NF-e',
                'chave_nfe'                 => 'NFe35190607504505000132650010000000011987654321',
                'numero'                    => '1',
                'serie'                     => '1',
                'caminho_xml_nota_fiscal'   => '/arquivos/xml.xml',
                'caminho_danfe'             => '/arquivos/danfe.pdf',
            ], 200),
        ]);

        $venda = $this->createVendaComItens();

        $nota = $this->service->emitir($venda, $this->config);

        $this->assertInstanceOf(NotaFiscal::class, $nota);
        $this->assertTrue($nota->exists);
        $this->assertEquals(StatusNotaFiscal::Autorizada, $nota->status);
        $this->assertEquals(TipoNotaFiscal::NFCe, $nota->tipo);
        $this->assertEquals('NFe35190607504505000132650010000000011987654321', $nota->chave_acesso);
        $this->assertEquals('1', $nota->numero);
        $this->assertEquals('/arquivos/xml.xml', $nota->xml_url);
        $this->assertEquals('/arquivos/danfe.pdf', $nota->danfe_url);
        $this->assertNotNull($nota->emitida_em);
        $this->assertEquals($venda->id, $nota->venda_id);
        $this->assertEquals($this->empresa->id, $nota->empresa_id);
        $this->assertEquals($this->unidade->id, $nota->unidade_id);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Autorizada->value,
            'tipo'   => TipoNotaFiscal::NFCe->value,
        ]);
    }

    public function test_nfce_emissao_handles_rejection(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'       => 'erro_autorizacao',
                'status_sefaz' => '302',
                'mensagem'     => 'Rejeicao: Irregularidade fiscal do emitente',
            ], 422),
        ]);

        $venda = $this->createVendaComItens();

        $nota = $this->service->emitir($venda, $this->config);

        $this->assertEquals(StatusNotaFiscal::Rejeitada, $nota->status);
        $this->assertNotNull($nota->focus_mensagem);
        $this->assertNull($nota->chave_acesso);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Rejeitada->value,
        ]);
    }

    public function test_nfce_cancelamento_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce/*' => Http::response([
                'status'    => 'cancelado',
                'protocolo' => '135200000000001',
            ], 200),
        ]);

        $nota = $this->createNotaAutorizada();

        $result = $this->service->cancelar($nota, 'Cancelamento por erro de digitacao');

        $this->assertEquals(StatusNotaFiscal::Cancelada, $result->status);
        $this->assertEquals('Cancelamento por erro de digitacao', $result->cancelamento_motivo);
        $this->assertEquals('135200000000001', $result->cancelamento_protocolo);
        $this->assertNotNull($result->cancelada_em);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Cancelada->value,
        ]);
    }

    public function test_nfce_payload_contains_correct_emitente_data(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'   => 'autorizado',
                'chave_nfe' => 'NFe35190607504505000132650010000000011987654321',
                'numero'   => '1',
            ], 200),
        ]);

        $venda = $this->createVendaComItens();

        $this->service->emitir($venda, $this->config);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['cnpj_emitente'] === preg_replace('/\D/', '', $this->unidade->cnpj)
                && $body['nome_emitente'] === $this->empresa->razao_social
                && $body['uf_emitente'] === $this->unidade->uf
                && $body['modelo'] === '65'
                && $body['natureza_operacao'] === 'Venda ao Consumidor';
        });
    }

    public function test_nfce_payload_contains_items_from_venda(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'   => 'autorizado',
                'chave_nfe' => 'NFe35190607504505000132650010000000011987654321',
                'numero'   => '1',
            ], 200),
        ]);

        $venda = $this->createVendaComItens(3);

        $this->service->emitir($venda, $this->config);

        Http::assertSent(function ($request) {
            $body = $request->data();

            if (! isset($body['items']) || count($body['items']) !== 3) {
                return false;
            }

            // Verify first item structure
            $firstItem = $body['items'][0];

            return isset($firstItem['numero_item'])
                && isset($firstItem['codigo_produto'])
                && isset($firstItem['descricao'])
                && isset($firstItem['quantidade_comercial'])
                && isset($firstItem['valor_unitario_comercial'])
                && isset($firstItem['valor_bruto'])
                && isset($firstItem['codigo_ncm'])
                && isset($firstItem['cfop']);
        });
    }
}
