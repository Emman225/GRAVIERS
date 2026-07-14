<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Livraison;
use App\Models\Location;
use App\Models\Client;
use App\Models\Produit;

/**
 * Envoie au client le CODE de validation d'une livraison de LOCATION.
 * Le code est le numéro de la livraison (livraison->numero) : le client le
 * communique au livreur, qui le saisit dans l'app pour valider la livraison.
 * Équivalent location de receptionCodeLivraison (ventes).
 */
class receptionCodeLivraisonLocation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Livraison $livraison,
        public Location $location,
        public Client $client,
        public ?Produit $produit = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'GRAVIERCI - Code de validation de votre location',
            to: $this->client->user->email
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reception-code-livraison-location',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
