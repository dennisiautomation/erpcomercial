<?php

namespace App\Observers;

use App\Jobs\IndexarProdutoAgenteJob;
use App\Models\AgenteIaConfig;
use App\Models\Produto;

/**
 * Mantém o índice vetorial do Agente IA em dia: qualquer mudança em produto
 * de empresa com o módulo ATIVO vira um job de re-indexação na fila.
 *
 * Empresas sem Agente IA não geram job nenhum — custo zero para quem não usa.
 */
class ProdutoObserver
{
    public function saved(Produto $produto): void
    {
        $this->reindexarSeAtivo($produto);
    }

    public function deleted(Produto $produto): void
    {
        $this->reindexarSeAtivo($produto);
    }

    public function restored(Produto $produto): void
    {
        $this->reindexarSeAtivo($produto);
    }

    private function reindexarSeAtivo(Produto $produto): void
    {
        if (! AgenteIaConfig::ativaPara($produto->empresa_id)) {
            return;
        }

        IndexarProdutoAgenteJob::dispatch($produto->id);
    }
}
