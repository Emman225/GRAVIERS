<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Location;
use App\Services\FacturationCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA TVA SUR LE TRANSPORT VAUT POUR LES VENTES ET LES LOCATIONS (point 5).
 *
 * La case « TVA sur le transport » du paramétrage ne jouait que sur les
 * demandes de livraison. Cochée, elle taxe désormais aussi le transport d'une
 * vente ou d'une location, au taux du client ; décochée, rien ne change. Le
 * montant est figé sur l'affaire et suit la première facture.
 */
class TvaTransportVentesLocationsTest extends TestCase
{
    use DatabaseTransactions;

    private function config(): Configuration
    {
        $c = Configuration::first();
        if (!$c) {
            $this->markTestSkipped('Pas de configuration en base.');
        }
        return $c;
    }

    public function test_la_regle_suit_la_case_du_parametrage(): void
    {
        $config = $this->config();

        $config->update(['tva_transport' => 0]);
        $this->assertSame(0.0, \Help::tvaSurTransport(4000, 0.18), 'Case décochée : le transport reste hors taxe.');

        $config->update(['tva_transport' => 1]);
        $this->assertSame(720.0, \Help::tvaSurTransport(4000, 0.18));
        $this->assertSame(0.0, \Help::tvaSurTransport(4000, 0.0), "Un client non assujetti n'est pas taxé sur le transport.");
        $this->assertSame(0.0, \Help::tvaSurTransport(0, 0.18), 'Sans transport, pas de taxe.');
    }

    public function test_le_montant_du_des_trois_affaires_compte_la_tva_transport(): void
    {
        $commande = Commande::whereHas('detailCommande')->first();
        if ($commande) {
            $avant = $commande->montantAPayer();
            $commande->tva_transport = 720;
            $this->assertEqualsWithDelta($avant + 720, $commande->montantAPayer(), 0.01);
        }

        $location = Location::whereHas('detailLocation')->first();
        if ($location) {
            $avant = $location->montantAPayer();
            $location->tva_transport = 540;
            $this->assertEqualsWithDelta($avant + 540, $location->montantAPayer(), 0.01);
        }

        $devis = Devis::whereHas('detailDevis')->first();
        if ($devis) {
            $avant = $devis->montantAPayer();
            $devis->tva_transport = 360;
            $this->assertEqualsWithDelta($avant + 360, $devis->montantAPayer(), 0.01);
        }

        if (!$commande && !$location && !$devis) {
            $this->markTestSkipped('Aucune affaire en base.');
        }
    }

    public function test_la_facture_sur_reglement_porte_la_tva_transport_et_l_imprime(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }

        $commande->cout_livraison_client = 4000;
        $commande->tva_transport = 720;
        $commande->save();
        Facture::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->delete();

        // Facture établie sur règlement, couvrant tout le dû.
        $facture = Facture::create([
            'numero'                  => 'T' . substr((string) time(), -8),
            'user_id'                 => $commande->client->user_id ?? \App\Models\User::value('id'),
            'client_id'               => $commande->client_id,
            'service'                 => \Help::$COMMANDE,
            'service_id'              => $commande->id,
            'montant'                 => round($commande->montantAPayer()),
            'remise_appliquee'        => (float) ($commande->remise ?? 0),
            'cout_livraison_applique' => 4000,
            'tva_transport_applique'  => 720,
            'statut'                  => 2,
            'fne_status'              => 'pending',
        ]);

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, $commande->fresh()))->render();

        // 09/09/2026 : le transport est une LIGNE du tableau, avec la mention de
        // TVA ; sa TVA est fondue dans la ligne « TVA » des totaux.
        $this->assertStringContainsString('Coût de livraison', $html);
        $this->assertStringContainsString('4 000', $html);
        $this->assertStringNotContainsString('TVA sur transport', $html);
        $this->assertStringNotContainsString('Transport (HT)', $html);
        $ligne = substr($html, strpos($html, 'Coût de livraison'), 700);
        $this->assertStringContainsString('TVA (', $ligne, 'La ligne de transport ne porte pas la mention de TVA.');
    }

    public function test_sans_tva_transport_le_document_n_affiche_pas_la_ligne(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }
        $commande->tva_transport = 0;
        $commande->save();
        Facture::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->delete();

        $facture = Facture::create([
            'numero'     => 'T' . substr((string) time(), -8),
            'user_id'    => $commande->client->user_id ?? \App\Models\User::value('id'),
            'client_id'  => $commande->client_id,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'montant'    => round($commande->montantAPayer()),
            'statut'     => 2,
            'fne_status' => 'pending',
        ]);

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, $commande->fresh()))->render();

        $this->assertStringNotContainsString('TVA sur transport', $html);
    }
}
