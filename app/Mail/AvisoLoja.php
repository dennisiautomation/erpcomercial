<?php

namespace App\Mail;

use App\Models\LojaOnline;
use App\Services\LojaOnline\TemaCss;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail simples da loja online (15/11/2026): título, texto e um botão.
 * Usado por: redefinir senha da conta, "chegou o produto que você pediu para
 * avisar" e o lembrete do carrinho. Sai com o nome da loja, como o do pedido.
 */
class AvisoLoja extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param array<int, string> $linhas */
    public function __construct(public int $lojaId, public string $assunto, public string $titulo, public array $linhas,
        public ?string $botao = null, public ?string $link = null, public ?string $rodape = null)
    {
    }

    public function envelope(): Envelope
    {
        $loja = LojaOnline::findOrFail($this->lojaId);
        $responder = filled($loja->opcao('email_pedidos')) ? $loja->opcao('email_pedidos') : $loja->opcao('email');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), filled($loja->opcao('email_remetente')) ? $loja->opcao('email_remetente') : $loja->nome),
            replyTo: filled($responder) ? [new Address($responder, $loja->nome)] : [],
            subject: $this->assunto,
        );
    }

    public function content(): Content
    {
        $loja = LojaOnline::findOrFail($this->lojaId);
        $tema = $loja->temaEfetivo();

        return new Content(view: 'emails.loja.aviso', with: [
            'loja' => $loja, 'titulo' => $this->titulo, 'linhas' => $this->linhas, 'botao' => $this->botao, 'link' => $this->link,
            'rodape' => $this->rodape, 'cor' => $tema['cor_primaria'], 'sobreCor' => TemaCss::sobre($tema['cor_primaria']),
        ]);
    }
}
