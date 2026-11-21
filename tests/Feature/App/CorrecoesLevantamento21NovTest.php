<?php

namespace Tests\Feature\App;

use App\Enums\StatusNotaFiscal;
use App\Models\ConfiguracaoFiscal;
use App\Models\NotaFiscal;
use App\Models\OrdemServico;
use App\Models\User;
use App\Models\Venda;
use App\Services\FocusNFe\NFeService;
use App\Services\FocusNFe\NFSeDispatcher;
use App\Services\FocusNFe\NFSeNacionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Os 7 defeitos do levantamento de 21/11/2026 que o Dennis mandou corrigir.
 * Cada teste falhava antes da correção.
 */
class CorrecoesLevantamento21NovTest extends TestCase
{
    use RefreshDatabase, CreatesTestData;


    protected function setUp(): void
    {
        parent::setUp();
        [$this->empresa, $this->unidade] = $this->createTenant();
    }

    private function config(array $extra = []): ConfiguracaoFiscal
    {
        return ConfiguracaoFiscal::withoutGlobalScopes()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'ambiente' => 'homologacao',
            'focus_token' => 'tok-teste',
            'emissao_fiscal_ativa' => true,
            'emite_nfe' => true,
            'emite_nfce' => true,
            'emite_nfse' => true,
        ], $extra));
    }

    private function nota(array $extra = []): NotaFiscal
    {
        return NotaFiscal::withoutGlobalScopes()->create(array_merge([
            'empresa_id' => $this->empresa->id,
            'unidade_id' => $this->unidade->id,
            'tipo' => 'nfe',
            'numero' => 10,
            'serie' => '1',
            'valor_total' => 100,
            'status' => 'autorizada',
            'focus_ref' => 'ref-teste-1',
            'ambiente' => 'homologacao',
        ], $extra));
    }

    // ─── 1. Inutilizar ──────────────────────────────────────────────────

    public function test_inutilizar_envia_os_numeros_e_so_confirma_com_a_sefaz(): void
    {
        $this->config();
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        Http::fake(['*/v2/nfce/inutilizacao*' => Http::response(['status' => 'autorizado', 'protocolo_sefaz' => '135000'], 200)]);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.notas-fiscais.inutilizar.store'), [
            'tipo' => 'nfce', 'serie' => 1, 'numero_inicial' => 5, 'numero_final' => 7,
            'justificativa' => 'Numeracao pulada por falha no sistema anterior',
        ])->assertRedirect(route('app.notas-fiscais.index'))->assertSessionHas('success');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/nfce/inutilizacao')
            && $r['serie'] === '1' && $r['numero_inicial'] === '5' && $r['numero_final'] === '7');
    }

    public function test_inutilizar_recusado_pela_sefaz_mostra_erro(): void
    {
        $this->config();
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        Http::fake(['*/v2/nfe/inutilizacao*' => Http::response(['status' => 'erro_autorizacao', 'mensagem_sefaz' => 'Rejeicao: numero ja utilizado'], 200)]);

        $this->actingAsUser($dono, $this->unidade)->from(route('app.notas-fiscais.inutilizar'))
            ->post(route('app.notas-fiscais.inutilizar.store'), [
                'tipo' => 'nfe', 'serie' => 1, 'numero_inicial' => 5, 'numero_final' => 5,
                'justificativa' => 'Numeracao pulada por falha no sistema anterior',
            ])->assertSessionHas('error', fn ($m) => str_contains($m, 'numero ja utilizado'))
            ->assertSessionMissing('success');
    }

    // ─── 2. Cancelar NF-e / NFS-e só com confirmação ────────────────────

    public function test_cancelar_nfe_recusado_nao_marca_cancelada(): void
    {
        $this->config();
        $nota = $this->nota();
        Http::fake(['*/v2/nfe/*' => Http::response(['status' => 'erro_cancelamento', 'mensagem_sefaz' => 'Rejeicao 501: Prazo de cancelamento superior ao previsto'], 200)]);

        try {
            NFeService::forUnidade($this->unidade)->cancelar($nota, 'Cliente desistiu da compra feita');
            $this->fail('deveria recusar');
        } catch (\App\Exceptions\NotaFiscalCancelException $e) {
            $this->assertStringContainsString('prazo', mb_strtolower($e->getMessage()));
        }
        $this->assertSame(StatusNotaFiscal::Autorizada, $nota->fresh()->status);
    }

    public function test_cancelar_nfe_confirmado_marca_cancelada(): void
    {
        $this->config();
        $nota = $this->nota();
        Http::fake(['*/v2/nfe/*' => Http::response(['status' => 'cancelado', 'protocolo' => '999'], 200)]);

        NFeService::forUnidade($this->unidade)->cancelar($nota, 'Cliente desistiu da compra feita');
        $this->assertSame(StatusNotaFiscal::Cancelada, $nota->fresh()->status);
    }

    public function test_cancelar_nfse_pela_tela_com_recusa_mostra_erro(): void
    {
        $this->config();
        $nota = $this->nota(['tipo' => 'nfse', 'focus_ref' => 'nfse-ref-1']);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        Http::fake(['*/v2/nfse/*' => Http::response(['status' => 'erro_cancelamento', 'mensagem' => 'Nota ja tem guia paga'], 200)]);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.notas-fiscais.cancelar', $nota), ['justificativa' => 'Servico nao foi prestado ao cliente'])
            ->assertSessionHas('error')->assertSessionMissing('success');
        $this->assertSame(StatusNotaFiscal::Autorizada, $nota->fresh()->status);
    }

    // ─── 3. NFS-e Nacional ──────────────────────────────────────────────

    public function test_nfse_nacional_usa_o_codigo_da_configuracao_fiscal(): void
    {
        $config = $this->config(['nfse_padrao' => 'nacional', 'nfse_item_lista_servico' => '01.07']);
        Http::fake(['*/v2/nfse_nacional*' => Http::response(['status' => 'processando_autorizacao'], 202)]);

        $nota = NFSeDispatcher::forConfig($config)->emitir([
            'descricao' => 'Suporte tecnico mensal', 'valor_servico' => 300, 'aliquota_iss' => 2,
        ], $this->createCliente($this->empresa));

        $this->assertStringStartsWith('nfse-nac-', $nota->focus_ref);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/nfse_nacional') && ($r['servico']['item_lista_servico'] ?? null) === '01.07');
    }

    public function test_consulta_e_cancelamento_de_nfse_nacional_vao_ao_endpoint_nacional(): void
    {
        $this->config(['nfse_padrao' => 'nacional']);
        $nota = $this->nota(['tipo' => 'nfse', 'focus_ref' => 'nfse-nac-1-123']);
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        Http::fake(['*/v2/nfse_nacional/*' => Http::response(['status' => 'cancelado'], 200)]);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.notas-fiscais.cancelar', $nota), ['justificativa' => 'Servico nao foi prestado ao cliente'])
            ->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v2/nfse_nacional/nfse-nac-1-123'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v2/nfse/'));
    }

    // ─── 4. Funcionário desativado ──────────────────────────────────────

    public function test_funcionario_desativado_nao_entra(): void
    {
        $this->createUser($this->empresa, $this->unidade, 'vendedor', ['email' => 'saiu@teste.com', 'password' => bcrypt('Senha@12345'), 'status' => 'inativo']);

        $this->post('/login', ['email' => 'saiu@teste.com', 'password' => 'Senha@12345'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_funcionario_desativado_com_sessao_aberta_cai_fora(): void
    {
        $u = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $this->actingAsUser($u, $this->unidade)->get(route('app.dashboard'))->assertOk();
        $u->update(['status' => 'inativo']);

        $this->actingAsUser($u->fresh(), $this->unidade)->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ─── 5. Gerente não cria nem mexe em Dono ───────────────────────────

    public function test_gerente_nao_cria_dono_nem_se_promove(): void
    {
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');
        $dados = ['name' => 'Novo Dono', 'email' => 'novodono@teste.com', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345', 'perfil' => 'dono', 'status' => 'ativo'];

        $this->actingAsUser($gerente, $this->unidade)->post(route('app.funcionarios.store'), $dados)->assertForbidden();
        $this->assertFalse(User::withoutGlobalScopes()->where('email', 'novodono@teste.com')->exists());

        $this->actingAsUser($gerente, $this->unidade)->put(route('app.funcionarios.update', $gerente), [
            'name' => $gerente->name, 'email' => $gerente->email, 'perfil' => 'admin', 'status' => 'ativo',
        ])->assertForbidden();
        $this->assertSame('gerente', $gerente->fresh()->perfil->value);
    }

    public function test_gerente_nao_edita_nem_desativa_o_dono(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');

        $this->actingAsUser($gerente, $this->unidade)->get(route('app.funcionarios.edit', $dono))->assertForbidden();
        $this->actingAsUser($gerente, $this->unidade)->delete(route('app.funcionarios.destroy', $dono))->assertForbidden();
        $this->assertSame('ativo', (string) $dono->fresh()->status);
    }

    public function test_gerente_segue_criando_vendedor_e_dono_edita_o_proprio_cadastro(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $gerente = $this->createUser($this->empresa, $this->unidade, 'gerente');

        $this->actingAsUser($gerente, $this->unidade)->post(route('app.funcionarios.store'), [
            'name' => 'Vendedora', 'email' => 'vend@teste.com', 'password' => 'Senha@12345', 'password_confirmation' => 'Senha@12345', 'perfil' => 'vendedor', 'status' => 'ativo',
        ])->assertRedirect(route('app.funcionarios.index'));

        $this->actingAsUser($dono, $this->unidade)->put(route('app.funcionarios.update', $dono), [
            'name' => 'Dono Renomeado', 'email' => $dono->email, 'perfil' => 'dono', 'status' => 'ativo',
        ])->assertRedirect(route('app.funcionarios.index'));
        $this->assertSame('Dono Renomeado', $dono->fresh()->name);
    }

    // ─── 6. Botão Trocar / Devolver ─────────────────────────────────────

    public function test_venda_concluida_mostra_trocar_devolver(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $venda = Venda::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'vendedor_id' => $dono->id,
            'numero' => 1, 'subtotal' => 10, 'total' => 10, 'status' => 'concluida', 'forma_pagamento' => 'dinheiro',
        ]);

        $this->actingAsUser($dono, $this->unidade)->get(route('app.vendas.show', $venda))
            ->assertOk()->assertSee('Trocar / Devolver');
    }

    // ─── 7. OS vira venda uma vez só ────────────────────────────────────

    public function test_os_nao_vira_venda_duas_vezes(): void
    {
        $dono = $this->createUser($this->empresa, $this->unidade, 'dono');
        $this->openCaixa($this->empresa, $this->unidade, $dono);
        $os = OrdemServico::withoutGlobalScopes()->create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id,
            'cliente_id' => $this->createCliente($this->empresa)->id, 'vendedor_id' => $dono->id,
            'numero' => 1, 'equipamento' => 'Notebook', 'defeito_relatado' => 'Nao liga',
            'status' => 'concluida', 'valor_produtos' => 0, 'valor_servicos' => 80, 'desconto' => 0, 'total' => 80,
        ]);

        $this->actingAsUser($dono, $this->unidade)->post(route('app.ordens-servico.converter-venda', $os), ['forma_pagamento' => 'dinheiro'])
            ->assertSessionHas('success');
        $this->actingAsUser($dono, $this->unidade)->post(route('app.ordens-servico.converter-venda', $os), ['forma_pagamento' => 'dinheiro'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'já virou a venda'));

        $this->assertSame(1, Venda::withoutGlobalScopes()->where('empresa_id', $this->empresa->id)->where('observacoes', 'like', 'Gerada a partir da OS #1%')->count());
        $this->assertNotNull($os->fresh()->venda_id);
    }
}
