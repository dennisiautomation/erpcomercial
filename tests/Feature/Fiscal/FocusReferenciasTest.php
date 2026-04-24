<?php

namespace Tests\Feature\Fiscal;

use App\Services\FocusNFe\FocusReferenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class FocusReferenciasTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.focus_nfe.master_token' => 'master-ref-token']);
        Cache::flush();

        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    // ─── NCM ───────────────────────────────────────────────────────────

    public function test_ncm_retorna_vazio_para_busca_muito_curta(): void
    {
        $resultado = FocusReferenciasService::make()->ncms('a');
        $this->assertEquals([], $resultado);
    }

    public function test_ncm_normaliza_resposta_da_focus(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/ncms*' => Http::response([
                ['codigo' => '04011010', 'descricao' => 'Leite em natureza'],
                ['codigo' => '04039090', 'descricao' => 'Outros iogurtes'],
            ], 200),
        ]);

        $resultado = FocusReferenciasService::make()->ncms('leite');

        $this->assertCount(2, $resultado);
        $this->assertEquals('04011010', $resultado[0]['codigo']);
        $this->assertEquals('Leite em natureza', $resultado[0]['descricao']);
    }

    public function test_ncm_usa_cache(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/ncms*' => Http::response([
                ['codigo' => '12345678', 'descricao' => 'Algum item'],
            ], 200),
        ]);

        $svc = FocusReferenciasService::make();
        $svc->ncms('algum');
        $svc->ncms('algum'); // segunda chamada deve vir do cache

        Http::assertSentCount(1);
    }

    // ─── CFOP ──────────────────────────────────────────────────────────

    public function test_cfop_filtra_por_busca_em_memoria(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/cfops*' => Http::response([
                ['codigo' => '5102', 'descricao' => 'Venda de mercadoria adquirida'],
                ['codigo' => '6102', 'descricao' => 'Venda interestadual'],
                ['codigo' => '1102', 'descricao' => 'Compra para comercialização'],
            ], 200),
        ]);

        $svc = FocusReferenciasService::make();

        // Sem filtro
        $this->assertCount(3, $svc->cfops(''));

        // Filtra por descrição
        $vendas = $svc->cfops('venda');
        $this->assertCount(2, $vendas);
    }

    // ─── Municípios ────────────────────────────────────────────────────

    public function test_municipios_exige_uf_valida(): void
    {
        $this->assertEquals([], FocusReferenciasService::make()->municipios('XY'));
        $this->assertEquals([], FocusReferenciasService::make()->municipios(''));
    }

    public function test_municipios_normaliza_codigo_ibge(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/municipios/SP' => Http::response([
                ['codigo_ibge' => '3550308', 'nome' => 'São Paulo'],
                ['codigo_ibge' => '3509502', 'nome' => 'Campinas'],
            ], 200),
        ]);

        $lista = FocusReferenciasService::make()->municipios('sp');

        $this->assertCount(2, $lista);
        $this->assertEquals('3550308', $lista[0]['codigo']);
        $this->assertEquals('São Paulo', $lista[0]['nome']);
        $this->assertEquals('SP', $lista[0]['uf']);
    }

    // ─── CNPJ ──────────────────────────────────────────────────────────

    public function test_cnpj_retorna_null_para_formato_invalido(): void
    {
        $this->assertNull(FocusReferenciasService::make()->cnpj('123'));
        $this->assertNull(FocusReferenciasService::make()->cnpj(''));
    }

    public function test_cnpj_retorna_dados_da_receita(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/cnpjs/12345678000199' => Http::response([
                'cnpj' => '12345678000199',
                'nome' => 'Empresa Teste LTDA',
                'fantasia' => 'Teste',
                'municipio' => 'São Paulo',
                'uf' => 'SP',
            ], 200),
        ]);

        $dados = FocusReferenciasService::make()->cnpj('12.345.678/0001-99');

        $this->assertEquals('Empresa Teste LTDA', $dados['nome']);
        $this->assertEquals('SP', $dados['uf']);
    }

    public function test_cnpj_retorna_null_em_404(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/cnpjs/*' => Http::response('not found', 404),
        ]);

        $this->assertNull(FocusReferenciasService::make()->cnpj('99999999000100'));
    }

    // ─── Endpoints AJAX ────────────────────────────────────────────────

    public function test_endpoint_ncm_autocomplete_retorna_formato_esperado(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/ncms*' => Http::response([
                ['codigo' => '04011010', 'descricao' => 'Leite em natureza'],
            ], 200),
        ]);

        $user = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($user, $this->unidade)
            ->getJson(route('app.focus-autocomplete.ncm', ['q' => 'leite']));

        $response->assertOk();
        $json = $response->json();
        $this->assertCount(1, $json);
        $this->assertEquals('04011010', $json[0]['codigo']);
        $this->assertStringContainsString('Leite em natureza', $json[0]['descricao']);
    }

    public function test_endpoint_cnpj_retorna_404_quando_nao_encontrado(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/cnpjs/*' => Http::response('not found', 404),
        ]);

        $user = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($user, $this->unidade)
            ->getJson(route('app.focus-autocomplete.cnpj', ['cnpj' => '99999999000100']))
            ->assertStatus(404)
            ->assertJsonPath('erro', 'CNPJ não encontrado ou inválido.');
    }

    public function test_endpoints_retornam_vazio_sem_master_token(): void
    {
        config(['services.focus_nfe.master_token' => null]);

        $user = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($user, $this->unidade)
            ->getJson(route('app.focus-autocomplete.ncm', ['q' => 'leite']))
            ->assertOk()
            ->assertExactJson([]);
    }
}
