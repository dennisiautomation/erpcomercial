<?php

namespace App\Mail;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso interno à IA365: uma empresa se cadastrou sozinha pelo site e está em teste. */
class NovoCadastroSite extends Mailable
{
    public function __construct(public Empresa $empresa, public User $dono) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo teste grátis pelo site · ' . ($this->empresa->nome_fantasia ?: $this->empresa->razao_social),
            replyTo: [$this->dono->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.novo-cadastro-site');
    }
}
