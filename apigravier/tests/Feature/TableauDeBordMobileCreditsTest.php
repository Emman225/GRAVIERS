<?php

namespace Tests\Feature;

use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LE MOBILE CLIENT REÇOIT CE QUE LE SITE AFFICHE (10/09/2026) : l'avance
 * disponible, les crédits à régler en agence (dû / payé / en attente / reste),
 * et sur chaque règlement l'affaire et l'état du point 20.
 */
class TableauDeBordMobileCreditsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_accueil_renvoie_l_avance_et_les_credits_et_la_liste_des_paiements_dit_l_affaire_et_l_etat(): void
    {
        if (!Schema::hasTable('avance_client')) {
            $this->markTestSkipped('Table avance_client absente.');
        }
        $client = Client::where('statut', 1)->whereNotNull('user_id')->get()
            ->first(fn (Client $c) => Avances::creditsEnAgenceDetail($c)['du'] < 1);
        $produit = Produit::first();
        $mode = ModePaiement::where('en_ligne', 0)->first();
        if (!$client || !$produit || !$mode) {
            $this->markTestSkipped('Il manque un client sans encours, un produit ou un mode hors ligne.');
        }

        $location = Location::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => 8000,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => 1, 'remise' => 0, 'cout_livraison_client' => 0,
            'mode_paiement_id' => $mode->id,
        ]);
        DetailLocation::create([
            'location_id' => $location->id, 'produit_id' => $produit->id, 'qte' => 1, 'prix' => 8000,
            'debut' => now()->toDateString(), 'fin' => now()->toDateString(), 'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $location->id, 'montant' => 0, 'type_affaire' => \Help::$LOCATION, 'statut' => \Help::$STATUT_ACTIF]);

        AvanceClient::create([
            'client_id' => $client->id, 'montant' => 3000, 'montant_consomme' => 0, 'statut' => AvanceClient::DISPONIBLE,
            'numero_recu' => 'RA-TEST-' . random_int(100, 999), 'date_depot' => now(),
        ]);
        Avances::imputerSurLocation(Location::find($location->id), $client);

        // Un versement de 2 000 saisi au guichet, pas encore validé.
        $p = Paiement::create([
            'client_id' => $client->id, 'code' => 'T-' . random_int(100000, 999999), 'libelle' => 'Acompte', 'montant_total' => 2000,
            'montant_restant' => 0, 'statut' => 2, 'service' => 'LOCATION', 'service_id' => $location->id, 'numero_recu' => 'RC-T-' . random_int(100, 999),
        ]);
        LignePaiement::create([
            'paiement_id' => $p->id, 'mode_paiement_id' => $mode->id, 'montant' => 2000, 'statut' => 2,
            'code_paiement' => $p->code, 'service' => 'LOCATION', 'service_id' => $location->id, 'date_paiement' => now(),
        ]);

        $detail = Avances::creditsEnAgenceDetail($client->fresh());
        $this->assertEqualsWithDelta(8000, $detail['du'], 0.01);
        $this->assertEqualsWithDelta(3000, $detail['paye'], 0.01);
        $this->assertEqualsWithDelta(2000, $detail['en_attente'], 0.01);
        $this->assertEqualsWithDelta(5000, $detail['reste'], 0.01);

        $acces = ['access' => Crypt::encryptString((string) $client->user_id), 'type' => 'client'];
        $retour = $this->postJson('/mon_gravier/recuperer-montant-point', $acces)->assertOk()->json();
        $this->assertSame(200, $retour['code']);
        $this->assertEqualsWithDelta(0, (float) $retour['soldeAvance'], 0.01, "L'avance est consommée.");
        $this->assertEqualsWithDelta(5000, (float) $retour['creditsEnAgence']['reste'], 0.01);
        $this->assertEqualsWithDelta(2000, (float) $retour['creditsEnAgence']['en_attente'], 0.01);

        $liste = $this->postJson('/mon_gravier/liste-paiement', $acces)->assertOk()->json();
        $this->assertSame(200, $liste['code']);
        $av = collect($liste['data'])->first(fn ($x) => str_starts_with((string) ($x['numero_recu'] ?? ''), 'AV-') && (int) $x['service_id'] === (int) $location->id);
        $this->assertNotNull($av, 'Le règlement de l\'avance figure dans la liste.');
        $this->assertSame('Location ' . $location->numero, $av['affaire']);
        $this->assertSame('Payé', $av['libelle_etat']);
    }
}
