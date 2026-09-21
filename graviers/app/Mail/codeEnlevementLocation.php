<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Enlevement;
use App\Models\Location;
use App\Models\Produit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoie au CLIENT le code du bon d'enlèvement d'une location en RETRAIT SUR
 * PLACE.
 *
 * Quand le client vient chercher lui-même le matériel, personne d'autre ne peut
 * lui remettre ce code : il n'y a pas de livreur. C'est donc à lui qu'il revient
 * — il le présente au fournisseur, qui valide le bon à la quantité remise.
 *
 * À ne pas confondre avec `receptionCodeLivraisonLocation`, qui envoie le code
 * de VALIDATION d'une livraison : celui-là sert au client à confirmer au livreur
 * qu'il a bien reçu le matériel. Ici, c'est l'inverse — le client présente son
 * code pour OBTENIR le matériel.
 */
class codeEnlevementLocation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Enlevement $enlevement,
        public Location $location,
        public Client $client,
        public ?Produit $produit = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'GRAVIERCI - Votre code de retrait chez le fournisseur',
            to: $this->client->user?->email ?: ($this->client->email ?: '')
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.code-enlevement-location',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
