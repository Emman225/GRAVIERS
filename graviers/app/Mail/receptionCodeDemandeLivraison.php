<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;

/**
 * Envoie au client le CODE de validation d'une DEMANDE DE LIVRAISON.
 *
 * Le code est le numéro de la livraison (livraison->numero) : le client le
 * communique au livreur, qui le saisit dans son application pour clore la
 * course. Sans lui, la livraison ne peut pas être validée — l'application
 * livreur refuse la saisie tant que le numéro ne correspond pas.
 *
 * Les ventes et les locations envoyaient déjà cet e-mail (receptionCodeLivraison
 * et receptionCodeLivraisonLocation). Les demandes de livraison, non : le client
 * n'avait aucun moyen d'obtenir son code, et la course restait ouverte.
 */
class receptionCodeDemandeLivraison extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Livraison $livraison,
        public DemandeLivraison $demande,
        public Client $client,
        public ?DetailLivraison $ligne = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'DALAKOUN - Code de validation de votre demande de livraison',
            // Même cascade que les autres courriels de code : l'adresse du compte
            // d'abord, celle de la fiche client à défaut.
            to: $this->client->user?->email ?: ($this->client->email ?: '')
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reception-code-demande-livraison',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
