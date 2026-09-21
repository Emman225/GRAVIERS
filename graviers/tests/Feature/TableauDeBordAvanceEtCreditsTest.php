<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\MouvementAvance;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\User;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * 10/09/2026 :
 *  - /mon-compte : le bloc « avance disponible » reste affiché même à 0, et un
 *    bloc « à régler en agence » montre le reste dû des affaires réglées hors ligne ;
 *  - /avances : colonne « Observations / Notes », badge « Déduction » en blanc,
 *    colonne « Reste de l'avance » après chaque mouvement.
 */
class TableauDeBordAvanceEtCreditsTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): Client
    {
        $client = Client::where('statut', 1)->whereHas('user')->get()
            ->first(fn (Client $c) => Avances::affairesNonSoldees($c)->isEmpty() && Avances::creditsEnAgence($c) < 1);
        if (!$client) {
            $this->markTestSkipped('Aucun client sans affaire en cours.');
        }

        return $client;
    }

    private function modeAgence(): ModePaiement
    {
        return ModePaiement::where('statut', 1)->where('en_ligne', 0)->where('libelle', 'like', '%agence%')->first()
            ?: ModePaiement::where('en_ligne', 0)->first();
    }

    public function test_le_tableau_de_bord_montre_l_avance_meme_a_zero_et_les_credits_en_agence(): void
    {
        $client = $this->client();
        Auth::guard('web')->login($client->user);

        $html = $this->get('/mon-compte')->assertOk()->getContent();
        $this->assertStringContainsString('js-avance-disponible', $html, "Le bloc « avance disponible » doit rester affiché à 0.");
        $this->assertStringContainsString("FCFA d'avance disponible", $html);
        $this->assertStringContainsString('js-credits-agence', $html, 'Le bloc « à régler en agence » manque.');
        $this->assertStringContainsString('FCFA à régler en agence', $html);

        // Une location de 8 000 F réglée en agence, non soldée : 8 000 F à régler.
        $produit  = Produit::where('statut', 1)->first() ?: Produit::first();
        $location = Location::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => 8000,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => 1, 'remise' => 0, 'cout_livraison_client' => 0,
            'mode_paiement_id' => $this->modeAgence()->id,
        ]);
        DetailLocation::create([
            'produit_id' => $produit->id, 'location_id' => $location->id, 'qte' => 1, 'debut' => now()->toDateString(),
            'fin' => now()->toDateString(), 'prix' => 8000, 'nombre_jour' => 1, 'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $location->id, 'montant' => 0, 'type_affaire' => \Help::$LOCATION]);
        $this->assertEqualsWithDelta(8000, Avances::creditsEnAgence($client->fresh()), 0.01);

        // Une avance de 3 000 F disponible s'impute : il reste 5 000 F à régler, 0 d'avance.
        AvanceClient::create([
            'client_id' => $client->id, 'montant' => 3000, 'montant_consomme' => 0, 'statut' => AvanceClient::DISPONIBLE,
            'numero_recu' => 'RA-TEST-' . random_int(100, 999), 'date_depot' => now(),
        ]);
        Avances::imputerSurLocation(Location::find($location->id), null);
        $this->assertEqualsWithDelta(5000, Avances::creditsEnAgence($client->fresh()), 0.01);

        $html = $this->get('/mon-compte')->assertOk()->getContent();
        $bloc = substr($html, strpos($html, 'js-credits-agence'), 1500);
        $this->assertStringContainsString('js-credits-reste">5 000<', $bloc, 'Le bloc doit afficher 5 000 F à régler en agence.');
        $this->assertStringContainsString('À régler 8 000 · payé 3 000 · reste 5 000', $bloc, 'Le suivi dû / payé / reste manque.');

        // Un versement de 2 000 F saisi au guichet, pas encore validé : le client le voit, le reste ne bouge pas.
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        $p = \App\Models\Paiement::create([
            'client_id' => $client->id, 'code' => 'T-' . random_int(100000, 999999), 'libelle' => 'Acompte', 'montant_total' => 2000,
            'montant_restant' => 0, 'statut' => 2, 'service' => 'LOCATION', 'service_id' => $location->id, 'numero_recu' => 'RC-T-' . random_int(100, 999),
            'caissier_id' => $admin?->id, 'agence_id' => Agence::value('id'),
        ]);
        \App\Models\LignePaiement::create([
            'paiement_id' => $p->id, 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 2000, 'statut' => 2,
            'code_paiement' => $p->code, 'service' => 'LOCATION', 'service_id' => $location->id, 'date_paiement' => now(),
        ]);
        $detail = Avances::creditsEnAgenceDetail($client->fresh());
        $this->assertEqualsWithDelta(5000, $detail['reste'], 0.01);
        $this->assertEqualsWithDelta(2000, $detail['en_attente'], 0.01);
        $bloc = substr($this->get('/mon-compte')->getContent(), strpos($html, 'js-credits-agence'), 1500);
        $this->assertStringContainsString('dont 2 000 en attente de validation', $bloc);

        // Validé : payé 5 000, reste 3 000.
        $p->update(['statut' => 1]);
        \App\Models\LignePaiement::where('paiement_id', $p->id)->update(['statut' => 1]);
        $detail = Avances::creditsEnAgenceDetail($client->fresh());
        $this->assertEqualsWithDelta(3000, $detail['reste'], 0.01);
        $this->assertEqualsWithDelta(5000, $detail['paye'], 0.01);
        $html = $this->get('/mon-compte')->getContent();
        $bloc = substr($html, strpos($html, 'js-credits-agence'), 1500);
        $this->assertStringContainsString('À régler 8 000 · payé 5 000 · reste 3 000', $bloc);
        $this->assertStringNotContainsString('en attente de validation', $bloc);

        // Soldée : plus rien à régler, le bloc reste affiché à 0.
        $p2 = \App\Models\Paiement::create([
            'client_id' => $client->id, 'code' => 'T-' . random_int(100000, 999999), 'libelle' => 'Solde', 'montant_total' => 3000,
            'montant_restant' => 0, 'statut' => 1, 'service' => 'LOCATION', 'service_id' => $location->id, 'numero_recu' => 'RC-T-' . random_int(100, 999),
        ]);
        \App\Models\LignePaiement::create([
            'paiement_id' => $p2->id, 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 3000, 'statut' => 1,
            'code_paiement' => $p2->code, 'service' => 'LOCATION', 'service_id' => $location->id, 'date_paiement' => now(),
        ]);
        $this->assertEqualsWithDelta(0, Avances::creditsEnAgence($client->fresh()), 0.01);
        $bloc = substr($this->get('/mon-compte')->getContent(), strpos($html, 'js-credits-agence'), 1500);
        $this->assertStringContainsString('js-credits-reste">0<', $bloc, 'Tout est réglé : le bloc reste affiché, à 0.');
    }

    public function test_la_page_des_avances_montre_le_reste_apres_chaque_mouvement(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        if (!$admin->agence_id) {
            $admin->agence_id = Agence::value('id');
            $admin->save();
        }
        $client = Client::where('statut', 1)->whereHas('user')->first();
        $avance = AvanceClient::create([
            'client_id' => $client->id, 'montant' => 5000, 'montant_consomme' => 3000, 'statut' => AvanceClient::DISPONIBLE,
            'numero_recu' => 'RA-TEST-' . random_int(100, 999), 'date_depot' => now(),
        ]);
        MouvementAvance::create(['avance_client_id' => $avance->id, 'client_id' => $client->id, 'type' => 'DEPOT', 'montant' => 5000, 'libelle' => 'Dépôt de test']);
        MouvementAvance::create(['avance_client_id' => $avance->id, 'client_id' => $client->id, 'type' => 'DEDUCTION', 'montant' => 3000, 'libelle' => 'Déduction de test']);

        Auth::guard('web')->login($admin);
        $html = $this->get('/avances')->assertOk()->getContent();
        $this->assertStringContainsString('<th>Observations / Notes</th>', $html);
        $this->assertStringContainsString("<th class=\"text-end\">Reste de l'avance</th>", $html, 'La colonne « Reste de l\'avance » manque.');
        $this->assertStringContainsString('<span class="badge bg-primary text-white">Déduction</span>', $html, 'Le badge « Déduction » doit être en blanc.');

        // La ligne du dépôt montre 5 000, celle de la déduction 2 000.
        $depot = substr($html, strpos($html, 'Dépôt de test') - 1200, 1200);
        $this->assertStringContainsString('js-reste-avance"><strong>5 000', $depot, 'Après le dépôt, il reste 5 000.');
        $deduction = substr($html, strpos($html, 'Déduction de test') - 1200, 1200);
        $this->assertStringContainsString('js-reste-avance"><strong>2 000', $deduction, 'Après la déduction, il reste 2 000.');
    }
}
