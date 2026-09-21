<?php

namespace Tests\Feature;

use App\Models\BlClient;
use App\Models\Configuration;
use App\Models\Facture;
use App\Models\Location;
use App\Models\User;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE BON DE COMMANDE INTERNE SUR LA LOCATION (09/09/2026) — côté site.
 *
 * Ce qui vaut pour la vente vaut pour la location : le numéro est figé sur la
 * location (location.numero_bon_commande), sa pièce jointe est rangée dans
 * bl_client (location_id), et le numéro figure DEVANT chaque désignation de la
 * facture de location. Le parcours client (recapLocation, recapDevisLocation)
 * retient le numéro même sans pièce jointe, puis enregistrementDeLocation le
 * fige : ces deux points sont des lectures de source, le parcours entier
 * dépendant d'une session de panier de location.
 */
class BonDeCommandeSurLaLocationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_location_fige_le_numero_et_range_sa_piece(): void
    {
        $location = Location::whereHas('detailLocation')->whereHas('client')->first();
        if (!$location) {
            $this->markTestSkipped('Aucune location avec des lignes.');
        }
        $location->numero_bon_commande = 'BC-LOC-31';
        $location->save();
        $this->assertSame('BC-LOC-31', Location::find($location->id)->numero_bon_commande, 'La colonne location.numero_bon_commande manque.');

        BlClient::where('location_id', $location->id)->delete();
        BlClient::create(['numero' => 'BC-LOC-31', 'client_id' => $location->client_id, 'location_id' => $location->id, 'fichier' => 'lesBons/x.pdf']);
        $this->assertSame('BC-LOC-31', $location->fresh()->blClient?->numero, 'Location::blClient ne retrouve pas la pièce (bl_client.location_id).');
    }

    public function test_la_facture_de_location_porte_le_numero_devant_chaque_designation(): void
    {
        $location = Location::whereHas('detailLocation')->whereHas('client')->first();
        if (!$location || !$location->detailLocation->first()?->produit) {
            $this->markTestSkipped('Aucune location avec des lignes.');
        }
        $location->numero_bon_commande = 'BC-LOC-31';
        $location->save();

        $facture = Facture::create([
            'numero' => 'L' . substr((string) time(), -8), 'user_id' => $location->client->user_id ?? User::value('id'),
            'client_id' => $location->client_id, 'service' => \Help::$LOCATION, 'service_id' => $location->id,
            'montant' => round($location->montantAPayer()), 'statut' => 2, 'fne_status' => 'pending',
        ]);
        $loc = Location::with('detailLocation.produit.uniteProduit', 'tvaLocation', 'adresseLivraison', 'client')->find($location->id);
        $html = view('document.factureLocation', array_merge(
            ['location' => $loc, 'facture' => $facture, 'config' => Configuration::first(), 'livraison' => 1],
            FneService::getDonneesFne($facture, $facture->client)
        ))->render();

        $nom = \Help::phrase($location->detailLocation->first()->produit->nom);
        // 13/09/2026 : « numéro de ligne - numéro de bon ».
        $this->assertStringContainsString('<td class="col-ref">01 - BC-LOC-31</td>', $html, 'La facture de location ne porte pas le numéro en colonne Réf.');
        // La facture normalisée porte la même référence (13/09/2026).
        $items = FneService::buildLocationPayload($facture->fresh())['items'];
        $this->assertSame('01 - BC-LOC-31', $items[0]['reference'], 'La facture normalisée de location ne porte pas « 01 - N° de bon » en référence.');
        $this->assertStringContainsString($nom, $html);
        $this->assertStringNotContainsString('BC n° BC-LOC-31 — ', $html);
    }

    public function test_le_parcours_client_retient_le_numero_et_le_fige(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));
        // recapLocation ET recapDevisLocation retiennent le numéro même sans pièce jointe.
        $this->assertSame(2, substr_count($source, "session()->put(['numero_bon_commande' => \$request->numero_bon]);") - 1,
            'Les deux récapitulatifs de location doivent retenir le numéro sans pièce jointe.');
        // enregistrementDeLocation le fige et range la pièce.
        $this->assertStringContainsString("'numero_bon_commande' => session('numero_bon_commande'),", $source);
        $this->assertStringContainsString("'location_id' => \$location->id,", $source);
    }
}
