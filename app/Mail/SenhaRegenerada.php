<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SenhaRegenerada — email enviado quando a senha temporária é regenerada
 * (expirou em 2h ou admin pediu reenvio).
 */
class SenhaRegenerada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $senhaTemporaria
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sua senha temporária foi atualizada — Fazenda Macaybas',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.senha-regenerada',
            with: [
                'nome' => $this->user->name,
                'email' => $this->user->email,
                'senha' => $this->senhaTemporaria,
                'expira_em' => $this->user->password_expires_at?->setTimezone('America/Sao_Paulo')
                    ?->format('d/m/Y \à\s H:i') ?? '2 horas',
                'urlLogin' => url('/login'),
            ],
        );
    }
}
