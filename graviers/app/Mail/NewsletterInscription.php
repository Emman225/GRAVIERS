<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Accusé de réception envoyé au visiteur qui s'inscrit à la lettre
 * d'information depuis le pied de page du site.
 */
class NewsletterInscription extends Mailable
{
    use Queueable, SerializesModels;

    public string $adresse;

    public function __construct(string $adresse)
    {
        $this->adresse = $adresse;
    }

    public function build()
    {
        return $this->subject('Votre inscription à notre lettre d\'information')
            ->view('emails.newsletter-inscription');
    }
}
