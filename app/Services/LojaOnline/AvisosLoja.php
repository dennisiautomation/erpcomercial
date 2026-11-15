<?php

namespace App\Services\LojaOnline;

use App\Enums\StatusPedido;
use App\Mail\PedidoLoja;
use App\Models\LojaOnline;
use App\Models\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por e-mail do pedido da loja online (14/11/2026) — ponto único.
 *
 * - Ao fechar a compra: "recebemos o seu pedido" para o comprador (se deu e-mail)
 *   e "pedido novo" para a loja (no e-mail cadastrado em Avisos por e-mail).
 * - Quando o pedido muda de situação NO ERP (ou pelo pagamento): confirmado,
 *   pronto/a caminho, entregue, cancelado — disparado pelo PedidoLojaObserver.
 *
 * Cada aviso tem a sua chave em Loja online → Avisos por e-mail. Falha de envio
 * nunca derruba o pedido: fica no log de integração.
 */
class AvisosLoja
{
    /** Situação do pedido → aviso ao comprador e a chave que o liga. */
    private const POR_STATUS = [
        'confirmado' => ['confirmado', 'email_cliente_confirmado'],
        'faturado' => ['faturado', 'email_cliente_andamento'],
        'entregue' => ['entregue', 'email_cliente_andamento'],
        'cancelado' => ['cancelado', 'email_cliente_andamento'],
    ];

    public static function pedidoFechado(LojaOnline $loja, Pedido $pedido): void
    {
        if ($loja->opcao('email_cliente_recebido')) {
            self::aoCliente($loja, $pedido, 'recebido');
        }
        if ($loja->opcao('email_loja_novo') && filled($loja->opcao('email_pedidos'))) {
            self::enviar($loja->opcao('email_pedidos'), $loja, $pedido, 'loja_novo');
        }
    }

    public static function situacaoMudou(Pedido $pedido): void
    {
        if (! $pedido->loja_token) {
            return;
        }
        $status = $pedido->status instanceof StatusPedido ? $pedido->status->value : (string) $pedido->status;
        [$tipo, $chave] = self::POR_STATUS[$status] ?? [null, null];
        $loja = $tipo ? LojaOnline::where('empresa_id', $pedido->empresa_id)->where('ativo', true)->first() : null;
        if ($loja && $loja->opcao($chave)) {
            self::aoCliente($loja, $pedido, $tipo);
        }
    }

    private static function aoCliente(LojaOnline $loja, Pedido $pedido, string $tipo): void
    {
        $email = DB::table('clientes')->where('id', $pedido->cliente_id)->value('email');
        if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::enviar($email, $loja, $pedido, $tipo);
        }
    }

    public static function enviar(string $para, LojaOnline $loja, Pedido $pedido, string $tipo): bool
    {
        try {
            Mail::to($para)->queue(new PedidoLoja((int) $pedido->id, (int) $loja->id, $tipo));

            return true;
        } catch (\Throwable $e) {
            Log::channel('integracao')->warning('Loja online: e-mail do pedido não saiu', [
                'pedido_id' => $pedido->id, 'tipo' => $tipo, 'erro' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
