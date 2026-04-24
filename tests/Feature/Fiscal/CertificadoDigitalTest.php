<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracaoFiscal;
use App\Services\FocusNFe\CertificadoDigitalService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File as TestFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class CertificadoDigitalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->config = ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'token-cert-test',
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'      => 'fiscal',
        ]);
    }

    public function test_upload_certificado_com_sucesso_atualiza_metadados(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/empresas/*/certificado' => Http::response([
                'status'    => 'ok',
                'expiracao' => '2027-12-31',
            ], 200),
        ]);

        $service = new CertificadoDigitalService(FocusNFeClient::fromConfig($this->config));
        $resultado = $service->enviar(
            $this->empresa,
            $this->config,
            'pfx-binary-content-fake',
            'senha-correta',
            'meu-cert.pfx'
        );

        $this->assertNotNull($resultado->certificado_enviado_em);
        $this->assertEquals('meu-cert.pfx', $resultado->certificado_nome);
        $this->assertEquals(preg_replace('/\D/', '', $this->empresa->cnpj), $resultado->certificado_cnpj);
        $this->assertEquals('2027-12-31', $resultado->certificado_validade?->toDateString());
    }

    public function test_upload_falha_com_senha_incorreta_lanca_excecao_amigavel(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/empresas/*/certificado' => Http::response([
                'mensagem' => 'Senha do certificado incorreta',
            ], 422),
        ]);

        $service = new CertificadoDigitalService(FocusNFeClient::fromConfig($this->config));

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('Senha do certificado incorreta');

        $service->enviar($this->empresa, $this->config, 'fake', 'senha-errada', 'cert.pfx');
    }

    public function test_upload_falha_com_cnpj_divergente(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/empresas/*/certificado' => Http::response([
                'mensagem' => 'O CNPJ do titular do certificado não corresponde ao cadastro',
            ], 422),
        ]);

        $service = new CertificadoDigitalService(FocusNFeClient::fromConfig($this->config));

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('CNPJ do certificado não bate');

        $service->enviar($this->empresa, $this->config, 'fake', 'x', 'cert.pfx');
    }

    public function test_upload_rejeita_senha_vazia_sem_consultar_focus(): void
    {
        Http::fake(); // se for chamado, falha o teste

        $service = new CertificadoDigitalService(FocusNFeClient::fromConfig($this->config));

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('senha');

        $service->enviar($this->empresa, $this->config, 'fake', '', 'cert.pfx');

        Http::assertNothingSent();
    }

    public function test_dono_pode_enviar_certificado_via_http(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/empresas/*/certificado' => Http::response([
                'status'    => 'ok',
                'expiracao' => '2026-12-31',
            ], 200),
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.configuracao-fiscal.certificado'), [
                'certificado_senha' => 'minha-senha',
                'certificado'       => TestFile::fake()->createWithContent('cert.pfx', 'binary-fake'),
            ]);

        $response->assertRedirect(route('app.configuracao-fiscal.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'id'              => $this->config->id,
            'certificado_nome' => 'cert.pfx',
        ]);
    }
}
