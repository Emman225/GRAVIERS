<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\Location;
use App\Models\User;
use App\Services\DocumentDAffaire;
use App\Services\DocumentDeCommande;
use App\Models\Commande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** « Nom du vendeur » n'est plus jamais « N/A » (lot 87, 15/09/2026). */
class NomDuVendeurSurLesDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_nom_du_vendeur_est_l_agent_sinon_l_entreprise(): void
    {
        $conf = Configuration::first();
        $attendu = trim((string) ($conf?->nom_pdv ?: ($conf?->raison_sociale ?: 'DALAKOUN')));
        $this->assertNotSame('', $attendu);
        $this->assertSame($attendu, \Help::nomDuVendeur(null));

        $agent = User::whereNotNull('nom_prenoms')->where('nom_prenoms', '!=', '')->first();
        if ($agent) {
            $this->assertSame(trim($agent->nom_prenoms), \Help::nomDuVendeur($agent));
        }
    }

    public function test_les_proformas_ne_disent_plus_na(): void
    {
        $vendeur = e(\Help::nomDuVendeur(null));

        $commande = Commande::whereHas('detailCommande')->whereHas('client')->orderByDesc('id')->first();
        if ($commande) {
            $html = view('client.commandeValidee', DocumentDeCommande::donnees($commande, true))->render();
            $this->assertStringContainsString('Nom du vendeur : ' . $vendeur, $html);
            $this->assertStringNotContainsString('Nom du vendeur : N/A', $html);
        }

        $location = Location::whereHas('detailLocation')->whereHas('client')->orderByDesc('id')->first();
        if ($location) {
            $html = view('orders.recapLocation', ['location' => $location, 'config' => Configuration::first(),
                'typeDocument' => DocumentDAffaire::titreLocation($location), 'pourPdf' => true])->render();
            $this->assertStringContainsString('Nom du vendeur : ' . $vendeur, $html);
        }

        $demande = DemandeLivraison::whereHas('client')->orderByDesc('id')->first();
        if ($demande) {
            $html = view('document.factureLivraison', array_merge([
                'demande' => $demande, 'facture' => new Facture(['numero' => $demande->numero]),
                'config' => Configuration::first(), 'typeDocument' => DocumentDAffaire::titreLivraison($demande),
            ], \App\Services\FneService::getDonneesFne(null, $demande->client)))->render();
            $this->assertStringContainsString('Nom du vendeur : ' . $vendeur, $html);
            $this->assertStringNotContainsString('Nom du vendeur : N/A', $html);
        }

        // Plus aucun gabarit n'écrit « N/A » pour le vendeur.
        foreach (['document/factureCommande', 'document/factureLocation', 'document/factureLivraison'] as $g) {
            $this->assertStringNotContainsString("nom_prenoms ?? 'N/A'", file_get_contents(resource_path("views/$g.blade.php")));
        }
    }
}
