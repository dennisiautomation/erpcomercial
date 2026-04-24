<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracaoFiscal;
use App\Models\NFSeRecebida;
use App\Services\FocusNFe\BackupXmlService;
use App\Services\FocusNFe\EmailsBloqueadosService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\NFSesRecebidasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Sprint3Test extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_backup_solicitar_aceita_status_202(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/backups' => Http::response([
                'status' => 'processando',
            ], 202),
        ]);

        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new BackupXmlService(FocusNFeClient::fromConfig($config));

        $result = $service->solicitar('2026-03');

        $this->assertEquals('processando', $result['status']);
        $this->assertEquals('2026-03', $result['mes']);
    }

    public function test_backup_rejeita_mes_invalido(): void
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new BackupXmlService(FocusNFeClient::fromConfig($config));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Mês inválido/u');

        $service->solicitar('2026/03');
    }

    public function test_backup_meses_disponiveis_retorna_12_em_ordem_decrescente(): void
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new BackupXmlService(FocusNFeClient::fromConfig($config));

        $meses = $service->mesesDisponiveis();

        $this->assertCount(12, $meses);
        // Primeiro item é o mês anterior ao atual
        $this->assertEquals(now()->subMonth()->format('Y-m'), $meses[0]);
        // Ordem decrescente
        $this->assertEquals(now()->subMonths(12)->format('Y-m'), $meses[11]);
    }

    public function test_backup_consultar_retorna_indisponivel_quando_focus_nega(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/backups*' => Http::response('erro', 500),
        ]);

        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        $service = new BackupXmlService(FocusNFeClient::fromConfig($config));

        $result = $service->consultar('2026-02');

        $this->assertEquals('indisponivel', $result['status']);
        $this->assertNull($result['url']);
    }
}
