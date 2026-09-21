<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\User;
use App\Services\CourrielFactureFne;
use App\Services\FactureAvoir;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** La facture certifiée et l'avoir partent au client par courriel (lot 93, 16/09/2026). */
class CourrielFactureCertifieeTest extends TestCase
{
    use DatabaseTransactions;

    private function uneFactureCertifiee(): Facture
    {
        $commande = Commande::whereNotNull('client_id')->get()
            ->first(fn ($c) => $c->client && ($c->client->user?->email ?: $c->client->email));
        if (!$commande) {
            $this->markTestSkipped('Aucune commande dont le client a une adresse.');
        }
        $ref = 'REF-' . strtoupper(uniqid());

        return Facture::create([
            'numero' => \Help::genererNumeroUnique('facture'), 'numero_fne' => $ref, 'user_id' => User::first()?->id,
            'statut' => 2, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id, 'client_id' => $commande->client_id,
            'montant' => 11800, 'fne_status' => 'certified', 'fne_invoice_id' => 'inv-' . $ref, 'fne_reference' => $ref,
            'fne_certified_at' => now(), 'fne_request_payload' => ['items' => [['taxes' => ['TVA']]]],
            'fne_response_payload' => ['reference' => $ref, 'invoice' => ['id' => 'inv-' . $ref, 'items' => [
                ['id' => 'it-1', 'reference' => '01', 'description' => 'Gravier', 'quantity' => 10, 'amount' => 1000, 'measurementUnit' => 'T'],
            ]]],
        ]);
    }

    public function test_la_facture_certifiee_part_une_seule_fois(): void
    {
        Mail::fake();
        $facture = $this->uneFactureCertifiee();

        $this->assertTrue(CourrielFactureFne::envoyer($facture, true));
        Mail::assertSent(DocumentPdfMail::class, fn ($m) => $m->typeDocument === 'Facture de vente'
            && $m->numero === $facture->fne_reference && str_starts_with($m->pdfContent, '%PDF'));
        $this->assertNotNull($facture->fresh()->courriel_envoye_le);

        $this->assertFalse(CourrielFactureFne::envoyer($facture, true), 'Une seule fois.');
        Mail::assertSent(DocumentPdfMail::class, 1);

        // Non certifiée : rien ne part.
        Facture::where('id', $facture->id)->update(['fne_status' => 'pending', 'courriel_envoye_le' => null]);
        $this->assertFalse(CourrielFactureFne::envoyer($facture, true));
        Mail::assertSent(DocumentPdfMail::class, 1);
    }

    public function test_l_avoir_certifie_part_au_client(): void
    {
        Mail::fake();
        config(['fne.enabled' => true, 'fne.api_key' => 'cle', 'fne.base_url' => 'http://fne.recette/ws']);
        Http::fake(['fne.recette/*' => Http::response(['reference' => 'A-TEST-1', 'token' => 'http://t', 'balance_sticker' => 1], 201)]);
        $origine = $this->uneFactureCertifiee();

        $r = FactureAvoir::emettre($origine, ['it-1' => 2], 'Retour', null);

        $this->assertTrue($r['success'], $r['message']);
        $this->assertStringContainsString('courriel', $r['message']);
        // L'envoi est différé après la réponse : on l'exécute ici.
        app()->terminate();
        Mail::assertSent(DocumentPdfMail::class, fn ($m) => $m->typeDocument === "Facture d'avoir"
            && $m->numero === 'A-TEST-1' && str_starts_with($m->nomFichier, 'Facture_Avoir_'));
    }
}
