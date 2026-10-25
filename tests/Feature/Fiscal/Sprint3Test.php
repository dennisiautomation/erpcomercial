<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Jobs\BaixarXmlNotaJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\NFSeRecebida;
use App\Models\NotaFiscal;
use App\Services\FocusNFe\BackupXmlService;
use App\Services\FocusNFe\EmailsBloqueadosService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSesRecebidasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Sprint3Test extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        // Nota autorizada dispara BaixarXmlNotaJob; na fila `sync` do phpunit ele
        // roda na hora e tenta buscar o XML na Focus. Aqui o XML é posto à mão.
        Bus::fake([BaixarXmlNotaJob::class]);

        [$this->empresa, $this->unidade] = $this->createTenant();

        ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'ambiente' => 'homologacao',
            'focus_token' => 'token-sprint3',
            'emissao_fiscal_ativa' => true,
            'emite_nfse' => true,
        ]);
    }

    // ─── NFSes Recebidas ───────────────────────────────────────────────

    public function test_nfses_recebidas_sincroniza_e_cria_registros_sem_duplicar(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional/tomadas*' => Http::response([
                [
                    'codigo_verificacao' => 'VERIF-001',
                    'cnpj_prestador' => '12345678000199',
                    'razao_social_prestador' => 'Prestadora XYZ Ltda',
                    'numero' => '42',
                    'serie' => '1',
                    'discriminacao' => 'Serviço de consultoria',
                    'item_lista_servico' => '01.01',
                    'valor_servicos' => 1500.00,
                    'valor_iss' => 75.00,
                    'aliquota' => 5.00,
                    'iss_retido' => false,
                    'data_emissao' => '2026-04-01',
                    'caminho_xml_nota_fiscal' => '/xml/nfse-001.xml',
                ],
            ], 200),
        ]);

        $service = NFSesRecebidasService::forUnidade($this->unidade);

        $novas = $service->sincronizar($this->empresa, $this->unidade);
        $this->assertEquals(1, $novas);
        $this->assertDatabaseCount('nfses_recebidas', 1);

        $nfse = NFSeRecebida::withoutGlobalScopes()->first();
        $this->assertEquals('Prestadora XYZ Ltda', $nfse->nome_prestador);
        $this->assertEquals(1500.00, (float) $nfse->valor_servicos);
        $this->assertEquals('nacional', $nfse->padrao);

        // Segunda sync com mesmo payload não duplica
        $novasDup = $service->sincronizar($this->empresa, $this->unidade);
        $this->assertEquals(0, $novasDup);
        $this->assertDatabaseCount('nfses_recebidas', 1);
    }

    public function test_nfses_recebidas_cai_para_municipal_quando_nacional_falha(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional/tomadas*' => Http::response('not found', 404),
            'homologacao.focusnfe.com.br/v2/nfses_tomadas*' => Http::response([
                [
                    'codigo_verificacao' => 'MUN-001',
                    'cnpj_prestador' => '98765432000155',
                    'nome_prestador' => 'Prestador Municipal',
                    'valor_servicos' => 300,
                ],
            ], 200),
        ]);

        $service = NFSesRecebidasService::forUnidade($this->unidade);
        $novas = $service->sincronizar($this->empresa, $this->unidade);

        $this->assertEquals(1, $novas);
        $this->assertEquals('municipal', NFSeRecebida::withoutGlobalScopes()->first()->padrao);
    }

    public function test_endpoint_sincronizar_nfses_retorna_flash_e_persiste(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse_nacional/tomadas*' => Http::response([
                [
                    'codigo_verificacao' => 'WEB-001',
                    'cnpj_prestador' => '11111111000111',
                    'nome_prestador' => 'Via Web',
                    'valor_servicos' => 500,
                ],
            ], 200),
        ]);

        $user = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($user, $this->unidade)
            ->post(route('app.nfses-recebidas.sincronizar'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('nfses_recebidas', 1);
    }

    // ─── Emails bloqueados ────────────────────────────────────────────

    public function test_emails_bloqueados_lista_e_normaliza(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/emails_bloqueados*' => Http::response([
                ['email' => 'bounce@example.com', 'motivo' => 'hard_bounce', 'data_bloqueio' => '2026-03-15'],
                ['email' => 'cheio@example.com', 'razao' => 'mailbox_full'],
            ], 200),
        ]);

        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new EmailsBloqueadosService(FocusNFeClient::fromConfig($config));

        $lista = $service->listar();

        $this->assertCount(2, $lista);
        $this->assertEquals('bounce@example.com', $lista[0]['email']);
        $this->assertEquals('hard_bounce', $lista[0]['motivo']);
        $this->assertEquals('mailbox_full', $lista[1]['motivo']);
    }

    public function test_emails_bloqueados_desbloquear_trata_404_como_sucesso(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/emails_bloqueados/*' => Http::response('not found', 404),
        ]);

        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new EmailsBloqueadosService(FocusNFeClient::fromConfig($config));

        // Não deve lançar — idempotente
        $service->desbloquear('ghost@example.com');
        $this->assertTrue(true);
    }

    public function test_emails_bloqueados_desbloquear_rejeita_email_invalido(): void
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new EmailsBloqueadosService(FocusNFeClient::fromConfig($config));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email inválido');

        $service->desbloquear('não-é-email');
    }

    // ─── Backups XML ──────────────────────────────────────────────────

    /* ─── Backup mensal de XMLs (pacote LOCAL, armadilha 49) ───────────── */

    /**
     * O `/v2/backups` da Focus NÃO existe (armadilha 49). Desde 12/08/2026 o pacote
     * mensal é montado localmente a partir das cópias por nota, e o serviço nem
     * recebe mais um FocusNFeClient. Os 4 testes antigos falavam de
     * `solicitar()`/`consultar()`, que morreram junto com o endpoint.
     */
    private function notaComXmlLocal(string $chave, ?string $mes = null): NotaFiscal
    {
        $emitida = $mes
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $mes)->startOfMonth()->addDays(3)
            : now();

        $nota = NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'   => $this->empresa->id,
            'unidade_id'   => $this->unidade->id,
            'tipo'         => TipoNotaFiscal::NFCe,
            'status'       => StatusNotaFiscal::Autorizada,
            'focus_ref'    => 'ref-' . $chave,
            'chave_acesso' => $chave,
            'numero'       => '1',
            'serie'        => 1,
            'valor_total'  => 100.00,
            'ambiente'     => 'homologacao',
            'emitida_em'   => $emitida,
        ]);

        Storage::put($nota->caminhoXmlLocal(), '<nfeProc><chave>' . $chave . '</chave></nfeProc>');

        return $nota;
    }

    public function test_backup_gera_zip_com_os_xmls_do_mes_e_manifest(): void
    {
        Storage::fake();

        $mes = now()->format('Y-m');
        $this->notaComXmlLocal('35260900000000000000000000000000000000000001', $mes);
        $this->notaComXmlLocal('35260900000000000000000000000000000000000002', $mes);

        $service = new BackupXmlService();
        $result = $service->gerar($this->empresa->id, $this->unidade->cnpj, $mes);

        $this->assertEquals('concluido', $result['status']);
        $this->assertEquals(2, $result['arquivos']);
        $this->assertEquals([], $result['sem_xml']);
        Storage::assertExists($result['path']);

        // O manifest é o índice que o contador confere
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open(Storage::path($result['path'])) === true);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertEquals($mes, $manifest['mes']);
        $this->assertEquals(2, $manifest['total_notas']);
        $this->assertCount(2, $manifest['notas']);
    }

    public function test_backup_de_mes_sem_nota_sai_vazio_e_nao_cria_arquivo(): void
    {
        Storage::fake();

        $service = new BackupXmlService();
        $result = $service->gerar($this->empresa->id, $this->unidade->cnpj, '2026-01');

        $this->assertEquals('vazio', $result['status']);
        $this->assertEquals(0, $result['arquivos']);
        $this->assertNull($result['path']);
        Storage::assertMissing($service->caminhoZip($this->unidade->cnpj, '2026-01'));
    }

    public function test_backup_rejeita_mes_invalido(): void
    {
        $service = new BackupXmlService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Mês inválido/u');

        $service->gerar($this->empresa->id, $this->unidade->cnpj, '2026/03');
    }

    public function test_backup_info_diz_indisponivel_antes_de_gerar_e_concluido_depois(): void
    {
        Storage::fake();

        $mes = now()->format('Y-m');
        $service = new BackupXmlService();

        $antes = $service->info($this->unidade->cnpj, $mes);
        $this->assertEquals('indisponivel', $antes['status']);
        $this->assertNull($antes['path']);

        $this->notaComXmlLocal('35260900000000000000000000000000000000000003', $mes);
        $service->gerar($this->empresa->id, $this->unidade->cnpj, $mes);

        $depois = $service->info($this->unidade->cnpj, $mes);
        $this->assertEquals('concluido', $depois['status']);
        $this->assertEquals(1, $depois['arquivos']); // manifest.json não conta
        $this->assertNotNull($depois['atualizado_em']);
    }

    public function test_backup_meses_disponiveis_retorna_12_em_ordem_decrescente(): void
    {
        $meses = (new BackupXmlService())->mesesDisponiveis();

        $this->assertCount(12, $meses);
        // O primeiro é o mês CORRENTE — o pacote em andamento é parcial de propósito
        $this->assertEquals(now()->format('Y-m'), $meses[0]);
        $this->assertEquals(now()->subMonths(11)->format('Y-m'), $meses[11]);
    }
}
