<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class FocusEmpresaServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.focus_nfe.master_token' => 'master-token-fake-123',
        ]);

        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    public function test_master_disponivel_retorna_true_quando_token_configurado(): void
    {
        $this->assertTrue(FocusNFeClient::masterDisponivel());

        config(['services.focus_nfe.master_token' => null]);
        $this->assertFalse(FocusNFeClient::masterDisponivel());
    }

    public function test_criar_empresa_persiste_tokens_e_focus_empresa_id(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas' => Http::response([
                'id' => 987,
                'token_producao' => 'prod-token-abc',
                'token_homologacao' => 'homo-token-xyz',
                'cnpj' => preg_replace('/\D/', '', $this->empresa->cnpj),
            ], 200),
        ]);

        $service = FocusEmpresaService::make();
        $data = $service->criar($this->empresa, $this->unidade, [
            'habilita_nfe' => true,
            'habilita_nfce' => true,
            'habilita_manifestacao' => true,
        ]);

        $this->assertEquals(987, $data['id']);
        $this->assertEquals('prod-token-abc', $data['token_producao']);

        $config = ConfiguracaoFiscal::withoutGlobalScopes()
            ->where('empresa_id', $this->empresa->id)
            ->where('unidade_id', $this->unidade->id)
            ->first();

        $this->assertNotNull($config);
        $this->assertEquals(987, $config->focus_empresa_id);
        $this->assertEquals('prod-token-abc', $config->focus_token_producao);
        $this->assertEquals('homo-token-xyz', $config->focus_token_homologacao);
        $this->assertNotEmpty($config->webhook_secret);
        $this->assertEquals(48, strlen($config->webhook_secret));
        $this->assertTrue((bool) $config->emite_nfe);
        $this->assertTrue((bool) $config->emite_nfce);
        $this->assertFalse((bool) $config->emite_nfse);
    }

    public function test_criar_empresa_traduz_erro_senha_certificado(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas' => Http::response([
                'codigo' => 'certificado_invalido',
                'mensagem' => 'Senha do certificado incorreta',
            ], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/senha do certificado/i');

        FocusEmpresaService::make()->criar($this->empresa, $this->unidade, []);
    }

    public function test_criar_empresa_traduz_cnpj_ja_cadastrado(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas' => Http::response([
                'codigo' => 'cnpj_ja_existe',
                'mensagem' => 'CNPJ já existe na nossa base',
            ], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/já está cadastrado/i');

        FocusEmpresaService::make()->criar($this->empresa, $this->unidade, []);
    }

    public function test_client_master_sempre_opera_em_producao(): void
    {
        $client = FocusNFeClient::master();
        $this->assertTrue($client->isMaster());
        $this->assertEquals('producao', $client->getAmbiente());
        $this->assertStringContainsString('api.focusnfe.com.br', $client->getBaseUrl());

        Http::fake(['api.focusnfe.com.br/*' => Http::response([], 200)]);
        $client->get('/v2/empresas');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.focusnfe.com.br'));
    }

    public function test_rate_limit_429_lanca_exception_amigavel(): void
    {
        config(['services.focus_nfe.master_token' => 'token-rl']);

        Http::fake([
            'api.focusnfe.com.br/v2/empresas' => Http::response('Too Many Requests', 429, [
                'Rate-Limit-Reset' => '30',
            ]),
        ]);

        $this->expectException(\App\Services\FocusNFe\FocusRateLimitException::class);

        FocusEmpresaService::make()->criar($this->empresa, $this->unidade, []);
    }

    public function test_atualizar_falha_sem_focus_empresa_id(): void
    {
        ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'ambiente' => 'homologacao',
            'emissao_fiscal_ativa' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/não foi criada na Focus/i');

        FocusEmpresaService::make()->atualizar($this->empresa, $this->unidade, []);
    }

    public function test_consultar_retorna_dados_da_focus(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas/123' => Http::response([
                'id' => 123,
                'nome' => 'Empresa XYZ',
                'cnpj' => '12345678000199',
            ], 200),
        ]);

        $data = FocusEmpresaService::make()->consultar(123);

        $this->assertEquals(123, $data['id']);
        $this->assertEquals('Empresa XYZ', $data['nome']);
    }
}
