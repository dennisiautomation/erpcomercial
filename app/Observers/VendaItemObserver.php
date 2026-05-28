<?php

namespace App\Observers;

use App\Models\Produto;
use App\Models\VendaItem;

/**
 * Captura snapshot fiscal do produto no momento da criação do item de venda.
 * Garante que re-emissões / consultas históricas usem os dados do momento
 * da venda, não o estado corrente do produto.
 */
class VendaItemObserver
{
    public function creating(VendaItem $item): void
    {
        if (! $item->produto_id) {
            return;
        }
        // Só aplica se ainda não veio com snapshot setado pelo controller
        if ($item->snapshot_ncm !== null) {
            return;
        }
        $produto = $item->relationLoaded('produto')
            ? $item->produto
            : Produto::withoutGlobalScopes()->find($item->produto_id);

        if ($produto) {
            $item->aplicarSnapshotFiscal($produto);
        }
    }
}
