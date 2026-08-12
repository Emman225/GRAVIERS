<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Alerte envoyée à l'entreprise à chaque message reçu depuis la page
 * « Nous contacter ». Sans elle, personne n'était prévenu : le message
 * dormait en base et le visiteur n'obtenait jamais de réponse.
 */
class NouveauMessageContact extends Mailable
{
    use Queueable, SerializesModels;

    public Contact $contact;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    public function build()
    {
        return $this->subject('Nouveau message de contact : ' . $this->contact->sujet)
            // Répondre au message répond directement au visiteur.
            ->replyTo($this->contact->email, $this->contact->nom_prenoms)
            ->view('emails.nouveau-message-contact');
    }
}
