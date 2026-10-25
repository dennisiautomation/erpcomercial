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

/**
 * O certificado A1 NÃO tem endpoint próprio na Focus (armadilha 29): vai em base64
 * pela API de EMPRESAS, com TOKEN MASTER, em api.focusnfe.com.br —
 * `PUT /v2/empresas/{focus_empresa_id}`. E antes de gastar a chamada, o serviço abre
 * o .pfx localmente com openssl para conferir senha, validade e CNPJ do titular.
 *
 * Por isso os testes montam um PKCS#12 de verdade: com um arquivo falso o serviço
 * para na leitura local e nenhum dos caminhos que interessa é exercitado.
 */
class CertificadoDigitalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->config = ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'           => $this->empresa->id,
            'unidade_id'           => $this->unidade->id,
            'ambiente'             => 'homologacao',
            'focus_token'          => 'token-cert-test',
            'focus_empresa_id'     => 4242,
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'       => 'fiscal',
        ]);

        // A instalação é sempre com o token master, em produção (armadilha 29)
        config(['services.focus_nfe.master_token' => 'master-token-fake']);
    }

    /** PKCS#12 autoassinado, com o CNPJ no CN como nos certificados e-CNPJ reais. */
    private function pfx(?string $cnpj = null, string $senha = 'senha-correta'): string
    {
        $cnpj ??= preg_replace('/\D/', '', $this->unidade->cnpj);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new([
            'countryName'      => 'BR',
            'organizationName' => 'Empresa Teste LTDA',
            'commonName'       => "EMPRESA TESTE:{$cnpj}",
        ], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);

        openssl_pkcs12_export($cert, $pfx, $key, $senha);

        return $pfx;
    }

    private function service(): CertificadoDigitalService
    {
        return new CertificadoDigitalService(FocusNFeClient::fromConfig($this->config));
    }

    public function test_upload_certificado_com_sucesso_atualiza_metadados(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas/4242' => Http::response([
                'certificado_valido_ate' => '2027-12-31',
            ], 200),
        ]);

        $resultado = $this->service()->enviar(
            $this->empresa,
            $this->config,
            $this->pfx(),
            'senha-correta',
            'meu-cert.pfx'
        );

        $this->assertNotNull($resultado->certificado_enviado_em);
        $this->assertEquals('meu-cert.pfx', $resultado->certificado_nome);
        $this->assertEquals(preg_replace('/\D/', '', $this->unidade->cnpj), $resultado->certificado_cnpj);
        $this->assertEquals('2027-12-31', $resultado->certificado_validade?->toDateString());

        // O .pfx vai em base64 no corpo da empresa — nunca num endpoint de certificado
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/v2/empresas/4242')
                && ! empty($request->data()['arquivo_certificado_base64'])
                && $request->data()['senha_certificado'] === 'senha-correta';
        });
    }

    public function test_upload_falha_com_senha_incorreta_sem_consultar_focus(): void
    {
        Http::fake();

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('Senha do certificado incorreta');

        try {
            $this->service()->enviar($this->empresa, $this->config, $this->pfx(), 'senha-errada', 'cert.pfx');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_upload_falha_com_cnpj_divergente(): void
    {
        Http::fake();

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('diferente do CNPJ desta unidade');

        try {
            $this->service()->enviar(
                $this->empresa,
                $this->config,
                $this->pfx('99887766000155'),
                'senha-correta',
                'cert.pfx'
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_upload_rejeita_arquivo_que_nao_e_pkcs12(): void
    {
        Http::fake();

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('não parece ser um certificado A1');

        $this->service()->enviar($this->empresa, $this->config, 'binary-fake', 'senha-correta', 'cert.pfx');
    }

    public function test_upload_rejeita_senha_vazia_sem_consultar_focus(): void
    {
        Http::fake();

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('senha');

        try {
            $this->service()->enviar($this->empresa, $this->config, $this->pfx(), '', 'cert.pfx');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_upload_exige_unidade_provisionada_na_focus(): void
    {
        Http::fake();
        $this->config->update(['focus_empresa_id' => null]);

        $this->expectException(\App\Exceptions\CertificadoDigitalException::class);
        $this->expectExceptionMessage('ainda não foi provisionada');

        $this->service()->enviar($this->empresa, $this->config, $this->pfx(), 'senha-correta', 'cert.pfx');
    }

    public function test_dono_pode_enviar_certificado_via_http(): void
    {
        Http::fake([
            'api.focusnfe.com.br/v2/empresas/4242' => Http::response([
                'certificado_valido_ate' => '2027-12-31',
            ], 200),
        ]);

        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.configuracao-fiscal.certificado'), [
                'certificado_senha' => 'senha-correta',
                'certificado'       => TestFile::fake()->createWithContent('cert.pfx', $this->pfx()),
            ]);

        $response->assertRedirect(route('app.configuracao-fiscal.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'id'               => $this->config->id,
            'certificado_nome' => 'cert.pfx',
        ]);
    }
}
