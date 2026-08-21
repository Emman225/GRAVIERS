<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La balance âgée.
 *
 * Elle ne vieillissait rien. `facture.date_echeance` n'est écrite NULLE PART —
 * ni écran, ni formulaire, ni service — donc toujours vide ; `joursRetard()`
 * retournait alors 0 pour toutes les factures, et la totalité des créances
 * s'entassait dans la première tranche. Aucune facture n'apparaissait jamais en
 * retard.
 *
 * S'y ajoutaient : le non échu compté comme du retard (une facture pas encore
 * due a zéro jour de retard, comme une facture échue du jour), un regroupement
 * sur le NOM du client qui fusionnait les homonymes, et l'absence de total par
 * tranche — la seule question qu'on pose vraiment à une balance âgée.
 */
class BalanceAgeeTest extends TestCase
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

    private function unClient(string $nom = 'Recette', int $delaiPaiement = 30): Client
    {
        return Client::create([
            'user_id'        => $this->unAdmin()->id,
            'nom'            => $nom,
            'prenom'         => 'Balance',
            'email'          => 'balance-' . uniqid() . '@example.test',
            'contact1'       => '0000000000',
            'type_client'    => 'ENTREPRISE',
            'client_a_terme' => 1,
            'delai_paiement' => $delaiPaiement,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);
    }

    /**
     * Une facture impayée, émise il y a $ilYA jours.
     *
     * L'échéance se déduisant de la date d'émission plus le délai accordé, c'est
     * en reculant l'émission qu'on fabrique un retard.
     */
    private function uneFactureImpayee(Client $client, float $montant, int $ilYA): Facture
    {
        $produit = Produit::first();

        if (!$produit) {
            $this->markTestSkipped('Aucun produit.');
        }

        $commande = Commande::create([
            'numero'    => 'B' . substr((string) uniqid(), -8),
            'client_id' => $client->id,
            'statut'    => \Help::$STATUT_ACTIF,
        ]);

        DetailCommande::create([
            'produit_id'     => $produit->id,
            'commande_id'    => $commande->id,
            'qte'            => 1,
            'prix'           => $montant,
            'statut'         => \Help::$STATUT_ACTIF,
            'etat_livraison' => 'EN ATTENTE',
        ]);

        $facture = Facture::create([
            'numero'     => 'FB' . substr((string) uniqid(), -8),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'montant'    => $montant,
            'statut'     => 2,
        ]);

        $facture->forceFill(['created_at' => now()->subDays($ilYA)])->save();

        return $facture->fresh();
    }

    /** La ligne de balance d'un client donné. */
    private function ligneDe(Client $client): ?object
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/Balance-agee');
        $reponse->assertOk();

        return collect($reponse->viewData('lignes'))
            ->firstWhere('client_id', $client->id);
    }

    private function totaux(): array
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/Balance-agee');
        $reponse->assertOk();

        return $reponse->viewData('totaux');
    }

    // ------------------------------------------------------------- ÉCHÉANCE

    public function test_l_echeance_se_deduit_du_delai_de_paiement(): void
    {
        $client  = $this->unClient(delaiPaiement: 30);
        $facture = $this->uneFactureImpayee($client, 10000, ilYA: 100);

        // La colonne est vide — elle l'est sur toutes les factures.
        $this->assertNull($facture->date_echeance);

        $this->assertNotNull($facture->echeance(),
            'Sans échéance déduite, aucune facture ne peut jamais être en retard.');
        $this->assertSame(70, $facture->joursRetard());
        $this->assertTrue($facture->estEchue());
    }

    public function test_une_facture_recente_n_est_pas_en_retard(): void
    {
        $client  = $this->unClient(delaiPaiement: 30);
        $facture = $this->uneFactureImpayee($client, 10000, ilYA: 5);

        $this->assertSame(0, $facture->joursRetard());
        $this->assertFalse($facture->estEchue());
        $this->assertSame('À échoir', $facture->statutCreance());
    }

    // ------------------------------------------------------------- TRANCHES

    public function test_le_non_echu_ne_compte_pas_comme_du_retard(): void
    {
        $client = $this->unClient(delaiPaiement: 30);
        $this->uneFactureImpayee($client, 40000, ilYA: 5);

        $ligne = $this->ligneDe($client);

        $this->assertNotNull($ligne);

        // Il tombait dans « 0 à 30 jours » : la première tranche mélangeait le
        // non exigible et le retard récent.
        $this->assertSame(40000.0, round($ligne->non_echu, 2));
        $this->assertSame(0.0, round($ligne->t1_30, 2));
    }

    public function test_chaque_retard_tombe_dans_sa_tranche(): void
    {
        $client = $this->unClient(delaiPaiement: 30);

        // Émise il y a 45 j, échue à 30 j : 15 jours de retard.
        $this->uneFactureImpayee($client, 1000, ilYA: 45);
        // 75 j - 30 j = 45 jours de retard.
        $this->uneFactureImpayee($client, 2000, ilYA: 75);
        // 430 j - 30 j = 400 jours de retard.
        $this->uneFactureImpayee($client, 4000, ilYA: 430);

        $ligne = $this->ligneDe($client);

        $this->assertSame(1000.0, round($ligne->t1_30, 2));
        $this->assertSame(2000.0, round($ligne->t31_60, 2));
        $this->assertSame(4000.0, round($ligne->t360_plus, 2));
        $this->assertSame(7000.0, round($ligne->total, 2));
    }

    public function test_les_totaux_par_tranche_font_le_total_general(): void
    {
        $client = $this->unClient(delaiPaiement: 30);
        $this->uneFactureImpayee($client, 1000, ilYA: 45);
        $this->uneFactureImpayee($client, 2000, ilYA: 5);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/Balance-agee');
        $reponse->assertOk();

        $totaux = $reponse->viewData('totaux');

        // L'état n'avait aucun total par tranche : il ne disait pas combien
        // dormait à plus de 360 jours.
        $this->assertSame(
            round(array_sum($totaux), 2),
            round((float) $reponse->viewData('totalGeneral'), 2)
        );
    }

    // -------------------------------------------------------- REGROUPEMENT

    public function test_deux_clients_homonymes_ne_fusionnent_pas(): void
    {
        $unNom = 'Homonyme ' . uniqid();

        $premier = $this->unClient(nom: $unNom);
        $second  = $this->unClient(nom: $unNom);

        $this->uneFactureImpayee($premier, 1000, ilYA: 45);
        $this->uneFactureImpayee($second, 3000, ilYA: 45);

        // Le regroupement se faisait sur le NOM : les deux ne faisaient qu'une
        // ligne de 4 000.
        $this->assertSame(1000.0, round($this->ligneDe($premier)->total, 2));
        $this->assertSame(3000.0, round($this->ligneDe($second)->total, 2));
    }

    // ---------------------------------------------------------- PÉRIMÈTRE

    public function test_une_creance_soldee_sort_de_la_balance(): void
    {
        $client  = $this->unClient(delaiPaiement: 30);
        $facture = $this->uneFactureImpayee($client, 5000, ilYA: 45);

        $this->assertNotNull($this->ligneDe($client));

        \App\Models\Paiement::create([
            'client_id'     => $client->id,
            'code'          => 'PB' . substr((string) uniqid(), -8),
            'libelle'       => 'Règlement de recette',
            'montant_total' => 5000,
            'facture_id'    => $facture->id,
            'statut'        => 1,
        ]);

        $this->assertNull($this->ligneDe($client),
            'Une créance soldée ne doit plus figurer dans la balance.');
    }

    public function test_la_balance_retombe_sur_l_etat_client_a_terme(): void
    {
        $client = $this->unClient(delaiPaiement: 30);
        $this->uneFactureImpayee($client, 1000, ilYA: 45);
        $this->uneFactureImpayee($client, 2000, ilYA: 5);

        URL::forceRootUrl('');
        $etat = $this->actingAs($this->unAdmin())->get('/client-a-terme');
        $etat->assertOk();

        $balance = $this->actingAs($this->unAdmin())->get('/Balance-agee');
        $balance->assertOk();

        $this->assertSame(
            round((float) $etat->viewData('totalSolde'), 2),
            round((float) $balance->viewData('totalGeneral'), 2)
        );
    }

    public function test_la_page_repond_sans_aucune_creance(): void
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/Balance-agee');

        $reponse->assertOk();

        if (count($reponse->viewData('lignes')) === 0) {
            $reponse->assertSee('Aucune créance ouverte.');
        }
    }
}
