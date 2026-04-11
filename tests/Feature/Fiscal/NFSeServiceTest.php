<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFSeServiceTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private ConfiguracaoFiscal $config;
    private FocusNFeClient $client;
    private NFSeService $service;

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
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'      => 'fiscal',
        ]);

        $this->client  = new FocusNFeClient('test-token-fake-123', 'homologacao');
        $this->service = new NFSeService($this->client);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function baseDadosServico(): array
    {
        return [
            'valor_servicos'            => 1500.00,
            'discriminacao'             => 'Servico de consultoria empresarial',
            'item_lista_servico'        => '17.01',
            'codigo_tributario_municipio' => '1701',
            'aliquota_iss'              => 5.00,
            'valor_iss'                 => 75.00,
            'iss_retido'                => 'false',
            'natureza_operacao'         => 'Prestacao de Servicos',
        ];
    }

    private function createNotaAutorizadaNFSe(): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFSe,
            'status'            => StatusNotaFiscal::Autorizada,
            'focus_ref'         => 'nfse-test-111',
            'focus_status'      => 'autorizado',
            'numero'            => '500',
            'chave_acesso'      => 'VERIF-ABC123',
            'valor_total'       => 1500.00,
            'natureza_operacao' => 'Prestacao de Servicos',
            'ambiente'          => 'homologacao',
            'emitida_em'        => now(),
        ]);
    }

    private function createNotaPendenteNFSe(): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFSe,
            'status'            => StatusNotaFiscal::Pendente,
            'focus_ref'         => 'nfse-test-222',
            'focus_status'      => 'processando_autorizacao',
            'valor_total'       => 1500.00,
            'natureza_operacao' => 'Prestacao de Servicos',
            'ambiente'          => 'homologacao',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_nfse_emissao_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse*' => Http::response([
                'status'                    => 'autorizado',
                'numero'                    => '500',
                'codigo_verificacao'        => 'VERIF-ABC123',
                'caminho_xml_nota_fiscal'   => '/arquivos/nfse-xml.xml',
                'caminho_pdf_nota_fiscal'   => '/arquivos/nfse.pdf',
            ], 200),
        ]);

        $dados   = $this->baseDadosServico();
        $cliente = $this->createCliente($this->empresa);

        $nota = $this->service->emitir($dados, $this->config, $cliente);

        $this->assertInstanceOf(NotaFiscal::class, $nota);
        $this->assertTrue($nota->exists);
        $this->assertEquals(StatusNotaFiscal::Autorizada, $nota->status);
        $this->assertEquals(TipoNotaFiscal::NFSe, $nota->tipo);
        $this->assertEquals('500', $nota->numero);
        $this->assertEquals('VERIF-ABC123', $nota->chave_acesso);
        $this->assertEquals('/arquivos/nfse-xml.xml', $nota->xml_url);
        $this->assertNotNull($nota->emitida_em);
        $this->assertEquals($cliente->id, $nota->cliente_id);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Autorizada->value,
            'tipo'   => TipoNotaFiscal::NFSe->value,
        ]);
    }

    public function test_nfse_consulta_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse/*' => Http::response([
                'status'                    => 'autorizado',
                'numero'                    => '500',
                'codigo_verificacao'        => 'VERIF-XYZ789',
                'caminho_xml_nota_fiscal'   => '/arquivos/nfse-xml.xml',
                'caminho_pdf_nota_fiscal'   => '/arquivos/nfse.pdf',
            ], 200),
        ]);

        $nota = $this->createNotaPendenteNFSe();

        $result = $this->service->consultar($nota);

        $this->assertEquals(StatusNotaFiscal::Autorizada, $result->status);
        $this->assertEquals('500', $result->numero);
        $this->assertEquals('VERIF-XYZ789', $result->chave_acesso);
        $this->assertNotNull($result->emitida_em);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Autorizada->value,
        ]);
    }

    public function test_nfse_cancelamento_works(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse/*' => Http::response([
                'status' => 'cancelado',
            ], 200),
        ]);

        $nota = $this->createNotaAutorizadaNFSe();

        $result = $this->service->cancelar($nota, 'Servico nao prestado, cancelamento solicitado');

        $this->assertEquals(StatusNotaFiscal::Cancelada, $result->status);
        $this->assertEquals('Servico nao prestado, cancelamento solicitado', $result->cancelamento_motivo);
        $this->assertNotNull($result->cancelada_em);

        $this->assertDatabaseHas('notas_fiscais', [
            'id'     => $nota->id,
            'status' => StatusNotaFiscal::Cancelada->value,
        ]);
    }
}
