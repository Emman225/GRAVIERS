<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $clientNom,
        public string $emailClient,
        public string $typeDocument,
        public string $numero,
        public string $pdfContent,
        public string $nomFichier
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->typeDocument . ' N° ' . $this->numero . ' - IMLOD',
            to: $this->emailClient
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-pdf',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->nomFichier)
                ->withMime('application/pdf'),
        ];
    }
}
