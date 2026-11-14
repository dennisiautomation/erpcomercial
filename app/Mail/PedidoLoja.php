<?php

namespace App\Mail;

use App\Models\LojaOnline;
use App\Models\Pedido;
use App\Services\LojaOnline\TemaCss;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * E-mails do pedido da loja online (14/11/2026).
 *
 * $tipo: recebido | confirmado | faturado | entregue | cancelado (para o comprador)
 *        loja_novo (para a loja). Sai com o nome da LOJA como remetente e a
 *        resposta vai para o e-mail da loja; o endereço de envio é o da plataforma.
 */
class PedidoLoja extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public int $pedidoId, public int $lojaId, public string $tipo)
    {
    }

    public static function assunto(string $tipo, int $numero, string $loja): string
    {
        return match ($tipo) {
            'loja_novo' => "Novo pedido #{$numero} na loja online",
            'confirmado' => "Pedido #{$numero} confirmado — {$loja}",
            'faturado' => "Seu pedido #{$numero} está pronto — {$loja}",
            'entregue' => "Pedido #{$numero} entregue — {$loja}",
            'cancelado' => "Pedido #{$numero} cancelado — {$loja}",
            default => "Recebemos o seu pedido #{$numero} — {$loja}",
        };
    }

    public function envelope(): Envelope
    {
        $loja = LojaOnline::findOrFail($this->lojaId);
        $numero = (int) DB::table('pedidos')->where('id', $this->pedidoId)->value('numero');
        $responder = filled($loja->opcao('email_pedidos')) ? $loja->opcao('email_pedidos') : $loja->opcao('email');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), filled($loja->opcao('email_remetente')) ? $loja->opcao('email_remetente') : $loja->nome),
            replyTo: filled($responder) && $this->tipo !== 'loja_novo' ? [new Address($responder, $loja->nome)] : [],
            subject: self::assunto($this->tipo, $numero, $loja->nome),
        );
    }

    public function content(): Content
    {
        $loja = LojaOnline::findOrFail($this->lojaId);
        $pedido = Pedido::withoutGlobalScopes()->where('empresa_id', $loja->empresa_id)->findOrFail($this->pedidoId);
        $tema = $loja->temaEfetivo();

        return new Content(view: 'emails.loja.pedido', with: [
            'loja' => $loja,
            'pedido' => $pedido,
            'tipo' => $this->tipo,
            'itens' => DB::table('pedido_itens')->where('pedido_id', $pedido->id)->whereNull('deleted_at')->orderBy('id')->get(['descricao', 'quantidade', 'preco_unitario', 'total']),
            'cliente' => DB::table('clientes')->where('id', $pedido->cliente_id)->first(),
            'unidade' => $loja->unidade,
            'link' => rtrim($loja->enderecoPublico(), '/') . '/pedido/' . $pedido->loja_token,
            'cor' => $tema['cor_primaria'],
            'sobreCor' => TemaCss::sobre($tema['cor_primaria']),
        ]);
    }
}
