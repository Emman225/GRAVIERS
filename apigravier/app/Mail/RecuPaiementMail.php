<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Reçu de paiement en texte — repli quand le site ne peut pas produire le PDF. */
class RecuPaiementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $recu)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reçu de paiement N° ' . ($this->recu['numeroRecu'] ?? '') . ' - DALAKOUN',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.recu-paiement');
    }

    public function attachments(): array
    {
        return [];
    }
}
