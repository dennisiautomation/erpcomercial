<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\NFeEvento;
use App\Models\NotaFiscal;
use App\Services\FocusNFe\AtorInteressadoService;
use App\Services\FocusNFe\FocusNFeClient;
use App\Services\FocusNFe\InsucessoEntregaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class NFeEventosTest extends TestCase
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
            'focus_token' => 'token-evt',
            'emissao_fiscal_ativa' => true,
            'emite_nfe' => true,
        ]);
    }

    private function criarNotaAutorizada(string $ref = 'nfe-evt-001'): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'tipo' => TipoNotaFiscal::NFe,
            'status' => StatusNotaFiscal::Autorizada,
            'focus_ref' => $ref,
            'chave_acesso' => str_repeat('1', 44),
            'numero' => '42',
            'valor_total' => 500,
            'natureza_operacao' => 'Venda',
            'ambiente' => 'homologacao',
            'emitida_em' => now(),
        ]);
    }

    private function service(string $class)
    {
        $config = ConfiguracaoFiscal::withoutGlobalScopes()->first();
        return new $class(FocusNFeClient::fromConfig($config));
    }

    // ─── Ator Interessado ─────────────────────────────────────────────

    public function test_ator_interessado_registra_e_marca_autorizado(): void
    {
        $nota = $this->criarNotaAutorizada();

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/ator_interessado' => Http::response([
                'status' => 'autorizado',
                'numero_protocolo' => '135200000099999',
            ], 200),
        ]);

        $evento = $this->service(AtorInteressadoService::class)
            ->registrar($nota, [
                'cnpj_ator' => '12345678000199',
                'tipo_ator' => 1,
                'razao_social_ator' => 'Transportadora Veloz',
            ]);

        $this->assertEquals(NFeEvento::STATUS_AUTORIZADO, $evento->status);
        $this->assertEquals('135200000099999', $evento->protocolo);
        $this->assertEquals(1, $evento->sequencia);
        $this->assertEquals('12345678000199', $evento->dados['cnpj_ator']);
    }

    public function test_ator_interessado_incrementa_sequencia(): void
    {
        $nota = $this->criarNotaAutorizada();

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/ator_interessado' => Http::response(['status' => 'autorizado'], 200),
        ]);

        $svc = $this->service(AtorInteressadoService::class);
        $e1 = $svc->registrar($nota, ['cnpj_ator' => '11111111000111', 'tipo_ator' => 1]);
        $e2 = $svc->registrar($nota, ['cnpj_ator' => '22222222000122', 'tipo_ator' => 3]);

        $this->assertEquals(1, $e1->sequencia);
        $this->assertEquals(2, $e2->sequencia);
    }

    public function test_ator_interessado_rejeita_cnpj_invalido(): void
    {
        $nota = $this->criarNotaAutorizada();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CNPJ do ator interessado inválido');

        $this->service(AtorInteressadoService::class)
            ->registrar($nota, ['cnpj_ator' => '123', 'tipo_ator' => 1]);
    }

    public function test_ator_interessado_exige_nota_autorizada(): void
    {
        $nota = NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'tipo' => TipoNotaFiscal::NFe,
            'status' => StatusNotaFiscal::Pendente,
            'focus_ref' => 'nfe-pendente',
            'valor_total' => 100,
            'natureza_operacao' => 'x',
            'ambiente' => 'homologacao',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NF-e autorizada');

        $this->service(AtorInteressadoService::class)
            ->registrar($nota, ['cnpj_ator' => '12345678000199', 'tipo_ator' => 1]);
    }

    public function test_ator_interessado_marca_rejeitado_quando_focus_recusa(): void
    {
        $nota = $this->criarNotaAutorizada();

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/ator_interessado' => Http::response([
                'status' => 'erro_autorizacao',
                'mensagem' => 'CNPJ do autorizado já consta como ator',
            ], 422),
        ]);

        $evento = $this->service(AtorInteressadoService::class)
            ->registrar($nota, ['cnpj_ator' => '12345678000199', 'tipo_ator' => 1]);

        $this->assertEquals(NFeEvento::STATUS_REJEITADO, $evento->status);
        $this->assertStringContainsString('já consta', $evento->mensagem_retorno);
    }

    // ─── Insucesso de Entrega ─────────────────────────────────────────

    public function test_insucesso_entrega_registra_e_autoriza(): void
    {
        $nota = $this->criarNotaAutorizada('nfe-insuc-001');

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/insucesso_entrega' => Http::response([
                'status' => 'autorizado',
                'numero_protocolo' => '135300000011111',
            ], 200),
        ]);

        $evento = $this->service(InsucessoEntregaService::class)
            ->registrar($nota, [
                'motivo' => 2,
                'justificativa' => 'Destinatário recusou o recebimento',
                'latitude' => -23.5505,
                'longitude' => -46.6333,
            ]);

        $this->assertEquals(NFeEvento::STATUS_AUTORIZADO, $evento->status);
        $this->assertEquals(2, $evento->dados['motivo']);
        $this->assertEquals('Destinatário recusou o recebimento', $evento->dados['justificativa']);
    }

    public function test_insucesso_entrega_exige_justificativa_quando_motivo_outros(): void
    {
        $nota = $this->criarNotaAutorizada();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/justificativa/i');

        $this->service(InsucessoEntregaService::class)
            ->registrar($nota, ['motivo' => 4, 'justificativa' => 'curta']);
    }

    public function test_insucesso_entrega_rejeita_motivo_invalido(): void
    {
        $nota = $this->criarNotaAutorizada();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Motivo inválido');

        $this->service(InsucessoEntregaService::class)
            ->registrar($nota, ['motivo' => 99]);
    }

    // ─── Endpoints HTTP ───────────────────────────────────────────────

    public function test_endpoint_ator_interessado_retorna_flash_sucesso(): void
    {
        $nota = $this->criarNotaAutorizada('nfe-http-ator');

        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfe/*/ator_interessado' => Http::response([
                'status' => 'autorizado',
            ], 200),
        ]);

        $user = $this->createUser($this->empresa, $this->unidade, 'dono');

        $this->actingAsUser($user, $this->unidade)
            ->from('/app/notas-fiscais/' . $nota->id)
            ->post(route('app.notas-fiscais.ator-interessado', $nota), [
                'cnpj_ator' => '99.999.999/0001-91',
                'tipo_ator' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('nfe_eventos', 1);
    }

    // ─── Produto Importação ───────────────────────────────────────────

    public function test_produto_eh_importado_detecta_origens_corretas(): void
    {
        $produto = $this->createProduto($this->empresa, ['origem' => '1']);
        $this->assertTrue($produto->ehImportado());

        $produto2 = $this->createProduto($this->empresa, ['origem' => '0']);
        $this->assertFalse($produto2->ehImportado());

        $produto3 = $this->createProduto($this->empresa, ['origem' => '6']);
        $this->assertTrue($produto3->ehImportado());
    }

    public function test_campos_importacao_persistem_em_produto(): void
    {
        $produto = $this->createProduto($this->empresa, [
            'origem' => '1',
            'di_numero' => '2024/1234567-8',
            'di_data' => '2024-12-10',
            'di_uf_desembaraco' => 'SP',
            'di_valor_afrmm' => 150.75,
        ]);

        $this->assertEquals('2024/1234567-8', $produto->di_numero);
        $this->assertEquals('2024-12-10', $produto->di_data->toDateString());
        $this->assertEquals(150.75, (float) $produto->di_valor_afrmm);
    }
}
