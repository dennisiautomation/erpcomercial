<?php

namespace Tests\Feature\Fiscal;

use App\Enums\TipoManifestacao;
use App\Models\ConfiguracaoFiscal;
use App\Models\NFeRecebida;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\ManifestacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManifestacaoTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;
    private ManifestacaoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->config = ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'token-manifestacao',
            'emissao_fiscal_ativa' => true,
        ]);

        $this->service = new ManifestacaoService(FocusNFeClient::fromConfig($this->config));
    }

    private function payloadFocus(): array
    {
        return [
            [
                'chave_nfe'             => '35230612345678000123550010000012341000012341',
                'cnpj_emitente'         => '12345678000199',
                'razao_social_emitente' => 'Fornecedor ABC Ltda',
                'numero'                => '123',
                'serie'                 => '1',
                'valor_total'           => 2450.99,
                'data_emissao'          => '2026-04-20T10:15:00-03:00',
                'caminho_xml_nota_fiscal' => '/xml/123.xml',
                'caminho_danfe'         => '/danfe/123.pdf',
            ],
            [
                'chave_nfe'             => '35230612345678000123550010000012342000012342',
                'cnpj_emitente'         => '98765432000111',
                'razao_social_emitente' => 'Fornecedor XYZ SA',
                'numero'                => '456',
                'serie'                 => '1',
                'valor_total'           => 780.00,
                'data_emissao'          => '2026-04-21T09:00:00-03:00',
            ],
        ];
    }

    public function test_sincronizar_importa_nfes_recebidas_da_focus(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas*' => Http::response($this->payloadFocus(), 200),
        ]);

        $novas = $this->service->sincronizar($this->empresa, $this->unidade);

        $this->assertEquals(2, $novas);
        $this->assertDatabaseHas('nfes_recebidas', [
            'chave_acesso'  => '35230612345678000123550010000012341000012341',
            'nome_emitente' => 'Fornecedor ABC Ltda',
            'empresa_id'    => $this->empresa->id,
        ]);
    }

    public function test_sincronizar_nao_duplica_notas_ja_existentes(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas*' => Http::response($this->payloadFocus(), 200),
        ]);

        $this->service->sincronizar($this->empresa, $this->unidade);
        $novas = $this->service->sincronizar($this->empresa, $this->unidade);

        $this->assertEquals(0, $novas);
        $this->assertEquals(2, NFeRecebida::withoutGlobalScopes()->count());
    }

    public function test_manifestar_ciencia_atualiza_status_da_nfe(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas/*/manifestacao' => Http::response([
                'status'           => 'autorizado',
                'numero_protocolo' => '210200000000042',
            ], 200),
        ]);

        $nfe = NFeRecebida::withoutGlobalScopes()->create([
            'empresa_id'    => $this->empresa->id,
            'unidade_id'    => $this->unidade->id,
            'chave_acesso'  => '35230612345678000123550010000012341000099999',
            'cnpj_emitente' => '12345678000199',
            'nome_emitente' => 'Fornecedor ABC',
            'valor_total'   => 500.00,
        ]);

        $resultado = $this->service->manifestar($nfe, TipoManifestacao::Ciencia);

        $this->assertEquals(TipoManifestacao::Ciencia, $resultado->tipo_ultima_manifestacao);
        $this->assertEquals('210200000000042', $resultado->protocolo_manifestacao);
        $this->assertNotNull($resultado->manifestada_em);
    }

    public function test_manifestacao_desconhecimento_exige_justificativa(): void
    {
        Http::fake(); // não deve chamar Focus

        $nfe = NFeRecebida::withoutGlobalScopes()->create([
            'empresa_id'    => $this->empresa->id,
            'unidade_id'    => $this->unidade->id,
            'chave_acesso'  => '35230612345678000123550010000012341000088888',
            'cnpj_emitente' => '12345678000199',
            'nome_emitente' => 'Fornecedor Suspeito',
            'valor_total'   => 10_000.00,
        ]);

        $this->expectException(\App\Exceptions\ManifestacaoException::class);
        $this->expectExceptionMessage('Desconhecimento');

        $this->service->manifestar($nfe, TipoManifestacao::Desconhecimento, 'curto');

        Http::assertNothingSent();
    }

    public function test_erro_de_prazo_excedido_traduz_mensagem(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas/*/manifestacao' => Http::response([
                'mensagem' => 'Prazo para manifestação já foi excedido',
            ], 400),
        ]);

        $nfe = NFeRecebida::withoutGlobalScopes()->create([
            'empresa_id'    => $this->empresa->id,
            'unidade_id'    => $this->unidade->id,
            'chave_acesso'  => '35230612345678000123550010000012341000077777',
            'cnpj_emitente' => '12345678000199',
            'nome_emitente' => 'Fornecedor',
            'valor_total'   => 1.00,
        ]);

        $this->expectException(\App\Exceptions\ManifestacaoException::class);
        $this->expectExceptionMessage('prazo para manifestação');

        $this->service->manifestar($nfe, TipoManifestacao::Ciencia);
    }

    public function test_dono_pode_acessar_lista_nfes_recebidas(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->get(route('app.nfes-recebidas.index'));

        $response->assertOk();
        $response->assertViewIs('app.nfes-recebidas.index');
    }

    public function test_vendedor_nao_pode_acessar_lista_nfes_recebidas(): void
    {
        $vendedor = $this->createUser($this->empresa, $this->unidade, 'vendedor');

        $response = $this->actingAsUser($vendedor, $this->unidade)
            ->get(route('app.nfes-recebidas.index'));

        $response->assertForbidden();
    }

    public function test_sincronizar_via_http_dispara_service_e_retorna_flash(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas*' => Http::response($this->payloadFocus(), 200),
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.nfes-recebidas.sincronizar'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(2, NFeRecebida::withoutGlobalScopes()->count());
    }

    public function test_manifestar_via_http_atualiza_nota_da_propria_unidade(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfes_recebidas/*/manifestacao' => Http::response([
                'numero_protocolo' => '210200000000777',
            ], 200),
        ]);

        $nfe = NFeRecebida::withoutGlobalScopes()->create([
            'empresa_id'    => $this->empresa->id,
            'unidade_id'    => $this->unidade->id,
            'chave_acesso'  => '35230612345678000123550010000012341000066666',
            'cnpj_emitente' => '12345678000199',
            'nome_emitente' => 'Fornecedor Z',
            'valor_total'   => 300.00,
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.nfes-recebidas.manifestar', $nfe), [
                'tipo' => TipoManifestacao::Confirmacao->value,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $nfe->refresh();
        $this->assertEquals(TipoManifestacao::Confirmacao, $nfe->tipo_ultima_manifestacao);
        $this->assertEquals($dono->id, $nfe->manifestada_por);
    }

    public function test_nao_pode_manifestar_nfe_de_outra_unidade(): void
    {
        [$outraEmpresa, $outraUnidade] = $this->createTenant('b');

        $nfe = NFeRecebida::withoutGlobalScopes()->create([
            'empresa_id'    => $outraEmpresa->id,
            'unidade_id'    => $outraUnidade->id,
            'chave_acesso'  => '35230698765432000111550010000055555000055555',
            'cnpj_emitente' => '99999999000100',
            'nome_emitente' => 'Outra empresa',
            'valor_total'   => 100.00,
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.nfes-recebidas.manifestar', $nfe), [
                'tipo' => TipoManifestacao::Ciencia->value,
            ]);

        // 404 via global scope (não expõe existência) ou 403 via abort_unless:
        // ambos são bloqueios legítimos.
        $this->assertContains($response->status(), [403, 404]);
    }
}
