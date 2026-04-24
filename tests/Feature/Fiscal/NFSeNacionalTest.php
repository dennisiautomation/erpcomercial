<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\NFSeDispatcher;
use App\Services\FocusNFe\NFSeNacionalService;
use App\Services\FocusNFe\ReformaTributariaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFSeNacionalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function configBase(array $overrides = []): ConfiguracaoFiscal
    {
        return ConfiguracaoFiscal::withoutGlobalScopes()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'ambiente' => 'homologacao',
            'focus_token' => 'token-nfsen-test',
            'emissao_fiscal_ativa' => true,
            'emite_nfse' => true,
            'nfse_padrao' => 'nacional',
        ], $overrides));
    }

    // ─── NFSe Nacional service ─────────────────────────────────────────

    public function test_emissao_nfse_nacional_envia_para_endpoint_nacional(): void
    {
        $config = $this->configBase();
        $cliente = $this->createCliente($this->empresa);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional*' => Http::response([
                'status' => 'processando_autorizacao',
            ], 202),
        ]);

        $service = NFSeNacionalService::forUnidade($this->unidade);

        $nota = $service->emitir([
            'valor_servicos' => 500.00,
            'discriminacao' => 'Consultoria em TI',
            'codigo_servico_nacional' => '01.01',
            'aliquota_iss' => 5.0,
        ], $config, $cliente);

        $this->assertEquals(StatusNotaFiscal::Pendente, $nota->status);
        $this->assertStringStartsWith('nfse-nac-', $nota->focus_ref);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/nfse_nacional'));
    }

    public function test_valida_codigo_servico_obrigatorio_no_padrao_nacional(): void
    {
        $config = $this->configBase();

        $this->expectException(\App\Exceptions\NotaFiscalEmissaoException::class);
        $this->expectExceptionMessageMatches('/código do serviço/ui');

        NFSeNacionalService::forUnidade($this->unidade)->emitir([
            'valor_servicos' => 100,
            'discriminacao' => 'servico x',
            // codigo_servico_nacional e item_lista_servico ausentes
        ], $config);
    }

    // ─── Dispatcher ────────────────────────────────────────────────────

    public function test_dispatcher_roteia_para_municipal_quando_padrao_municipal(): void
    {
        $this->configBase(['nfse_padrao' => 'municipal']);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse?*' => Http::response(['status' => 'processando_autorizacao'], 202),
        ]);

        $nota = NFSeDispatcher::forUnidade($this->unidade)->emitir([
            'valor_servicos' => 200,
            'discriminacao' => 'Serviço municipal',
            'item_lista_servico' => '01.01',
            'aliquota_iss' => 3,
        ]);

        $this->assertStringStartsWith('nfse-', $nota->focus_ref);
        $this->assertStringNotContainsString('nfse-nac-', $nota->focus_ref);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/nfse?'));
    }

    public function test_dispatcher_roteia_para_nacional_quando_padrao_nacional(): void
    {
        $this->configBase(['nfse_padrao' => 'nacional']);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional*' => Http::response(['status' => 'processando_autorizacao'], 202),
        ]);

        $nota = NFSeDispatcher::forUnidade($this->unidade)->emitir([
            'valor_servicos' => 200,
            'discriminacao' => 'Serviço nacional',
            'codigo_servico_nacional' => '01.01',
            'aliquota_iss' => 3,
        ]);

        $this->assertStringStartsWith('nfse-nac-', $nota->focus_ref);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/nfse_nacional'));
    }

    public function test_dispatcher_detecta_padrao_pelo_focus_ref_existente(): void
    {
        $config = $this->configBase(['nfse_padrao' => 'municipal']);

        // Nota emitida antes quando padrão era nacional
        $nota = \App\Models\NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'tipo' => \App\Enums\TipoNotaFiscal::NFSe,
            'status' => StatusNotaFiscal::Autorizada,
            'focus_ref' => 'nfse-nac-123-456',
            'valor_total' => 100,
            'natureza_operacao' => 'x',
            'ambiente' => 'homologacao',
        ]);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional/*' => Http::response(['status' => 'cancelado'], 200),
        ]);

        NFSeDispatcher::forUnidade($this->unidade)->cancelar($nota, 'Erro no valor');

        // Deve ter chamado o endpoint nacional mesmo com padrão municipal agora
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/nfse_nacional/'));
    }

    // ─── Reforma Tributária calculator ────────────────────────────────

    public function test_calculator_retorna_null_para_flag_desligada(): void
    {
        $config = $this->configBase([
            'ibs_ativo' => false,
            'cbs_ativo' => false,
            'is_ativo' => false,
        ]);

        $result = (new ReformaTributariaCalculator($config))->calcular(1000);

        $this->assertNull($result['ibs']);
        $this->assertNull($result['cbs']);
        $this->assertNull($result['is']);
    }

    public function test_calculator_usa_aliquota_padrao_da_config_quando_item_nao_define(): void
    {
        $config = $this->configBase([
            'ibs_ativo' => true,
            'cbs_ativo' => true,
            'ibs_aliquota_padrao' => 0.9,
            'cbs_aliquota_padrao' => 0.1,
        ]);

        $result = (new ReformaTributariaCalculator($config))->calcular(1000);

        $this->assertEquals(9.00, $result['ibs']['valor']);
        $this->assertEquals(0.9, $result['ibs']['aliquota']);
        $this->assertEquals(1.00, $result['cbs']['valor']);
        $this->assertEquals(0.1, $result['cbs']['aliquota']);
    }

    public function test_calculator_usa_aliquota_do_item_quando_disponivel(): void
    {
        $config = $this->configBase([
            'ibs_ativo' => true,
            'ibs_aliquota_padrao' => 0.9,
        ]);

        $result = (new ReformaTributariaCalculator($config))->calcular(
            1000,
            ['ibs_aliquota' => 2.5, 'cst_ibs_cbs' => '050']
        );

        $this->assertEquals(25.00, $result['ibs']['valor']);
        $this->assertEquals(2.5, $result['ibs']['aliquota']);
        $this->assertEquals('050', $result['ibs']['cst']);
    }

    public function test_calculator_aplica_aliquota_teste_2026_como_fallback(): void
    {
        $config = $this->configBase([
            'ibs_ativo' => true,
            'cbs_ativo' => true,
            'ibs_aliquota_padrao' => null,
            'cbs_aliquota_padrao' => null,
        ]);

        $result = (new ReformaTributariaCalculator($config))->calcular(1000);

        $this->assertEquals(ReformaTributariaCalculator::IBS_TESTE_2026, $result['ibs']['aliquota']);
        $this->assertEquals(ReformaTributariaCalculator::CBS_TESTE_2026, $result['cbs']['aliquota']);
    }

    public function test_bloco_payload_omite_tributos_desligados(): void
    {
        $config = $this->configBase([
            'ibs_ativo' => true,
            'cbs_ativo' => false,
            'is_ativo' => false,
            'ibs_aliquota_padrao' => 0.9,
        ]);

        $payload = (new ReformaTributariaCalculator($config))->blocoPayload(500);

        $this->assertArrayHasKey('ibs', $payload);
        $this->assertArrayNotHasKey('cbs', $payload);
        $this->assertArrayNotHasKey('is', $payload);
    }
}
