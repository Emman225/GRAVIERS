<?php

namespace Tests\Feature;

use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\Location;
use App\Services\DocumentDAffaire;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * MON COMPTE : proforma / facture et factures DGI des locations et des demandes
 * de livraison, comme pour les ventes (lot 85, 15/09/2026).
 */
class DocumentsLocationEtLivraisonMonCompteTest extends TestCase
{
    use DatabaseTransactions;

    private function uneLocation(): Location
    {
        $l = Location::whereHas('client.user')->whereHas('detailLocation')->orderByDesc('id')->first();
        if (!$l) {
            $this->markTestSkipped('Aucune location avec client.');
        }

        return $l;
    }

    private function uneDemande(): DemandeLivraison
    {
        $d = DemandeLivraison::whereHas('client.user')->orderByDesc('id')->first();
        if ($d) {
            return $d;
        }
        // Base sans demande : on en crée une pour le client d'une location.
        $client = $this->uneLocation()->client;

        return DemandeLivraison::create([
            'numero' => 'D' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 20000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF,
        ]);
    }

    public function test_mon_compte_offre_les_documents_des_locations_et_des_livraisons(): void
    {
        $l = $this->uneLocation();
        $this->actingAs($l->client->user)->get('/mon-compte')->assertOk()
            ->assertSee('document-location/' . $l->id . '/pdf')
            ->assertSee('liste-des-factures-location-' . $l->id);

        $d = $this->uneDemande();
        $this->actingAs($d->client->user)->get('/mon-compte')->assertOk()
            ->assertSee('document-demande-livraison/' . $d->id . '/pdf')
            ->assertSee('liste-des-factures-livraison-' . $d->id);
    }

    public function test_la_proforma_de_location_se_telecharge_et_dit_son_titre(): void
    {
        $l = $this->uneLocation();
        $type = DocumentDAffaire::typeLocation($l);
        $this->assertContains($type, ['Proforma', 'Facture']);

        $html = view('orders.recapLocation', ['location' => $l, 'config' => \App\Models\Configuration::first(),
            'typeDocument' => DocumentDAffaire::titreLocation($l), 'pourPdf' => true])->render();
        $this->assertStringContainsString(DocumentDAffaire::titreLocation($l) . ' Nº', $html);
        $this->assertStringNotContainsString('Location validée', $html);
        $this->assertStringNotContainsString('Continuer vos achats', $html);

        $r = $this->actingAs($l->client->user)->get(route('client.documentLocationPdf', $l));
        $r->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
    }

    public function test_la_proforma_de_transport_se_telecharge(): void
    {
        $d = $this->uneDemande();
        $html = view('document.factureLivraison', array_merge([
            'demande' => $d, 'facture' => new Facture(['numero' => $d->numero]),
            'config' => \App\Models\Configuration::first(), 'typeDocument' => DocumentDAffaire::titreLivraison($d),
        ], \App\Services\FneService::getDonneesFne(null, $d->client)))->render();
        $this->assertStringContainsString(DocumentDAffaire::titreLivraison($d) . ' Nº', $html);

        $r = $this->actingAs($d->client->user)->get(route('client.documentLivraisonPdf', $d));
        $r->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
    }

    public function test_les_factures_dgi_se_listent_et_l_affaire_d_un_autre_est_refusee(): void
    {
        $l = $this->uneLocation();
        $this->actingAs($l->client->user)->get(route('client.listeFactureAffaire', ['service' => 'location', 'id' => $l->id]))
            ->assertOk()->assertSee('Factures de la location N°' . $l->numero);

        $facture = Facture::where('service', \Help::$LOCATION)->where('service_id', $l->id)->first();
        if ($facture) {
            $r = $this->actingAs($l->client->user)->get(route('client.factureAffairePdf', ['facture' => $facture, 'action' => 'voir']));
            $r->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
        }

        $autre = Location::where('client_id', '!=', $l->client_id)->whereHas('client.user')->first();
        if ($autre) {
            $this->actingAs($autre->client->user)->get(route('client.documentLocationPdf', $l))->assertForbidden();
            $this->actingAs($autre->client->user)->get(route('client.listeFactureAffaire', ['service' => 'location', 'id' => $l->id]))->assertForbidden();
        }
    }
}
