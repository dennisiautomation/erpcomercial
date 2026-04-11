<?php

namespace Tests\Feature\Fiscal;

use App\Models\Caixa;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PdvFiscalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private User $vendedor;
    private Caixa $caixa;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->vendedor = $this->createUser($this->empresa, $this->unidade, 'caixa');
        $this->caixa    = $this->openCaixa($this->empresa, $this->unidade, $this->vendedor);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function createConfigFiscal(bool $ativa = true, string $tipoCupom = 'fiscal'): ConfiguracaoFiscal
    {
        return ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'test-token-pdv-123',
            'serie_nfe'           => 1,
            'serie_nfce'          => 1,
            'csc_nfce'            => 'CSC-TESTE-PDV',
            'csc_id_nfce'         => '1',
            'emissao_fiscal_ativa' => $ativa,
            'tipo_cupom_pdv'      => $tipoCupom,
        ]);
    }

    private function vendaPayload(): array
    {
        $produto = $this->createProduto($this->empresa, [
            'ncm'  => '61091000',
            'cfop' => '5102',
        ]);

        return [
            'itens' => [
                [
                    'produto_id'     => $produto->id,
                    'quantidade'     => 2,
                    'preco_unitario' => 50.00,
                    'desconto_valor' => 0,
                ],
            ],
            'pagamentos' => [
                [
                    'forma' => 'dinheiro',
                    'valor' => 100.00,
                ],
            ],
        ];
    }

    private function actingAsVendedorComCaixa(): static
    {
        return $this->actingAs($this->vendedor)->withSession([
            'unidade_id' => $this->unidade->id,
            'empresa_id' => $this->empresa->id,
            'caixa_id'   => $this->caixa->id,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_pdv_emits_nfce_when_fiscal_active(): void
    {
        $this->createConfigFiscal(ativa: true, tipoCupom: 'fiscal');

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'                  => 'autorizado',
                'status_sefaz'            => '100',
                'mensagem_sefaz'          => 'Autorizado o uso da NF-e',
                'chave_nfe'               => 'NFe35190607504505000132650010000000011987654321',
                'numero'                  => '1',
                'serie'                   => '1',
                'caminho_xml_nota_fiscal' => '/arquivos/xml.xml',
                'caminho_danfe'           => '/arquivos/danfe.pdf',
            ], 200),
        ]);

        $response = $this->actingAsVendedorComCaixa()
            ->postJson(route('app.pdv.venda'), $this->vendaPayload());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('tipo_cupom', 'fiscal');
        $response->assertJsonStructure([
            'success',
            'venda',
            'cupom',
            'nota_fiscal',
            'tipo_cupom',
        ]);

        // Verify nota_fiscal is present in response
        $this->assertNotNull($response->json('nota_fiscal'));
        $this->assertEquals('autorizada', $response->json('nota_fiscal.status'));

        // Verify DB record
        $this->assertDatabaseCount('notas_fiscais', 1);
        $this->assertDatabaseHas('notas_fiscais', [
            'status' => 'autorizada',
            'tipo'   => 'nfce',
        ]);
    }

    public function test_pdv_emits_recibo_when_fiscal_inactive(): void
    {
        $this->createConfigFiscal(ativa: false, tipoCupom: 'nao_fiscal');

        $response = $this->actingAsVendedorComCaixa()
            ->postJson(route('app.pdv.venda'), $this->vendaPayload());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('tipo_cupom', 'nao_fiscal');

        // Nota fiscal should be null in response
        $this->assertNull($response->json('nota_fiscal'));

        // No NotaFiscal record should be created
        $this->assertDatabaseCount('notas_fiscais', 0);
    }

    public function test_pdv_falls_back_to_recibo_on_nfce_error(): void
    {
        $this->createConfigFiscal(ativa: true, tipoCupom: 'fiscal');

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfce*' => Http::response([
                'status'  => 'erro_autorizacao',
                'mensagem' => 'SEFAZ indisponivel',
            ], 500),
        ]);

        $response = $this->actingAsVendedorComCaixa()
            ->postJson(route('app.pdv.venda'), $this->vendaPayload());

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Sale should still succeed even with NFC-e error
        $this->assertNotNull($response->json('venda'));
        $this->assertNotNull($response->json('cupom'));
    }
}
