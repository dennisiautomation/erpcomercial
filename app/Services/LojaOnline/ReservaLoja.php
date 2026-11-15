<?php

namespace App\Services\LojaOnline;

use App\Enums\CanalVenda;
use App\Enums\StatusPedido;
use App\Models\LojaOnline;
use Illuminate\Support\Facades\DB;

/**
 * Reserva de estoque da loja online (15/11/2026).
 *
 * Onde cada coisa vive: o estoque continua baixando SÓ no faturamento do ERP.
 * A reserva não grava nada — é uma conta: o que os pedidos da loja online já
 * prometeram e ainda não foi faturado sai do saldo que a VITRINE mostra.
 *
 *   - pedido PAGO/confirmado e ainda não faturado → reserva até faturar/cancelar;
 *   - pedido aguardando pagamento → reserva por `reservar_minutos` (padrão 30;
 *     0 desliga) para o comprador que está com o QR aberto não perder a peça.
 *
 * Faturou → o estoque baixa de verdade e o pedido sai da conta sozinho.
 * O PDV da loja física não enxerga esta reserva (ver Loja online → Pedidos).
 */
class ReservaLoja
{
    /** @return array<int, float> produto_id => quantidade reservada */
    public static function porProduto(LojaOnline $loja, ?array $produtoIds = null): array
    {
        if ($produtoIds === []) {
            return [];
        }
        $minutos = max(0, (int) $loja->opcao('reservar_minutos'));

        $q = DB::table('pedido_itens as i')
            ->join('pedidos as p', 'p.id', '=', 'i.pedido_id')
            ->where('p.empresa_id', $loja->empresa_id)
            ->where('p.canal', CanalVenda::Online->value)
            ->whereNotNull('p.loja_token')
            ->whereNull('p.deleted_at')->whereNull('i.deleted_at')
            ->where(function ($w) use ($minutos) {
                $w->where('p.status', StatusPedido::Confirmado->value);
                if ($minutos > 0) {
                    $w->orWhere(fn ($r) => $r->where('p.status', StatusPedido::Rascunho->value)
                        ->where('p.created_at', '>=', now()->subMinutes($minutos)));
                }
            });
        if ($produtoIds !== null) {
            $q->whereIn('i.produto_id', $produtoIds);
        }

        return $q->groupBy('i.produto_id')->selectRaw('i.produto_id, SUM(i.quantidade) as qtd')
            ->pluck('qtd', 'produto_id')->map(fn ($v) => (float) $v)->all();
    }
}
