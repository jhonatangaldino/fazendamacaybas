<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ManualUsuarioMail — envio do manual do usuário pelo Master.
 *
 * Comportamento dual:
 *   - Quando $manualHtml está preenchido → anexa o arquivo HTML ao e-mail
 *     (modo "anexo", usado quando o tamanho cabe em 20 MB).
 *   - Quando $downloadUrl está preenchido → e-mail tem CTA "Baixar manual"
 *     com URL assinada (modo "link", usado quando o anexo seria grande
 *     demais e quebraria limite de 25 MB do Gmail/Outlook).
 *
 * O Master, em /master/manuais, escolhe o cliente, escolhe o usuário "dono"
 * (ativo) daquele cliente e dispara o envio. O ManuaisController decide qual
 * modo usar com base no tamanho do HTML self-contained gerado.
 */
class ManualUsuarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $destinatario,
        public readonly string $manualTitulo,
        public readonly ?string $manualHtml,
        public readonly string $manualFilename,
        public readonly ?string $remetenteNome = null,
        public readonly ?string $mensagemPersonalizada = null,
        public readonly ?string $downloadUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->manualTitulo.' · Fazenda Macaybas',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manual-usuario',
            with: [
                'nome' => $this->destinatario->name,
                'manualTitulo' => $this->manualTitulo,
                'remetenteNome' => $this->remetenteNome,
                'mensagemPersonalizada' => $this->mensagemPersonalizada,
                'downloadUrl' => $this->downloadUrl,
                'modoAnexo' => $this->manualHtml !== null,
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->manualHtml === null) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $this->manualHtml, $this->manualFilename)
                ->withMime('text/html'),
        ];
    }
}
