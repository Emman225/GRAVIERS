<?php

namespace Tests\Feature;

use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * L'AVANCE D'UN CLIENT S'IMPUTE AUSSI SUR SA LOCATION ET SA DEMANDE DE
 * LIVRAISON MOBILES « EN AGENCE » (10/09/2026) — même règle que la commande.
 */
class AvanceLocationLivraisonMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): Client
    {
        if (!Schema::hasTable('avance_client')) {
            $this->markTestSkipped('Table avance_client absente : migration du site non passée.');
        }
        $client = Client::where('statut', 1)->whereNotNull('user_id')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        return $client;
    }

    private function avance(Client $client, float $montant): AvanceClient
    {
        return AvanceClient::create([
            'client_id'        => $client->id,
            'montant'          => $montant,
            'montant_consomme' => 0,
            'statut'           => AvanceClient::DISPONIBLE,
            'numero_recu'      => 'RA-TEST-' . random_int(100, 999),
            'date_depot'       => now(),
        ]);
    }

    private function location(Client $client, float $prix): Location
    {
        $produit = Produit::first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit.');
        }
        $location = Location::create([
            'numero'        => 'T' . random_int(100000, 999999),
            'client_id'     => $client->id,
            'montant_total' => $prix,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
            'statut'        => 1,
            'remise'        => 0,
            'cout_livraison_client' => 0,
        ]);
        DetailLocation::create([
            'location_id'   => $location->id,
            'produit_id'    => $produit->id,
            'qte'           => 1,
            'prix'          => $prix,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
        TvaCommande::create([
            'client_id'    => $client->id,
            'commande_id'  => $location->id,
            'montant'      => 0,
            'type_affaire' => \Help::$LOCATION,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        return Location::find($location->id);
    }

    private function demande(Client $client, float $montant): DemandeLivraison
    {
        $demande = new DemandeLivraison();
        $demande->numero        = 'T' . random_int(100000, 999999);
        $demande->client_id     = $client->id;
        $demande->montantTotal  = $montant;
        $demande->etat_commande = \Help::$COMMANDE_EN_ATTENTE;
        $demande->statut        = \Help::$STATUT_ACTIF;
        $demande->save();

        return DemandeLivraison::find($demande->id);
    }

    public function test_une_location_en_agence_consomme_l_avance_et_laisse_le_reliquat(): void
    {
        $client = $this->client();
        $avance = $this->avance($client, 3000);
        $location = $this->location($client, 5000);
        $this->assertEqualsWithDelta(5000, $location->montantRestantDu(), 0.01);

        $resultat = Avances::imputerSurLocation($location, $client);

        $this->assertEqualsWithDelta(3000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(2000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(2000, Location::find($location->id)->montantRestantDu(), 0.01);
        $this->assertSame(2, (int) Location::find($location->id)->statut, 'Location partiellement réglée = statut 2.');
        $this->assertSame(0.0, Avances::soldeDisponible($client));
        $this->assertEqualsWithDelta(3000, (float) $avance->fresh()->montant_consomme, 0.01);

        $reglement = Paiement::where('service', \Help::$LOCATION)->where('service_id', $location->id)->first();
        $this->assertNotNull($reglement, 'Le règlement issu de l\'avance porte service = LOCATION.');
        $this->assertSame(1, (int) $reglement->statut);
        $this->assertStringStartsWith('AV-', $reglement->numero_recu);
        $this->assertSame(1, LignePaiement::where('paiement_id', $reglement->id)->where('service', \Help::$LOCATION)->where('statut', 1)->count());

        $mouvement = MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->first();
        $this->assertNotNull($mouvement);
        $this->assertNull($mouvement->commande_id, 'Une location ne se range pas dans commande_id.');
        $this->assertSame((int) $reglement->id, (int) $mouvement->paiement_id);
        $this->assertStringContainsString('Location ' . $location->numero, $mouvement->libelle);

        $message = Avances::messageImputation($resultat, \Help::$LOCATION);
        $this->assertStringContainsString('sur cette location', $message);
        $this->assertStringContainsString('3 000 FCFA', $message);
        $this->assertStringContainsString('Reste à régler en agence : 2 000 FCFA', $message);

        // Aucune commande n'a été touchée par ce règlement.
        $this->assertSame(0, Paiement::where('service', \Help::$COMMANDE)->where('service_id', $location->id)
            ->where('numero_recu', $reglement->numero_recu)->count());
    }

    public function test_une_location_couverte_en_entier_est_soldee(): void
    {
        $client = $this->client();
        $this->avance($client, 9000);
        $location = $this->location($client, 4000);

        $resultat = Avances::imputerSurLocation($location, $client);

        $this->assertEqualsWithDelta(4000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(0, $resultat['reste'], 0.01);
        $this->assertSame(3, (int) Location::find($location->id)->statut, 'Location soldée = statut 3.');
        $this->assertEqualsWithDelta(5000, Avances::soldeDisponible($client), 0.01, 'Le reliquat de l\'avance reste disponible.');
        $this->assertStringContainsString('La location est entièrement réglée', Avances::messageImputation($resultat, \Help::$LOCATION));
    }

    public function test_une_demande_de_livraison_en_agence_consomme_l_avance(): void
    {
        $client = $this->client();
        $pointsAvant = (float) $client->point;
        $avance = $this->avance($client, 3000);
        $demande = $this->demande($client, 5000);
        $this->assertEqualsWithDelta(5000, $demande->montantRestantDu(), 0.01);

        $resultat = Avances::imputerSurDemandeLivraison($demande, $client);

        $this->assertEqualsWithDelta(3000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(2000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(2000, DemandeLivraison::find($demande->id)->montantRestantDu(), 0.01);
        $this->assertSame(0.0, Avances::soldeDisponible($client));

        $reglement = Paiement::where('service', \Help::$LIVRAISON)->where('service_id', $demande->id)->first();
        $this->assertNotNull($reglement, 'Le règlement issu de l\'avance porte service = LIVRAISON.');
        $this->assertSame(1, (int) $reglement->statut);
        $this->assertSame(1, LignePaiement::where('paiement_id', $reglement->id)->where('service', \Help::$LIVRAISON)->where('statut', 1)->count());

        $mouvement = MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->first();
        $this->assertNull($mouvement->commande_id);
        $this->assertStringContainsString('Demande de livraison ' . $demande->numero, $mouvement->libelle);

        // Une demande de livraison ne donne pas de points (même règle que le guichet).
        $this->assertSame($pointsAvant, (float) $client->fresh()->point);
        $this->assertStringContainsString('sur cette demande de livraison', Avances::messageImputation($resultat, \Help::$LIVRAISON));

        // Une seconde imputation ne fait rien : rien n'est disponible.
        $encore = Avances::imputerSurDemandeLivraison($demande, $client);
        $this->assertSame(0.0, $encore['impute']);
    }

    public function test_le_message_de_la_commande_ne_change_pas(): void
    {
        $message = Avances::messageImputation(['impute' => 3000, 'reste' => 0, 'recus' => []]);
        $this->assertStringContainsString('sur cette commande', $message);
        $this->assertStringContainsString('La commande est entièrement réglée', $message);
    }
}
