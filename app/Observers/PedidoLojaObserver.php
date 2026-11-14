<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Services\LojaOnline\AvisosLoja;

/**
 * Pedido da loja online mudou de situação (pagamento confirmado pelo webhook,
 * faturado ou cancelado no ERP…) → avisa o comprador por e-mail (14/11/2026).
 * Pedido que não nasceu na loja online (sem loja_token) passa direto.
 */
class PedidoLojaObserver
{
    public function updated(Pedido $pedido): void
    {
        if ($pedido->loja_token && $pedido->wasChanged('status')) {
            AvisosLoja::situacaoMudou($pedido);
        }
    }
}
