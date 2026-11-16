<?php

namespace App\Mail;

use App\Models\PlataformaFatura;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail da fatura da plataforma para dono, gerente, admin e financeiro da loja
 * (16/11/2026). Tipos: emitida | vence | atraso | suspensa | paga.
 * Quem decide quando mandar é App\Services\Plataforma\FaturasPlataforma::avisar.
 */
class FaturaPlataformaAviso extends Mailable
{
    public function __construct(
        public PlataformaFatura $fatura,
        public string $tipo,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        $vence = $this->fatura->vencimento->format('d/m/Y');

        return new Envelope(subject: match ($this->tipo) {
            'emitida' => "Sua fatura do ERP Comercial está disponível — vence em {$vence}",
            'vence' => "Sua fatura do ERP Comercial vence em {$vence}",
            'atraso' => "Fatura do ERP Comercial em atraso — venceu em {$vence}",
            'suspensa' => 'Acesso ao ERP Comercial suspenso por fatura em aberto',
            default => 'Pagamento confirmado — ERP Comercial',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.fatura-plataforma', with: [
            'empresa' => $this->fatura->empresa,
            'linkFaturas' => rtrim((string) config('app.url'), '/') . '/app/faturas',
        ]);
    }
}
