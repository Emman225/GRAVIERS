<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA TVA SUR LE TRANSPORT EST UNE OPTION, ET ELLE EST DÉSACTIVÉE PAR DÉFAUT.
 *
 * Le site ajoutait 18 % au coût du transport, l'application mobile non : la même
 * course coûtait 23 600 F depuis le site et 20 000 F depuis le téléphone.
 * L'arbitrage du 13/08/2026 avait supprimé la taxe ; le responsable demande
 * aujourd'hui de pouvoir la rétablir — sur décision, jamais par défaut.
 *
 * Ce que ce fichier tient :
 *   · sans décision, le transport reste hors taxe — poser cette version ne
 *     change AUCUN prix ;
 *   · l'option activée, la taxe s'applique et se retrouve dans tva_commande,
 *     rattachée à la bonne affaire ;
 *   · l'écran des paramètres sait activer ET désactiver — une case décochée
 *     n'étant pas transmise, la désactivation est le cas qui casse en silence.
 */
class TvaTransportOptionTest extends TestCase
{
    use DatabaseTransactions;

    private function laConfig(): Configuration
    {
        $config = Configuration::first();

        if (!$config) {
            $this->markTestSkipped('Aucune configuration en base.');
        }

        return $config;
    }

    /** Une tranche de la grille qui couvre 0 km : le trajet du test est nul. */
    private function uneTranche(): CoutLivraison
    {
        $tranche = CoutLivraison::where('distance_min_km', '<=', 0)
            ->where('distance_max_km', '>=', 0)
            ->where('prix_km', '>', 0)
            ->whereNull('ville_id')
            ->first();

        if (!$tranche) {
            $this->markTestSkipped('Aucune tranche de grille couvrant 0 km.');
        }

        return $tranche;
    }

    private function unClient(): User
    {
        $client = Client::whereNotNull('user_id')->first();

        if (!$client || !$client->user) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client->user;
    }

    /** Chiffre une demande de livraison et rend la session obtenue. */
    private function chiffrerUneCourse(CoutLivraison $tranche): \Illuminate\Testing\TestResponse
    {
        // Départ et arrivée confondus : la distance vaut 0 km, ce qui tombe dans
        // la tranche choisie. Le test porte sur la taxe, pas sur le trajet.
        return $this->actingAs($this->unClient())->post(route('client.recapLivraison'), [
            'long'  => -4.0, 'lat'  => 5.3, 'ville'  => 'Abidjan', 'affichage'  => 'Yopougon',
            'long1' => -4.0, 'lat1' => 5.3, 'ville1' => 'Abidjan', 'affichage1' => 'Yopougon',

            'produit'     => ['Sable'],
            'qte'         => [max(1, (float) $tranche->unite_min)],
            'unite'       => [$tranche->unite_produit_id],
            'description' => ['Course de test'],

            'date'           => date('Y-m-d'),
            'paiement'       => \App\Models\ModePaiement::first()?->id,
            'type_livraison' => 'STANDARD',
        ]);
    }

    public function test_sans_decision_le_transport_reste_hors_taxe(): void
    {
        $tranche = $this->uneTranche();

        $this->laConfig()->update(['tva_transport' => 0]);

        $this->chiffrerUneCourse($tranche);

        // Le prix annoncé est celui de la grille, sans un franc de plus.
        $this->assertEquals(0, (float) session('montantTva'),
            "Le transport doit rester hors taxe tant que l'option n'est pas activée.");

        $this->assertEquals((float) $tranche->prix_km, (float) session('montant_total'));
    }

    public function test_option_activee_la_taxe_s_applique(): void
    {
        $tranche = $this->uneTranche();
        $config  = $this->laConfig();

        $config->update(['tva_transport' => 1]);

        $this->chiffrerUneCourse($tranche);

        $attendu = round((float) $tranche->prix_km * ((float) $config->tva) / 100);

        $this->assertEquals($attendu, (float) session('montantTva'),
            'La TVA doit être calculée sur le tarif de la grille.');

        // Le HT ne bouge pas : la taxe s'ajoute, elle ne se prélève pas dedans.
        $this->assertEquals((float) $tranche->prix_km, (float) session('montant_total'));

        $config->update(['tva_transport' => 0]);
    }

    public function test_l_option_est_desactivee_par_defaut(): void
    {
        // La colonne existe et vaut 0 : poser la migration en production ne
        // change aucun prix tant que personne n'a décidé le contraire.
        $colonne = \Illuminate\Support\Facades\DB::selectOne(
            "SHOW COLUMNS FROM configuration LIKE 'tva_transport'"
        );

        $this->assertNotNull($colonne, "La colonne tva_transport doit exister.");
        $this->assertEquals('0', (string) $colonne->Default,
            'La TVA sur le transport doit être désactivée par défaut.');
    }

    public function test_l_ecran_des_parametres_active_et_desactive(): void
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        $config = $this->laConfig();
        $base   = ['tva' => $config->tva, 'montant_point' => $config->montant_point];

        // La case existe bel et bien a l ecran : sans elle, le reglage ne serait
        // pilotable que depuis la base.
        $this->actingAs($admin)->get(route('show.parametre'))
            ->assertOk()
            ->assertSee('name="tva_transport"', false);

        // Activation.
        $this->actingAs($admin)->post(route('show.parametre'), $base + ['tva_transport' => 1]);
        $this->assertEquals(1, (int) $config->fresh()->tva_transport);

        // Désactivation : une case décochée n'est PAS transmise par le
        // navigateur. Sans repli côté serveur, le réglage resterait activé pour
        // toujours — c'est ce cas-là qui casse en silence.
        $this->actingAs($admin)->post(route('show.parametre'), $base);
        $this->assertEquals(0, (int) $config->fresh()->tva_transport,
            "Décocher la case doit réellement désactiver la TVA sur le transport.");
    }

    public function test_la_facture_et_la_dgi_suivent_la_taxe_reellement_portee(): void
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        $client = Client::whereNotNull('user_id')->first();

        if (!$admin || !$client) {
            $this->markTestSkipped('Administrateur ou client manquant.');
        }

        $demande = \App\Models\DemandeLivraison::create([
            'numero'        => 'DL-' . substr(uniqid(), -8),
            'client_id'     => $client->id,
            'montantTotal'  => 20000,
            'remise'        => 0,
            'etat_commande' => 'EN ATTENTE',
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        \App\Models\TvaCommande::create([
            'client_id'    => $client->id,
            'commande_id'  => $demande->id,
            'montant'      => 3600,
            'type_affaire' => \Help::$LIVRAISON,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($admin)->post(route('orders.genererFactureLivraison', $demande->id));

        $facture = \App\Models\Facture::where('service', \Help::$LIVRAISON)
            ->where('service_id', $demande->id)->first();

        $this->assertNotNull($facture, 'La facture de transport doit être créée.');

        // La facture réclame le TTC : 20 000 de course + 3 600 de taxe.
        $this->assertEquals(23600, (float) $facture->montant);

        // Et le message envoyé à la DGI déclare un code de taxe à 18 %, pas le
        // code d'exonération. Déclarer 0 % sur une course taxée opposerait à
        // l'administration une facture qui contredit l'encaissement.
        $payload = \App\Services\FneService::buildLivraisonPayload($facture->fresh());

        $this->assertEquals([config('fne.defaults.tax', 'TVA')], $payload['items'][0]['taxes']);

        // La course sans taxe, elle, garde le code d'exonération.
        \App\Models\TvaCommande::where('commande_id', $demande->id)
            ->where('type_affaire', \Help::$LIVRAISON)->forceDelete();

        $payload = \App\Services\FneService::buildLivraisonPayload($facture->fresh());

        $this->assertEquals(
            [config('fne.defaults.delivery_tax', 'TVAC')],
            $payload['items'][0]['taxes']
        );
    }
}
