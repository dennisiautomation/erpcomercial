<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Jobs\ConsultarNotaFiscalJob;
use App\Jobs\EmitirNFSeJob;
use App\Models\Cliente;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFSePollingTest extends TestCase
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
            'focus_token'         => 'token-nfse-polling',
            'emissao_fiscal_ativa' => true,
            'emite_nfse'          => true,
        ]);
    }

    public function test_emissao_nfse_via_controller_encadeia_polling_quando_pendente(): void
    {
        Bus::fake([ConsultarNotaFiscalJob::class]);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse*' => Http::response([
                'status' => 'processando_autorizacao',
            ], 202),
        ]);

        $cliente = $this->createCliente($this->empresa);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $response = $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.notas-fiscais.emitir-nfse.store'), [
                'cliente_id'    => $cliente->id,
                'descricao'     => 'Consultoria de TI referente ao mes',
                'valor_servico' => 1200.00,
                'aliquota_iss'  => 5.0,
            ]);

        $response->assertSessionHas('success');

        Bus::assertDispatched(ConsultarNotaFiscalJob::class, function ($job) {
            return $job->notaFiscal->tipo === TipoNotaFiscal::NFSe
                && $job->notaFiscal->status === StatusNotaFiscal::Pendente;
        });
    }

    public function test_emissao_nfse_sincrona_nao_encadeia_polling(): void
    {
        Bus::fake([ConsultarNotaFiscalJob::class]);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse*' => Http::response([
                'status'                    => 'autorizado',
                'numero'                    => '777',
                'codigo_verificacao'        => 'VX9',
                'caminho_xml_nota_fiscal'   => '/xml/777.xml',
                'caminho_pdf_nota_fiscal'   => '/pdf/777.pdf',
            ], 200),
        ]);

        $cliente = $this->createCliente($this->empresa);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($dono, $this->unidade)
            ->post(route('app.notas-fiscais.emitir-nfse.store'), [
                'cliente_id'    => $cliente->id,
                'descricao'     => 'Consultoria de TI referente ao mes',
                'valor_servico' => 500.00,
                'aliquota_iss'  => 3.0,
            ]);

        Bus::assertNotDispatched(ConsultarNotaFiscalJob::class);
    }

    public function test_job_emitir_nfse_encadeia_consulta_se_pendente(): void
    {
        Bus::fake([ConsultarNotaFiscalJob::class]);

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse*' => Http::response([
                'status' => 'processando_autorizacao',
            ], 202),
        ]);

        $cliente = $this->createCliente($this->empresa);

        $job = new EmitirNFSeJob(
            $this->empresa->id,
            $this->unidade->id,
            [
                'descricao'      => 'Servico de faxina mensal',
                'valor_servicos' => 350.00,
                'aliquota_iss'   => 2.0,
            ],
            $cliente->id,
        );

        $job->handle();

        Bus::assertDispatched(ConsultarNotaFiscalJob::class);
    }

    public function test_consultar_nfse_atualiza_status_quando_autorizada(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse/*' => Http::response([
                'status'                    => 'autorizado',
                'numero'                    => '888',
                'codigo_verificacao'        => 'CV888',
                'caminho_xml_nota_fiscal'   => '/xml/888.xml',
                'caminho_pdf_nota_fiscal'   => '/pdf/888.pdf',
            ], 200),
        ]);

        $nota = NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFSe,
            'status'            => StatusNotaFiscal::Pendente,
            'focus_ref'         => 'nfse-polling-888',
            'focus_status'      => 'processando_autorizacao',
            'valor_total'       => 800.00,
            'natureza_operacao' => 'Prestacao de Servicos',
            'ambiente'          => 'homologacao',
        ]);

        (new ConsultarNotaFiscalJob($nota))->handle();

        $nota->refresh();
        $this->assertEquals(StatusNotaFiscal::Autorizada, $nota->status);
        $this->assertEquals('888', $nota->numero);
    }
}
