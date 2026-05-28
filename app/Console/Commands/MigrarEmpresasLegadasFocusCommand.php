<?php

namespace App\Console\Commands;

use App\Jobs\ProvisionarEmpresaFocusJob;
use App\Models\ConfiguracaoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Services\FocusNFe\FocusEmpresaService;
use App\Services\FocusNFe\FocusNFeClient;
use Illuminate\Console\Command;

/**
 * Migra empresas que estão em modelo "legado" (focus_token preenchido
 * manualmente, sem focus_empresa_id) para o modelo "revenda"
 * (auto-provisionadas via master token).
 *
 * Por unidade: chama POST/PUT /v2/empresas + sincroniza webhooks.
 * Idempotente: pode rodar quantas vezes quiser.
 *
 * Use --sync para executar imediatamente (sem fila), ou sem flag
 * para despachar o job e voltar pro shell.
 */
class MigrarEmpresasLegadasFocusCommand extends Command
{
    protected $signature = 'fiscal:migrar-empresas-legadas
                            {--empresa= : ID específico (default: todas)}
                            {--sync : executa síncrono em vez de despachar job}
                            {--dry-run : só lista o que seria feito}';

    protected $description = 'Provisiona na Focus NFe as empresas que ainda usam token manual';

    public function handle(): int
    {
        if (! FocusNFeClient::masterDisponivel()) {
            $this->error('FOCUS_MASTER_TOKEN não configurado — nada a fazer.');
            return self::FAILURE;
        }

        $query = ConfiguracaoFiscal::withoutGlobalScopes()
            ->whereNull('focus_empresa_id');

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $configs = $query->get();

        $this->info("Encontradas {$configs->count()} unidade(s) para migrar.");

        if ($this->option('dry-run')) {
            foreach ($configs as $c) {
                $unidade = Unidade::withoutGlobalScopes()->find($c->unidade_id);
                $this->line("  • empresa #{$c->empresa_id} unidade #{$c->unidade_id} ({$unidade?->nome})");
            }
            return self::SUCCESS;
        }

        $sync = $this->option('sync');
        $sucesso = 0;
        $falha = 0;

        foreach ($configs as $config) {
            $empresa = Empresa::find($config->empresa_id);
            $unidade = Unidade::withoutGlobalScopes()->find($config->unidade_id);
            if (! $empresa || ! $unidade) continue;

            $flags = [
                'habilita_nfe' => (bool) $config->emite_nfe,
                'habilita_nfce' => (bool) $config->emite_nfce,
                'habilita_nfse' => (bool) $config->emite_nfse,
                'habilita_manifestacao' => true,
            ];

            // Se não habilitou nenhum, ainda assim cria com defaults seguros.
            if (! array_filter($flags, fn ($v) => $v && $v !== 'habilita_manifestacao')) {
                $flags['habilita_nfce'] = true;
            }

            if ($sync) {
                try {
                    (new ProvisionarEmpresaFocusJob(
                        empresaId: $empresa->id,
                        unidadeId: $unidade->id,
                        flags: $flags,
                    ))->handle();
                    $this->info("  ✓ {$empresa->razao_social} — {$unidade->nome}");
                    $sucesso++;
                } catch (\Throwable $e) {
                    $this->error("  ✗ {$empresa->razao_social} — {$unidade->nome}: " . $e->getMessage());
                    $falha++;
                }
            } else {
                ProvisionarEmpresaFocusJob::dispatch(
                    empresaId: $empresa->id,
                    unidadeId: $unidade->id,
                    flags: $flags,
                );
                $this->line("  ↻ despachado: {$empresa->razao_social} — {$unidade->nome}");
                $sucesso++;
            }
        }

        $this->newLine();
        $this->info("✓ {$sucesso} processado(s) · ✗ {$falha} falha(s)");
        return self::SUCCESS;
    }
}
