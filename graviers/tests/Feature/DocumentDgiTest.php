<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Facture;
use App\Models\User;
use App\Services\CourrielFactureFne;
use App\Services\DocumentDgi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Le document tel que la DGI le montre, partout (lot 96, 16/09/2026). */
class DocumentDgiTest extends TestCase
{
    use DatabaseTransactions;

    private function uneFactureCertifiee(): Facture
    {
        $commande = Commande::whereNotNull('client_id')->orderByDesc('id')->get()->first(fn ($c) => $c->client);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec client.');
        }
        $ref = 'REF-' . strtoupper(uniqid());

        return Facture::create([
            'numero' => \Help::genererNumeroUnique('facture'), 'numero_fne' => $ref, 'user_id' => User::first()?->id,
            'statut' => 2, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id, 'client_id' => $commande->client_id,
            'montant' => 11262, 'fne_status' => 'certified', 'fne_invoice_id' => 'inv-' . $ref, 'fne_reference' => $ref,
            'fne_certified_at' => now(), 'fne_token' => 'http://54.247.95.108/fr/verification/01a0aba1-f7d0-7cc8-8a94-42de8e38ee33',
            'fne_request_payload' => ['items' => [['taxes' => ['TVA']]]],
            'fne_response_payload' => ['reference' => $ref, 'invoice' => ['id' => 'inv-' . $ref, 'items' => []]],
        ]);
    }

    private function fauxDgi(): void
    {
        Http::fake(['54.247.95.108/ws/invoices/qr/*' => Http::response(
            json_decode(file_get_contents(base_path('tests/Fixtures/verification_dgi.json')), true), 200)]);
    }

    public function test_les_donnees_sont_lues_memorisees_sans_la_cle_et_dessinees(): void
    {
        $this->fauxDgi();
        $facture = $this->uneFactureCertifiee();

        $this->assertSame('http://54.247.95.108/ws/invoices/qr/01a0aba1-f7d0-7cc8-8a94-42de8e38ee33', DocumentDgi::urlDonnees($facture));
        $d = DocumentDgi::donnees($facture);
        $this->assertSame('1339220N26000000025', $d['reference']);
        $this->assertStringNotContainsString('CLE-QUI-NE-DOIT-JAMAIS', json_encode($facture->fresh()->fne_verification_payload));
        $this->assertSame('AFRICA PROJECT MANAGEMENT', $facture->fresh()->fne_verification_payload['company']['name']);
        Http::assertSentCount(1);
        // Mémorisé : plus aucun appel ensuite.
        DocumentDgi::donnees($facture->fresh());
        Http::assertSentCount(1);

        $html = view('document.factureDgi', DocumentDgi::variables($facture->fresh(), $d))->render();
        // Le document est la copie de l'export de la DGI (lot 102) : mêmes mentions, rien de plus.
        foreach (['AFRICA PROJECT MANAGEMENT', '1339220N', '8023 CME Djibi', 'CI-ABJ-03-2013-B13-12197', 'JLA Endpoint Test',
                  'DEV GRAVIER', 'Mode de paiement :', 'Cash', '1339220N26000000025', 'Bati Azo', 'Ciment CPJ 35', 'Frais de livraison',
                  'TVA (18)', '9 280', '1 670', '10 950', 'AUTRES TAXES', '548', '11 498', 'AIRSI', 'RESUME DE LA FACTURE', 'MontserratM'] as $attendu) {
            $this->assertStringContainsString($attendu, $html, "Le document DGI doit porter « {$attendu} ».");
        }
        foreach (['CERTIFIÉE PAR LA DGI', 'SARL au Capital', 'vérification :'] as $enTrop) {
            $this->assertStringNotContainsString($enTrop, $html, "Rien de plus que l'export de la DGI : pas « {$enTrop} ».");
        }

        // Le courriel et toutes les entrées servent ce document.
        $this->assertStringStartsWith('%PDF', CourrielFactureFne::pdf($facture->fresh())->output());
        $this->assertSame('Facture_DGI_1339220N26000000025.pdf', DocumentDgi::nomFichier($facture->fresh()));
        URL::forceRootUrl('');
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])->where('statut', \Help::$STATUT_ACTIF)->first();
        if ($admin) {
            $r = $this->actingAs($admin)->get('/facture-livraison/' . $facture->id . '/telecharger');
            $r->assertOk();
            $this->assertStringContainsString('Facture_DGI_', (string) $r->headers->get('content-disposition'));
        }
        config(['constantes.jeton_interne' => 'jeton-de-recette']);
        $pdf = $this->post('/api/interne/facture/' . $facture->id . '/pdf', ['jeton' => 'jeton-de-recette']);
        $pdf->assertOk();
        $this->assertStringContainsString('Facture_DGI_', (string) $pdf->headers->get('content-disposition'));
    }

    public function test_sans_la_dgi_le_document_local_sert_de_repli(): void
    {
        Http::fake(['54.247.95.108/*' => Http::response('Erreur', 500)]);
        $facture = $this->uneFactureCertifiee();

        $this->assertNull(DocumentDgi::donnees($facture));
        $this->assertStringContainsString('HTTP 500', DocumentDgi::$derniereErreur);
        $this->assertNull(DocumentDgi::reponse($facture, 'voir'));
        $this->assertStringStartsWith('%PDF', CourrielFactureFne::pdf($facture)->output(), 'Le document local reste disponible.');

        // Non certifiée : jamais de document DGI, aucun appel.
        Facture::where('id', $facture->id)->update(['fne_status' => 'pending']);
        Http::fake();
        $this->assertNull(DocumentDgi::donnees($facture->fresh()));
        Http::assertNothingSent();
    }
}
