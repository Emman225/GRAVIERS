<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DemandePaiement;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * POINT 20 SUR LES LOCATIONS ET LES DEMANDES DE LIVRAISON (10/09/2026) :
 * le client voit ses règlements en agence de location et de livraison, avec
 * leur État, dans « Paiements effectués » — comme ceux de ses commandes.
 * Et un règlement de location n'est jamais pris pour la commande de même
 * identifiant.
 */
class ClientVoitSesReglementsDeLocationEtLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    private function reglement(Client $client, string $service, int $serviceId, string $etat, User $admin): Paiement
    {
        $p = Paiement::create([
            'client_id' => $client->id, 'code' => 'T-' . random_int(100000, 999999), 'libelle' => 'Recette point 20',
            'montant_total' => 12345, 'montant_restant' => 0, 'statut' => 1, 'service' => $service, 'service_id' => $serviceId,
            'caissier_id' => $admin->id, 'agence_id' => \App\Models\Agence::value('id'), 'numero_recu' => 'T-' . random_int(100000, 999999),
            'user_valide_id' => $admin->id, 'user_valide2_id' => $admin->id, 'etat_reglement' => $etat,
        ]);
        LignePaiement::create([
            'paiement_id' => $p->id, 'mode_paiement_id' => ModePaiement::first()->id, 'reference' => $p->code,
            'moyen_paiement' => 'Espèces', 'date_paiement' => now(), 'montant' => 12345, 'statut' => 1,
            'user_id' => $admin->id, 'code_paiement' => $p->code, 'service' => $service, 'service_id' => $serviceId,
        ]);

        return $p;
    }

    public function test_le_client_voit_ses_reglements_de_location_et_de_livraison_avec_leur_etat(): void
    {
        $admin  = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        $client = Client::where('statut', 1)->whereHas('user')->first();
        if (!$admin || !$client) {
            $this->markTestSkipped('Il manque un administrateur ou un client.');
        }

        $location = Location::create([
            'numero' => 'L' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => 50000,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => 1,
        ]);
        $demande = DemandeLivraison::create([
            'numero' => 'D' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 20000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF,
        ]);

        $this->reglement($client, 'LOCATION', $location->id, DemandePaiement::EFFECTUEE, $admin);
        $this->reglement($client, 'LIVRAISON', $demande->id, DemandePaiement::A_PAYER, $admin);

        Auth::guard('web')->login($client->user);
        $html = $this->get('/client/liste-des-paiements-effectues')->assertOk()->getContent();

        $this->assertStringContainsString($location->numero, $html, 'Le règlement de la location manque.');
        $this->assertStringContainsString('>Location</span>', $html);
        $this->assertStringContainsString($demande->numero, $html, 'Le règlement de la demande de livraison manque.');
        $this->assertStringContainsString('>Livraison</span>', $html);
        $this->assertStringContainsString('>Effectuée<', $html, 'L\'état « Effectuée » de la location manque.');
        $this->assertStringContainsString('Validée — en cours', $html, 'L\'état « à payer » de la livraison manque.');

        // Une commande d'un AUTRE client portant l'identifiant de la location ne
        // doit pas être affichée à sa place.
        $commande = \App\Models\Commande::find($location->id);
        if ($commande && (int) $commande->client_id !== (int) $client->id) {
            $this->assertStringNotContainsString($commande->numero, $html, 'Un règlement de location est pris pour une commande de même identifiant.');
        }
    }
}
