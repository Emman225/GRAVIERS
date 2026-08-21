<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'état « Client à terme » et la balance âgée.
 *
 * Le dû y était recalculé depuis TOUTE la commande — quantité COMMANDÉE × prix,
 * frais de livraison entiers, remise ignorée — alors qu'une facture est émise
 * sur les enlèvements réellement SERVIS et qu'une commande peut en produire
 * plusieurs. Trois conséquences :
 *
 *   - la créance dépassait ce qui avait été réclamé au client ;
 *   - elle se comptait autant de fois que la commande avait de factures ;
 *   - un reste subsistait quoi que le client paie : la dette était insoldable.
 *
 * Le correctif existait déjà sur « Créances / Factures » sans avoir été
 * reporté ici. S'y ajoutaient une facture de location rattachée à la commande
 * portant le même numéro, une colonne « Statut » qui se prononçait sur un
 * autre total que la colonne « Solde », et la disparition des créances d'un
 * client suspendu.
 */
class CreanceClientATermeTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    /**
     * Un client à terme tout neuf, avec une commande de 100 000 F HT.
     *
     * On ne réutilise aucun client existant : la fixture doit mettre la règle
     * en défaut, pas se ranger derrière des données déjà cohérentes.
     */
    private function clientAvecCommande(float $htCommande = 100000): array
    {
        $admin   = $this->unAdmin();
        $produit = Produit::first();

        if (!$produit) {
            $this->markTestSkipped('Aucun produit.');
        }

        $client = Client::create([
            'user_id'        => $admin->id,
            'nom'            => 'Recette',
            'prenom'         => 'Creance',
            'email'          => 'recette-creance-' . uniqid() . '@example.test',
            'contact1'       => '0000000000',
            'type_client'    => 'ENTREPRISE',
            'client_a_terme' => 1,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $commande = Commande::create([
            'numero'                => 'T' . substr((string) uniqid(), -8),
            'client_id'             => $client->id,
            'statut'                => \Help::$STATUT_ACTIF,
            'cout_livraison_client' => 20000,
            'remise'                => 5000,
        ]);

        DetailCommande::create([
            'produit_id'     => $produit->id,
            'commande_id'    => $commande->id,
            'qte'            => 10,
            'prix'           => $htCommande / 10,
            'statut'         => \Help::$STATUT_ACTIF,
            'etat_livraison' => 'EN ATTENTE',
        ]);

        return [$client, $commande];
    }

    private function uneFacture(Client $client, Commande $commande, float $montant): Facture
    {
        return Facture::create([
            'numero'     => 'F' . substr((string) uniqid(), -9),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'montant'    => $montant,
            'statut'     => 2,
        ]);
    }

    private function unPaiement(Client $client, Facture $facture, float $montant, int $statut = 1): Paiement
    {
        return Paiement::create([
            'client_id'     => $client->id,
            'code'          => 'P' . substr((string) uniqid(), -9),
            'libelle'       => 'Règlement de recette',
            'montant_total' => $montant,
            'facture_id'    => $facture->id,
            'statut'        => $statut,
        ]);
    }

    /** Les lignes de l'écran, pour un client donné. */
    private function lignesDe(Client $client): array
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/client-a-terme');
        $reponse->assertOk();

        return collect($reponse->viewData('lignes'))
            ->filter(fn ($l) => $l->client_id === $client->id)
            ->values()->all();
    }

    // ------------------------------------------------- LE DÛ EST CELUI DE LA FACTURE

    public function test_le_du_est_celui_de_la_facture_et_non_de_la_commande(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        // Livraison partielle : on ne facture que 30 000 des 100 000 commandés.
        $this->uneFacture($client, $commande, 30000);

        $lignes = $this->lignesDe($client);

        $this->assertCount(1, $lignes);
        $this->assertSame(30000.0, round($lignes[0]->total_a_payer, 2),
            'La créance doit valoir la facture, et non la commande entière.');
    }

    public function test_une_commande_facturee_en_deux_fois_ne_compte_pas_deux_fois(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        $this->uneFacture($client, $commande, 40000);
        $this->uneFacture($client, $commande, 25000);

        $lignes = $this->lignesDe($client);

        $this->assertCount(2, $lignes);

        // Chaque facture apportait le total de la COMMANDE : le client devait
        // deux fois la même somme.
        $this->assertSame(65000.0,
            round(array_sum(array_map(fn ($l) => $l->total_a_payer, $lignes)), 2));
    }

    public function test_le_client_peut_solder_sa_dette(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        $facture = $this->uneFacture($client, $commande, 30000);
        $this->unPaiement($client, $facture, 30000);

        $lignes = $this->lignesDe($client);

        // Il paie très exactement sa facture : il ne doit plus rien. Le dû étant
        // calculé sur la commande, un reste subsistait quoi qu'il verse.
        $this->assertSame(0.0, round($lignes[0]->reste, 2));
        $this->assertSame('Soldée', $lignes[0]->facture->statutCreance());
    }

    public function test_le_statut_et_le_solde_parlent_du_meme_total(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        $facture = $this->uneFacture($client, $commande, 30000);
        $this->unPaiement($client, $facture, 30000);

        $ligne = $this->lignesDe($client)[0];

        // La colonne « Statut » se prononçait sur facture.montant pendant que la
        // colonne « Solde » se calculait sur la commande : une ligne pouvait
        // afficher « Soldée » avec un solde non nul.
        $this->assertSame(
            $ligne->facture->statutCreance() === 'Soldée',
            round($ligne->reste, 2) === 0.0,
            'Le statut affiché doit s\'accorder avec le solde affiché.'
        );
    }

    public function test_un_paiement_non_valide_ne_solde_rien(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        $facture = $this->uneFacture($client, $commande, 30000);
        $this->unPaiement($client, $facture, 30000, statut: 2);

        $this->assertSame(30000.0, round($this->lignesDe($client)[0]->reste, 2));
    }

    // -------------------------------------------------------------- LOCATIONS

    public function test_une_facture_de_location_ne_prend_pas_le_montant_d_une_commande(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);

        // `service_id` désigne ici une LOCATION. La relation commande() irait
        // pourtant chercher la commande portant ce même identifiant — une autre
        // affaire, dont le montant n'a rien à voir avec cette facture.
        $facture = Facture::create([
            'numero'     => 'L' . substr((string) uniqid(), -9),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$LOCATION,
            'service_id' => $commande->id,
            'montant'    => 7500,
            'statut'     => 2,
        ]);

        $ligne = collect($this->lignesDe($client))
            ->firstWhere('numero', $facture->numero);

        $this->assertNotNull($ligne);
        $this->assertSame(7500.0, round($ligne->total_a_payer, 2));
    }

    // ---------------------------------------------------------------- CLIENTS

    public function test_un_client_suspendu_doit_toujours_sa_creance(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);
        $this->uneFacture($client, $commande, 30000);

        $this->assertCount(1, $this->lignesDe($client));

        // Le suspendre ne l'acquitte pas.
        $client->update(['statut' => 0]);

        $lignes = $this->lignesDe($client);

        $this->assertCount(1, $lignes, 'Suspendre un client effaçait sa dette de la balance.');
        $this->assertSame(30000.0, round($lignes[0]->total_a_payer, 2));
    }

    // ---------------------------------------------------------- BALANCE ÂGÉE

    public function test_la_balance_agee_ventile_exactement_les_memes_restes(): void
    {
        [$client, $commande] = $this->clientAvecCommande(100000);
        $this->uneFacture($client, $commande, 30000);

        URL::forceRootUrl('');
        $etat = $this->actingAs($this->unAdmin())->get('/client-a-terme');
        $etat->assertOk();

        $balance = $this->actingAs($this->unAdmin())->get('/Balance-agee');
        $balance->assertOk();

        // Les deux écrans partagent le même calcul : leurs totaux ne peuvent
        // pas diverger.
        $this->assertSame(
            round((float) $etat->viewData('totalSolde'), 2),
            round((float) $balance->viewData('totalGeneral'), 2)
        );
    }

    // ------------------------------------------------------------- TABLE VIDE

    public function test_la_page_repond_sans_aucune_creance(): void
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/client-a-terme');

        $reponse->assertOk();

        // Une table vide sans ligne de repli fait échouer DataTables sur
        // « Requested unknown parameter ».
        if (count($reponse->viewData('lignes')) === 0) {
            $reponse->assertSee('Aucune créance client à terme.');
        }
    }
}
