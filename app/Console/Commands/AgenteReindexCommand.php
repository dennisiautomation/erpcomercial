<?php

namespace App\Console\Commands;

use App\Models\AgenteIaConfig;
use App\Services\AgenteIa\IndexadorProdutos;
use Illuminate\Console\Command;

/**
 * Reconstrói o índice vetorial do Agente IA a partir do MySQL.
 *
 *   php artisan agente:reindex 4        # só a empresa 4
 *   php artisan agente:reindex --all    # todas com o módulo ativo
 *
 * Seguro rodar quantas vezes quiser (upsert + poda). É o plano de
 * recuperação se o volume do erp-com-vector for perdido.
 */
class AgenteReindexCommand extends Command
{
    protected $signature = 'agente:reindex {empresa? : ID da empresa} {--all : Todas as empresas com Agente IA ativo}';

    protected $description = 'Reconstrói o índice de busca semântica do Agente IA';

    public function handle(IndexadorProdutos $indexador): int
    {
        $empresaId = $this->argument('empresa');

        if (! $empresaId && ! $this->option('all')) {
            $this->error('Informe o ID da empresa ou use --all.');

            return self::FAILURE;
        }

        $configs = AgenteIaConfig::where('ativo', true)
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get();

        if ($configs->isEmpty()) {
            $this->warn('Nenhuma empresa com Agente IA ativo encontrada.');

            return self::FAILURE;
        }

        foreach ($configs as $config) {
            $this->info("Indexando empresa {$config->empresa_id}...");
            $total = $indexador->indexarEmpresa($config->empresa_id);
            $this->info("  → {$total} produtos indexados.");
        }

        return self::SUCCESS;
    }
}
