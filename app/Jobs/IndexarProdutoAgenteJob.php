<?php

namespace App\Jobs;

use App\Models\Produto;
use App\Scopes\EmpresaScope;
use App\Services\AgenteIa\IndexadorProdutos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-indexa UM produto no banco vetorial (disparado pelo ProdutoObserver
 * quando um produto de empresa com Agente IA ativo muda).
 *
 * Guarda o ID, não o model: quando o job rodar o produto pode já ter sido
 * excluído — nesse caso a resposta certa é removê-lo do índice.
 */
class IndexarProdutoAgenteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $produtoId)
    {
    }

    public function handle(IndexadorProdutos $indexador): void
    {
        $produto = Produto::withoutGlobalScope(EmpresaScope::class)
            ->withTrashed()
            ->with('categoria')
            ->find($this->produtoId);

        if (! $produto) {
            $indexador->removerProduto($this->produtoId);

            return;
        }

        $indexador->indexarProduto($produto);

        Log::channel('integracao')->info('Agente IA: produto re-indexado', [
            'produto_id' => $this->produtoId,
            'empresa_id' => $produto->empresa_id,
        ]);
    }
}
