<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnvoieCommandeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public $commande, public $lignes, public $montantTva, public $nomPrenom, public $email, public $contact, public $typeAffaire )
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre commande',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.commande',
            with: [
                'cde' => $this->commande,
                'lignes' => $this->lignes,
                'montantTva' => $this->montantTva,
                'nomPrenom' => $this->nomPrenom,
                'email' => $this->email,
                'contact' => $this->contact,
                'typeAffaire' => $this->typeAffaire,
                'logo' => 'https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
