<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Jobs\BaixarXmlNotaJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\Unidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * STILO (05/11/2026): 7 lojas com só 2 CNPJs, cada loja com seu webhook_secret.
 * O webhook achava a config pelo CNPJ (sempre a 1ª loja) e recusava (401) o aviso
 * das outras. E a NFC-e que a Focus respondeu "autorizado" e a SEFAZ rejeitou depois
 * (613) ficava "autorizada" no ERP, com o download do XML falhando para sempre.
 */
class WebhookMesmoCnpjTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private Unidade $filial;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([BaixarXmlNotaJob::class]);

        [$this->empresa, $this->unidade] = $this->createTenant();

        // Filial com o MESMO CNPJ da matriz, segredo diferente
        $this->filial = Unidade::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Filial mesmo CNPJ',
            'cnpj' => $this->unidade->cnpj,
            'ie' => $this->unidade->ie,
            'cep' => '01001000', 'logradouro' => 'Rua Teste', 'numero' => '200', 'bairro' => 'Centro',
            'cidade' => 'São Paulo', 'uf' => 'SP', 'telefone' => '11999999999', 'status' => 'ativa',
        ]);

        foreach ([[$this->unidade, 'segredo-matriz'], [$this->filial, 'segredo-filial']] as [$u, $segredo]) {
            ConfiguracaoFiscal::withoutGlobalScopes()->create([
                'empresa_id' => $this->empresa->id,
                'unidade_id' => $u->id,
                'ambiente' => 'producao',
                'focus_token_producao' => 'token-' . $segredo,
                'webhook_secret' => $segredo,
                'emissao_fiscal_ativa' => true,
            ]);
        }
    }

    private function nota(Unidade $u, string $ref, StatusNotaFiscal $status = StatusNotaFiscal::Pendente, array $extra = []): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create($extra + [
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $u->id,
            'tipo' => TipoNotaFiscal::NFCe,
            'status' => $status,
            'focus_ref' => $ref,
            'focus_status' => 'processando_autorizacao',
            'valor_total' => 100,
            'natureza_operacao' => 'Venda ao Consumidor',
            'ambiente' => 'producao',
        ]);
    }

    public function test_aviso_da_filial_com_cnpj_da_matriz_e_aceito(): void
    {
        $nota = $this->nota($this->filial, 'nfce-filial-001');

        $this->withHeaders(['Authorization' => 'Bearer segredo-filial'])
            ->postJson('/webhooks/focusnfe', [
                'cnpj_emitente' => $this->unidade->cnpj,
                'ref' => 'nfce-filial-001',
                'status' => 'erro_autorizacao',
                'mensagem' => 'Rejeicao: Chave de Acesso difere da existente em BD',
            ])->assertOk();

        $this->assertEquals(StatusNotaFiscal::Rejeitada, $nota->refresh()->status);
    }

    public function test_aviso_da_filial_com_segredo_da_matriz_continua_recusado(): void
    {
        $nota = $this->nota($this->filial, 'nfce-filial-002');

        // O segredo vale para a loja da NOTA — o de outra loja não serve
        $this->withHeaders(['Authorization' => 'Bearer segredo-matriz'])
            ->postJson('/webhooks/focusnfe', [
                'cnpj_emitente' => $this->unidade->cnpj,
                'ref' => 'nfce-filial-002',
                'status' => 'autorizado',
            ])->assertStatus(401);

        $this->assertEquals(StatusNotaFiscal::Pendente, $nota->refresh()->status);
    }

    public function test_segredo_errado_continua_recusado(): void
    {
        $nota = $this->nota($this->filial, 'nfce-filial-003');

        $this->withHeaders(['Authorization' => 'Bearer qualquer'])
            ->postJson('/webhooks/focusnfe', [
                'cnpj_emitente' => $this->unidade->cnpj,
                'ref' => 'nfce-filial-003',
                'status' => 'autorizado',
            ])->assertStatus(401);

        $this->assertEquals(StatusNotaFiscal::Pendente, $nota->refresh()->status);
    }

    public function test_manifestacao_sem_ref_acha_a_loja_pelo_segredo(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer segredo-filial'])
            ->postJson('/webhooks/focusnfe', [
                'cnpj_emitente' => $this->unidade->cnpj,
                'evento' => 'manifestacao_destinatario',
            ])->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer qualquer'])
            ->postJson('/webhooks/focusnfe', [
                'cnpj_emitente' => $this->unidade->cnpj,
                'evento' => 'manifestacao_destinatario',
            ])->assertStatus(401);
    }

    public function test_cron_do_xml_marca_rejeitada_a_nota_que_a_sefaz_recusou_depois(): void
    {
        Storage::fake();
        $chave = 'NFe22261125105231000190650020000049459826184635';
        $nota = $this->nota($this->filial, 'nfce-613-001', StatusNotaFiscal::Autorizada, [
            'focus_status' => 'autorizado',
            'chave_acesso' => $chave,
            'numero' => '4945',
            'xml_url' => "/notas_fiscais_consumidor/{$chave}.xml",
        ]);
        $boa = $this->nota($this->unidade, 'nfce-ok-001', StatusNotaFiscal::Autorizada, [
            'focus_status' => 'autorizado',
            'chave_acesso' => 'NFe22261125105231000190650020000049469826184636',
            'numero' => '4946',
            'xml_url' => '/notas_fiscais_consumidor/NFe22261125105231000190650020000049469826184636.xml',
        ]);

        Http::fake([
            "*{$chave}.xml" => Http::response('Arquivo não encontrado.', 404),
            '*NFe22261125105231000190650020000049469826184636.xml' => Http::response('<nfeProc>ok</nfeProc>', 200),
            '*/v2/nfce/nfce-613-001*' => Http::response([
                'status' => 'erro_autorizacao',
                'status_sefaz' => '613',
                'mensagem_sefaz' => 'Rejeicao: Chave de Acesso difere da existente em BD',
            ], 200),
        ]);

        $this->artisan('fiscal:baixar-xmls-notas')->assertExitCode(0);

        $this->assertEquals(StatusNotaFiscal::Rejeitada, $nota->refresh()->status);
        $this->assertStringContainsString('613', '613 ' . $nota->focus_mensagem);
        $this->assertTrue($boa->refresh()->temXmlLocal());

        // Na noite seguinte a rejeitada nem entra na varredura
        Http::fake(fn () => throw new \RuntimeException('não devia chamar a Focus'));
        $this->artisan('fiscal:baixar-xmls-notas')->assertExitCode(0);
    }

    public function test_cron_ainda_falha_quando_a_nota_segue_autorizada_e_o_xml_nao_vem(): void
    {
        Storage::fake();
        $nota = $this->nota($this->filial, 'nfce-autorizada-sem-xml', StatusNotaFiscal::Autorizada, [
            'focus_status' => 'autorizado',
            'chave_acesso' => 'NFe22261125105231000190650020000049479826184637',
            'xml_url' => '/notas_fiscais_consumidor/NFe22261125105231000190650020000049479826184637.xml',
        ]);

        Http::fake([
            '*.xml' => Http::response('Arquivo não encontrado.', 404),
            '*/v2/nfce/*' => Http::response(['status' => 'autorizado', 'chave_nfe' => $nota->chave_acesso], 200),
        ]);

        $this->artisan('fiscal:baixar-xmls-notas')->assertExitCode(1);
        $this->assertEquals(StatusNotaFiscal::Autorizada, $nota->refresh()->status);
    }
}
