<?php

namespace App\Mail;

use App\Models\SolicitacaoDemonstracao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail enviado a cada nova solicitação de demonstração vinda da landing.
 * Vai para o time comercial, com o próprio cliente em cópia (CC).
 */
class NovaSolicitacaoDemonstracao extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitacaoDemonstracao $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nova demonstração · ' . $this->lead->empresa,
            replyTo: [$this->lead->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.nova-demonstracao');
    }
}
