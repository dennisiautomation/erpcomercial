<?php

namespace App\Mail;

use App\Enums\Perfil;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de boas-vindas disparado em todo cadastro de usuário.
 *
 * Contextos:
 *  - 'dono'        → proprietário cadastrado junto com a empresa (onboarding)
 *  - 'funcionario' → usuário criado pela própria empresa (FuncionarioController)
 *  - 'equipe'      → administrador da plataforma IA365 (Admin\UsuarioController)
 *
 * A senha é definida por quem cadastra (decisão do Dennis 04/08/2026) — o
 * e-mail nunca carrega a senha, apenas orienta usar "Esqueci minha senha".
 */
class BoasVindasUsuario extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $contexto = 'funcionario',
    ) {}

    public function envelope(): Envelope
    {
        $assunto = match ($this->contexto) {
            'dono'   => 'Bem-vindo ao ERP Comercial IA365 — seu acesso está pronto',
            'equipe' => 'Seu acesso de administrador IA365 foi criado',
            default  => 'Você foi cadastrado no ERP Comercial',
        };

        return new Envelope(subject: $assunto);
    }

    public function content(): Content
    {
        $perfil = $this->user->perfil instanceof Perfil
            ? $this->user->perfil
            : Perfil::tryFrom((string) $this->user->perfil);

        return new Content(view: 'emails.boas-vindas', with: [
            'contexto'    => $this->contexto,
            'perfilLabel' => $perfil?->label() ?? 'Usuário',
        ]);
    }
}
