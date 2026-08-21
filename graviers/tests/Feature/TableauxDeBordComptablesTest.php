<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\ModePaiement;
use App\Models\PaiementFournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Les deux tableaux de bord : créances clients et dettes partenaires.
 *
 * CRÉANCES. L'écran écartait les clients suspendus — suspendre un client
 * retirait sa dette du total de l'entreprise — et recalculait le dû facture par
 * facture au lieu de passer par Facture::totalAPayer(), soit une définition de
 * plus après celles déjà corrigées ailleurs.
 *
 * DETTES. Depuis que la validation d'une demande de paiement ÉCRIT sa ligne de
 * règlement, cette demande est comptée par montantPaye(). Le tableau de bord la
 * rajoutait pourtant à la main : le versement apparaissait DEUX FOIS et la
 * dette restante s'en trouvait minorée d'autant.
 */
class TableauxDeBordComptablesTest extends TestCase
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

    private function ecran(string $url): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get($url);
        $reponse->assertOk();

        return $reponse;
    }

    // ================================================== CRÉANCES CLIENTS

    /** Un client à terme, une commande, une facture impayée. */
    private function uneCreance(float $montant): array
    {
        $client = Client::create([
            'user_id'        => $this->unAdmin()->id,
            'nom'            => 'Bord',
            'prenom'         => 'Recette',
            'email'          => 'bord-' . uniqid() . '@example.test',
            'contact1'       => '0000000000',
            'type_client'    => 'ENTREPRISE',
            'client_a_terme' => 1,
            'delai_paiement' => 30,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $commande = Commande::create([
            'numero'    => 'TB' . substr((string) uniqid(), -8),
            'client_id' => $client->id,
            'statut'    => \Help::$STATUT_ACTIF,
        ]);

        $facture = Facture::create([
            'numero'     => 'FTB' . substr((string) uniqid(), -7),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'montant'    => $montant,
            'statut'     => 2,
        ]);

        return [$client, $facture];
    }

    public function test_la_creance_d_un_client_suspendu_reste_au_tableau_de_bord(): void
    {
        [$client] = $this->uneCreance(80000);

        $avant = (float) $this->ecran('/recap-creances/tableau-de-bord')->viewData('totalCreances');

        // Le suspendre ne l'acquitte pas.
        $client->update(['statut' => 0]);

        $apres = (float) $this->ecran('/recap-creances/tableau-de-bord')->viewData('totalCreances');

        $this->assertSame(round($avant, 2), round($apres, 2),
            'Suspendre un client retirait sa dette du total de l\'entreprise.');
    }

    public function test_le_du_du_tableau_de_bord_est_celui_du_modele(): void
    {
        [$client, $facture] = $this->uneCreance(80000);

        $avant = (float) $this->ecran('/recap-creances/tableau-de-bord')->viewData('totalCreances');

        // Le tableau de bord doit voir très exactement ce que la facture dit devoir.
        $this->assertSame(80000.0, round($facture->fresh()->resteAPayer(), 2));
        $this->assertGreaterThanOrEqual(80000.0, $avant);
    }

    public function test_une_facture_de_location_ne_prend_pas_le_client_d_une_commande(): void
    {
        [$client, $facture] = $this->uneCreance(80000);

        $location = Facture::create([
            'numero'     => 'LTB' . substr((string) uniqid(), -7),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$LOCATION,
            'service_id' => $facture->service_id,
            'montant'    => 12000,
            'statut'     => 2,
        ]);

        // Émise il y a longtemps : elle doit apparaître comme échue, au nom du
        // bon client. `service_id` pointant une location, la relation commande()
        // ramenait une commande sans rapport — et son client avec elle.
        $location->forceFill(['created_at' => now()->subDays(200)])->save();

        $echues = collect($this->ecran('/recap-creances/tableau-de-bord')->viewData('creancesEchues'));

        $ligne = $echues->firstWhere('reference', $location->numero);

        $this->assertNotNull($ligne, 'La facture de location échue doit être listée.');
        $this->assertSame($client->display_name, $ligne->client);
    }

    // ================================================== DETTES PARTENAIRES

    public function test_une_demande_deja_imputee_n_est_pas_comptee_deux_fois(): void
    {
        if (!Schema::hasColumn('paiement_fournisseur', 'demande_paiement_id')) {
            $this->markTestSkipped('Colonne demande_paiement_id absente.');
        }

        $bon = Enlevement::where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('fournisseur_id')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon actif.');
        }

        $userFournisseur = User::where('type_user_id', 5)->first();
        $mode            = ModePaiement::first();

        if (!$userFournisseur || !$mode) {
            $this->markTestSkipped('Fournisseur ou mode de paiement manquant.');
        }

        $avant = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalPaye');

        $demande = DemandePaiement::create([
            'user_id'          => $userFournisseur->id,
            'mode_paiement_id' => $mode->id,
            'montant'          => 30000,
            'paye'             => 1,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        // La validation de la demande écrit sa ligne de règlement : c'est elle
        // qui la fait entrer dans montantPaye().
        PaiementFournisseur::create([
            'enlevement_id'       => $bon->id,
            'fournisseur_id'      => $bon->fournisseur_id,
            'montant'             => 30000,
            'date_paiement'       => now()->toDateString(),
            'statut'              => 1,
            'demande_paiement_id' => $demande->id,
        ]);

        $apres = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalPaye');

        // Un seul versement de 30 000, donc 30 000 de plus — et non 60 000.
        $this->assertSame(30000.0, round($apres - $avant, 2),
            'Le versement issu de la demande était compté deux fois.');
    }

    public function test_une_demande_sans_reglement_reste_comptee(): void
    {
        $userFournisseur = User::where('type_user_id', 5)->first();
        $mode            = ModePaiement::first();

        if (!$userFournisseur || !$mode) {
            $this->markTestSkipped('Fournisseur ou mode de paiement manquant.');
        }

        $avant = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalPaye');

        // Réglée avant la mise en place de l'imputation : aucune ligne de
        // règlement ne la porte, elle doit donc continuer de compter.
        DemandePaiement::create([
            'user_id'          => $userFournisseur->id,
            'mode_paiement_id' => $mode->id,
            'montant'          => 17000,
            'paye'             => 1,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        $apres = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalPaye');

        $this->assertSame(17000.0, round($apres - $avant, 2));
    }

    public function test_un_bon_annule_n_engage_plus_rien(): void
    {
        $bon = Enlevement::where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('fournisseur_id')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon actif.');
        }

        $avant = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalEngage');

        $du = $bon->montantDu();
        $bon->update(['statut' => 0]);

        $apres = (float) $this->ecran('/recap-dettes/tableau-bord')->viewData('totalEngage');

        $this->assertSame(round($avant - $du, 2), round($apres, 2),
            'Un bon annulé continuait de gonfler la dette fournisseur.');
    }
}
