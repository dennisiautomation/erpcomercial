<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracaoFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ConfiguracaoFiscalTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->empresa, $this->unidade] = $this->createTenant();

        $this->dono = $this->createUser($this->empresa, $this->unidade, 'dono');

        ConfiguracaoFiscal::withoutGlobalScopes()->create([
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'homologacao',
            'focus_token'         => 'token-original-123',
            'serie_nfe'           => 1,
            'serie_nfce'          => 1,
            'emissao_fiscal_ativa' => false,
            'tipo_cupom_pdv'      => 'nao_fiscal',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Tests                                                              */
    /* ------------------------------------------------------------------ */

    public function test_dono_can_view_config_fiscal(): void
    {
        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->get(route('app.configuracao-fiscal.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('app.configuracao-fiscal.edit');
        $response->assertViewHas('config');
    }

    public function test_dono_can_update_config_fiscal(): void
    {
        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->put(route('app.configuracao-fiscal.update'), [
                'ambiente'             => 'producao',
                'focus_token'          => 'novo-token-456',
                'serie_nfe'            => 2,
                'serie_nfce'           => 3,
                'csc_nfce'             => 'CSC-NOVO',
                'csc_id_nfce'          => '2',
                'emissao_fiscal_ativa' => true,
                'tipo_cupom_pdv'       => 'fiscal',
            ]);

        $response->assertRedirect(route('app.configuracao-fiscal.edit'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'ambiente'            => 'producao',
            'serie_nfe'           => 2,
            'serie_nfce'          => 3,
            'emissao_fiscal_ativa' => true,
            'tipo_cupom_pdv'      => 'fiscal',
        ]);
    }

    public function test_config_toggle_emissao_fiscal(): void
    {
        // Start with fiscal disabled, then enable
        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->put(route('app.configuracao-fiscal.update'), [
                'ambiente'             => 'homologacao',
                'emissao_fiscal_ativa' => true,
                'tipo_cupom_pdv'       => 'fiscal',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'emissao_fiscal_ativa' => true,
        ]);

        // Disable again
        $this->actingAsUser($this->dono, $this->unidade)
            ->put(route('app.configuracao-fiscal.update'), [
                'ambiente'             => 'homologacao',
                'emissao_fiscal_ativa' => false,
                'tipo_cupom_pdv'       => 'nao_fiscal',
            ]);

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'empresa_id'          => $this->empresa->id,
            'unidade_id'          => $this->unidade->id,
            'emissao_fiscal_ativa' => false,
        ]);
    }

    public function test_config_toggle_tipo_cupom_pdv(): void
    {
        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->put(route('app.configuracao-fiscal.update'), [
                'ambiente'             => 'homologacao',
                'emissao_fiscal_ativa' => true,
                'tipo_cupom_pdv'       => 'fiscal',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('configuracoes_fiscais', [
            'empresa_id'     => $this->empresa->id,
            'unidade_id'     => $this->unidade->id,
            'tipo_cupom_pdv' => 'fiscal',
        ]);
    }

    public function test_testar_conexao_with_valid_token(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse/provisorio*' => Http::response([
                'status' => 'ok',
            ], 200),
        ]);

        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->postJson(route('app.configuracao-fiscal.testar'), [
                'token'    => 'valid-token-123',
                'ambiente' => 'homologacao',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success'  => true,
            'ambiente' => 'homologacao',
        ]);
    }

    public function test_testar_conexao_with_invalid_token(): void
    {
        Http::fake([
            'homologacao.focusnfe.com.br/v2/nfse/provisorio*' => Http::response([
                'mensagem' => 'Token invalido',
            ], 403),
        ]);

        $response = $this->actingAsUser($this->dono, $this->unidade)
            ->postJson(route('app.configuracao-fiscal.testar'), [
                'token'    => 'invalid-token',
                'ambiente' => 'homologacao',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => false,
        ]);
    }
}
