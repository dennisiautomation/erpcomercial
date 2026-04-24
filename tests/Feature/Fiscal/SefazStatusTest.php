<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\SefazStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class SefazStatusTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private SefazStatusService $service;
    private ConfiguracaoFiscal $config;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->config = ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'token-sefaz',
            'emissao_fiscal_ativa' => true,
        ]);

        $this->service = new SefazStatusService(FocusNFeClient::fromConfig($this->config));

        Cache::flush();
    }

    public function test_sefaz_online_retorna_situacao_online(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'SP',
                'mensagem'    => 'Serviço em operação normal',
            ], 200),
        ]);

        $r = $this->service->consultar('SP');
        $this->assertEquals('online', $r['situacao']);
        $this->assertStringContainsString('operacional', $r['mensagem']);
    }

    public function test_sefaz_instavel_detecta_pela_mensagem(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'RJ',
                'mensagem'    => 'Serviço instável — SEFAZ com lentidão',
            ], 200),
        ]);

        $r = $this->service->consultar('RJ');
        $this->assertEquals('instavel', $r['situacao']);
    }

    public function test_sefaz_offline_detecta_pela_mensagem(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'BA',
                'mensagem'    => 'Serviço fora do ar',
            ], 200),
        ]);

        $r = $this->service->consultar('BA');
        $this->assertEquals('offline', $r['situacao']);
    }

    public function test_erro_http_retorna_desconhecido_sem_quebrar(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([], 500),
        ]);

        $r = $this->service->consultar('SP');
        $this->assertEquals('desconhecido', $r['situacao']);
    }

    public function test_resultado_e_cacheado_por_uf(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'SP',
                'mensagem'    => 'Operacional',
            ], 200),
        ]);

        $this->service->consultar('SP');
        $this->service->consultar('SP');
        $this->service->consultar('SP');

        // Só uma requisição de fato — as demais vieram do cache
        Http::assertSentCount(1);
    }

    public function test_invalidar_cache_forca_nova_consulta(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'SP',
                'mensagem'    => 'Operacional',
            ], 200),
        ]);

        $this->service->consultar('SP');
        $this->service->invalidar('SP');
        $this->service->consultar('SP');

        Http::assertSentCount(2);
    }

    public function test_endpoint_ajax_retorna_situacao(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/sefaz/status*' => Http::response([
                'autorizador' => 'SP',
                'mensagem'    => 'Operacional',
            ], 200),
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->getJson(route('app.configuracao-fiscal.sefaz-status', ['uf' => 'SP']));

        $response->assertOk();
        $response->assertJsonPath('situacao', 'online');
    }
}
