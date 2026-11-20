<?php

namespace App\Mail;

use App\Models\Plano;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Link de confirmação do teste grátis pedido no site. Enviado na hora (sem fila). */
class ConfirmarCadastroSite extends Mailable
{
    public function __construct(public string $nome, public Plano $plano, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu e-mail para começar o teste grátis · ERP Comercial');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmar-cadastro-site');
    }
}
