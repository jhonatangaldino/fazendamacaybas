<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * BoasVindasUsuario — email enviado ao cadastrar um novo usuário.
 *
 * Inclui senha temporária em claro e link direto para login.
 * Sender: noreply@fazendamacaybas.com.br (config mail.from.address)
 */
class BoasVindasUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $senhaTemporaria
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo(a) ao Fazenda Macaybas — sua senha temporária',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boas-vindas',
            with: [
                'nome' => $this->user->name,
                'email' => $this->user->email,
                'senha' => $this->senhaTemporaria,
                'expira_em' => $this->user->password_expires_at?->setTimezone('America/Sao_Paulo')
                    ?->format('d/m/Y \à\s H:i') ?? '2 horas',
                'urlLogin' => $this->user->loginUrl(),
            ],
        );
    }
}
