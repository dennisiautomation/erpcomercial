<?php

namespace Tests\Feature\Fiscal;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoNotaFiscal;
use App\Models\NotaFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class WebhookTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function createNotaPendente(string $ref = 'nfce-webhook-001'): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFCe,
            'status'            => StatusNotaFiscal::Pendente,
            'focus_ref'         => $ref,
            'focus_status'      => 'processando_autorizacao',
            'valor_total'       => 150.00,
            'natureza_operacao' => 'Venda ao Consumidor',
            'ambiente'          => 'homologacao',
        ]);
    }

    private function createNotaAutorizada(string $ref = 'nfce-webhook-002'): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create([
            'empresa_id'        => $this->empresa->id,
            'unidade_id'        => $this->unidade->id,
            'tipo'              => TipoNotaFiscal::NFe,
            'status'            => StatusNotaFiscal::Autorizada,
            'focus_ref'         => $ref,
            'focus_status'      => 'autorizado',
            'chave_acesso'      => 'NFe35190607504505000132550010000000011987654321',
            'numero'            => '50',
            'valor_total'       => 300.00,
            'natureza_operacao' => 'Venda de Mercadoria',
            'ambiente'          => 'homologacao',
            'emitida_em'        => now(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_webhook_updates_nota_status_to_autorizada(): void
    {
        $nota = $this->createNotaPendente('nfce-auth-001');

        $response = $this->postJson('/webhooks/focusnfe', [
            'ref'                       => 'nfce-auth-001',
            'status'                    => 'autorizado',
            'chave_nfe'                 => 'NFe35190607504505000132650010000000099987654321',
            'numero'                    => '99',
            'caminho_xml_nota_fiscal'   => '/arquivos/webhook-xml.xml',
            'caminho_danfe'             => '/arquivos/webhook-danfe.pdf',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'OK']);

        $nota->refresh();

        $this->assertEquals(StatusNotaFiscal::Autorizada, $nota->status);
        $this->assertEquals('autorizado', $nota->focus_status);
        $this->assertEquals('NFe35190607504505000132650010000000099987654321', $nota->chave_acesso);
        $this->assertEquals('99', $nota->numero);
        $this->assertEquals('/arquivos/webhook-xml.xml', $nota->xml_url);
        $this->assertEquals('/arquivos/webhook-danfe.pdf', $nota->danfe_url);
        $this->assertNotNull($nota->emitida_em);
    }

    public function test_webhook_updates_nota_status_to_cancelada(): void
    {
        $nota = $this->createNotaAutorizada('nfe-cancel-001');

        $response = $this->postJson('/webhooks/focusnfe', [
            'ref'                      => 'nfe-cancel-001',
            'status'                   => 'cancelado',
            'protocolo_cancelamento'   => '135200000000099',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'OK']);

        $nota->refresh();

        $this->assertEquals(StatusNotaFiscal::Cancelada, $nota->status);
        $this->assertEquals('cancelado', $nota->focus_status);
        $this->assertEquals('135200000000099', $nota->cancelamento_protocolo);
        $this->assertNotNull($nota->cancelada_em);
    }

    public function test_webhook_returns_200_for_unknown_ref(): void
    {
        $response = $this->postJson('/webhooks/focusnfe', [
            'ref'    => 'nfce-inexistente-999',
            'status' => 'autorizado',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Nota nao encontrada.']);
    }

    public function test_webhook_logs_received_data(): void
    {
        Log::shouldReceive('info')
            ->withArgs(function (string $message) {
                return str_contains($message, 'Webhook Focus NFe recebido');
            })
            ->once();

        Log::shouldReceive('warning')
            ->withArgs(function (string $message) {
                return str_contains($message, 'nota nao encontrada');
            })
            ->once();

        // Allow any other log calls
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        $this->postJson('/webhooks/focusnfe', [
            'ref'    => 'nfce-log-test-001',
            'status' => 'autorizado',
        ]);
    }
}
