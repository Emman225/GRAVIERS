<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Commande;
use App\Models\Paiement;
use App\Services\DocumentDeCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LA PROFORMA OU LA FACTURE APRÈS LA COMMANDE (lot 81, 15/09/2026).
 *
 * « Après avoir passé la commande, la proforma doit être envoyée par mail et
 * affichée dans le compte client ; si c'est une commande où le client paie en
 * ligne, c'est la facture qui est envoyée par mail et affichée dans son
 * compte. » Un seul envoi par document, jamais bloquant.
 */
class ProformaOuFactureApresCommandeTest extends TestCase
{
    use DatabaseTransactions;

    private function uneCommandeSansPaiementEnLigne(): Commande
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client.user')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit && $c->client?->user?->email);

        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes et un client joignable.');
        }

        // Le témoin : aucun règlement en ligne sur cette commande.
        Paiement::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->delete();
        Commande::where('id', $commande->id)->update([
            'etat_commande'       => \Help::$COMMANDE_EN_ATTENTE,
            'proforma_envoyee_le' => null,
            'facture_envoyee_le'  => null,
            'date_livraison'      => now()->addDays(3)->toDateString(),
        ]);

        return Commande::find($commande->id);
    }

    private function unPaiementEnLigne(Commande $commande): Paiement
    {
        return Paiement::create([
            'client_id'       => $commande->client_id,
            'code'            => 'T' . substr((string) microtime(true) * 1000, -9),
            'libelle'         => 'Paiement en ligne (test)',
            'montant_total'   => round($commande->montantAPayer()),
            'montant_restant' => 0,
            'statut'          => 1,
            'service'         => \Help::$COMMANDE,
            'service_id'      => $commande->id,
        ]);
    }

    public function test_la_base_porte_la_memoire_d_envoi(): void
    {
        $this->assertTrue(Schema::hasColumn('commande', 'proforma_envoyee_le'));
        $this->assertTrue(Schema::hasColumn('commande', 'facture_envoyee_le'));
    }

    public function test_proforma_sans_paiement_en_ligne_facture_avec(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();

        $this->assertSame('Proforma', DocumentDeCommande::type($commande));
        $this->assertSame('Proforma', DocumentDeCommande::titre($commande));
        $this->assertSame('proforma-' . $commande->numero . '.pdf', DocumentDeCommande::nomFichier($commande));

        $this->unPaiementEnLigne($commande);

        $this->assertSame('Facture', DocumentDeCommande::type($commande));
        $this->assertSame('Facture de vente', DocumentDeCommande::titre($commande));
        $this->assertSame('facture-' . $commande->numero . '.pdf', DocumentDeCommande::nomFichier($commande));
    }

    public function test_un_reglement_en_agence_ne_fait_pas_une_facture(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        $p = $this->unPaiementEnLigne($commande);
        $p->update(['agence_id' => 1, 'caissier_id' => 1]);

        $this->assertSame('Proforma', DocumentDeCommande::type($commande));
    }

    public function test_la_page_dit_proforma_et_le_pdf_n_a_ni_bandeau_ni_boutons(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();

        $ecran = view('client.commandeValidee', DocumentDeCommande::donnees($commande))->render();
        $this->assertStringContainsString('Proforma Nº', $ecran);
        $this->assertStringContainsString('Commande Validée', $ecran);
        $this->assertStringContainsString('Télécharger (Proforma)', $ecran);
        $this->assertStringContainsString(e(\Help::mentionDelaiLivraison()), $ecran, 'Le délai toléré est lu sur le document.');

        $pdf = view('client.commandeValidee', DocumentDeCommande::donnees($commande, true))->render();
        $this->assertStringContainsString('Proforma Nº', $pdf);
        $this->assertStringNotContainsString('Commande Validée', $pdf);
        $this->assertStringNotContainsString('Continuer vos achats', $pdf);

        $this->unPaiementEnLigne($commande);
        $facture = view('client.commandeValidee', DocumentDeCommande::donnees($commande, true))->render();
        $this->assertStringContainsString('Facture de vente Nº', $facture);
        $this->assertStringNotContainsString('Proforma', $facture);
    }

    public function test_l_envoi_est_unique_et_dit_le_bon_type(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        Mail::fake();

        $this->assertTrue(DocumentDeCommande::envoyerParCourriel($commande, true));
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) =>
            $m->typeDocument === 'Proforma'
            && $m->numero === (string) $commande->numero
            && $m->nomFichier === 'proforma-' . $commande->numero . '.pdf'
            && str_starts_with($m->pdfContent, '%PDF'));
        $this->assertNotNull(Commande::find($commande->id)->proforma_envoyee_le);

        // Un second chemin se tait.
        $this->assertFalse(DocumentDeCommande::envoyerParCourriel($commande, true));
        Mail::assertSent(DocumentPdfMail::class, 1);

        // Payée en ligne ensuite : la FACTURE part, elle aussi une seule fois.
        $this->unPaiementEnLigne($commande);
        $this->assertTrue(DocumentDeCommande::envoyerParCourriel($commande, true));
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->typeDocument === 'Facture');
        $this->assertFalse(DocumentDeCommande::envoyerParCourriel($commande, true));
        Mail::assertSent(DocumentPdfMail::class, 2);
    }

    public function test_une_commande_qui_attend_son_paiement_en_ligne_ne_recoit_rien(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        Commande::where('id', $commande->id)->update(['etat_commande' => \Help::$COMMANDE_EN_ATTENTE_PAIEMENT]);
        Mail::fake();

        $this->assertFalse(DocumentDeCommande::envoyerParCourriel(Commande::find($commande->id), true));
        Mail::assertNothingSent();
        $this->assertNull(Commande::find($commande->id)->proforma_envoyee_le);
    }

    public function test_un_envoi_qui_echoue_ne_bloque_rien_et_libere_la_memoire(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP injoignable'));

        $this->assertTrue(DocumentDeCommande::envoyerParCourriel($commande, true));
        $this->assertNull(Commande::find($commande->id)->proforma_envoyee_le,
            'La date d\'envoi est effacée : un prochain chemin pourra réessayer.');
    }

    public function test_mon_compte_et_le_pdf_offrent_le_document_au_seul_client(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        $this->actingAs($commande->client->user);

        $this->get('/mon-compte')->assertOk()
            ->assertSee('commande-' . $commande->numero . '-document-pdf')
            ->assertSee('Factures DGI');

        $reponse = $this->get(route('client.documentCommandePdf', $commande->numero));
        $reponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $reponse->headers->get('content-type'));
        $this->assertStringContainsString('proforma-' . $commande->numero . '.pdf', (string) $reponse->headers->get('content-disposition'));

        // Un autre client n'y a pas accès.
        $autre = Commande::where('client_id', '!=', $commande->client_id)->whereHas('client.user')->first();
        if ($autre) {
            $this->actingAs($autre->client->user)
                ->get(route('client.documentCommandePdf', $commande->numero))
                ->assertForbidden();
        }
    }

    public function test_la_route_interne_exige_le_jeton(): void
    {
        $commande = $this->uneCommandeSansPaiementEnLigne();
        $this->postJson('/api/interne/commande/' . $commande->numero . '/document', ['jeton' => 'faux'])
            ->assertStatus(403);
    }
}
