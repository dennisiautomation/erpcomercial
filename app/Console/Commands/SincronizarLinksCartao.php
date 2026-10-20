<?php

namespace App\Console\Commands;

use App\Services\Pagamento\CartaoPedidoService;
use Illuminate\Console\Command;

/**
 * Rede de segurança do webhook da InfinitePay (25/09/2026): consulta os
 * links abertos dos últimos 3 dias em payment_check e confirma pagamento que
 * o webhook tenha perdido. Agendada a cada 15 min em routes/console.php.
 * (O Asaas não precisa: o webhook dele é autenticado e re-tentado por lá.)
 */
class SincronizarLinksCartao extends Command
{
    protected $signature = 'agente:cartao-sincronizar';

    protected $description = 'Confere na InfinitePay os links de pagamento abertos do Agente IA';

    public function handle(CartaoPedidoService $cartao): int
    {
        $r = $cartao->sincronizarInfinitePay();

        $this->info("InfinitePay: {$r['consultados']} consultados, {$r['confirmados']} confirmados, {$r['falhas']} falhas.");

        return self::SUCCESS;
    }
}
