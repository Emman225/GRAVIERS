<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodeInscriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public $nom, public $code, public $message)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Code de confirmation Mon Gravier',
        );
    }

    //     /**
    //      * Build the message.
    //      *
    //      * @return $this
    //      */
    //     public function build() {
    //         return $this->view('mail.code-inscription', [
    //             'nom' => $this->nom,
    //             'code' => $this->code,
    //             'message' => $this->message,
    //         ]);
    //     }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.code-inscription',
            with: [
                'nom' => $this->nom,
                'code' => $this->code,
                'msg' => $this->message,
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
