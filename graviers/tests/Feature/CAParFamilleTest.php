<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'écran « Chiffre d'affaire par famille », au-delà du seul montant.
 *
 * Le calcul du chiffre d'affaires était juste ; tout le reste de l'écran ne
 * l'était pas.
 *
 *  - La TVA était écrite en dur à 0 % dans la vue et le « Montant TTC »
 *    recopiait le HT : l'écran annonçait 16 750 F là où le taux configuré
 *    (18 %) donne 19 765.
 *  - La page s'appelait « par famille » sans jamais regrouper par famille ni
 *    calculer le moindre sous-total.
 *  - Elle listait tous les produits, y compris désactivés — mais un simple
 *    filtre sur le statut aurait fait disparaître le chiffre d'affaires des
 *    produits retirés du catalogue APRÈS avoir vendu.
 *  - Le bandeau sommait `stock_produit` pendant que la colonne « Quantité »
 *    sommait le pivot sans filtrer statut ni lignes supprimées.
 *  - Un bon annulé mais déjà validé continuait de compter.
 *  - Aucun filtre de dates.
 */
class CAParFamilleTest extends TestCase
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

    private function ecran(array $filtres = []): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())
            ->get('/CA-par-famille' . ($filtres ? '?' . http_build_query($filtres) : ''));
        $reponse->assertOk();

        return $reponse;
    }

    private function lignes(array $familles): array
    {
        return $familles
            ? array_merge(...array_values(array_map(fn ($f) => $f['lignes'], $familles)))
            : [];
    }

    /** Un bon servi, dont on maîtrise la date, le produit et le montant. */
    /**
     * UN BON QUI COMPTE VRAIMENT DANS LES TOTAUX.
     *
     * La première version prenait le premier bon servi venu. En base d'essai
     * c'est un bon dont la course ne porte aucune ligne de commande : depuis
     * que cet écran cesse de lui inventer un prix de vente, il est mis de
     * côté et annoncé à part. Le modifier ne bougeait plus aucun total, et
     * l'essai mesurait le vide.
     */
    private function unBonServi(): Enlevement
    {
        $bon = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('produit_id')
            ->whereNull('deleted_at')
            ->get()
            ->first(fn ($b) => \App\Support\BonsDeVente::prixFacture($b) !== null);

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattaché à sa ligne de commande.');
        }

        return $bon;
    }

    // ------------------------------------------------------------------ TVA

    public function test_la_tva_est_celle_de_la_configuration(): void
    {
        $taux = (float) (Configuration::first()?->tva ?? 18);

        if ($taux <= 0) {
            $this->markTestSkipped('Aucune TVA configurée.');
        }

        $r = $this->ecran();

        $this->assertSame($taux, (float) $r->viewData('tauxTva'));

        $ht = (float) $r->viewData('totalHt');

        // Le TTC recopiait le HT : c'est très exactement ce que ceci interdit.
        $this->assertSame(
            round($ht * (1 + $taux / 100), 2),
            round((float) $r->viewData('totalTtc'), 2)
        );
        $this->assertSame(
            round($ht * $taux / 100, 2),
            round((float) $r->viewData('totalTva'), 2)
        );

        if ($ht > 0) {
            $this->assertGreaterThan(
                $ht,
                (float) $r->viewData('totalTtc'),
                'Le TTC doit dépasser le HT dès lors que la TVA est appliquée.'
            );
        }
    }

    public function test_chaque_ligne_porte_sa_propre_tva(): void
    {
        $taux   = (float) $this->ecran()->viewData('tauxTva');
        $lignes = $this->lignes($this->ecran()->viewData('familles'));

        if (!$lignes) {
            $this->markTestSkipped('Aucune ligne à contrôler.');
        }

        foreach ($lignes as $l) {
            $this->assertSame(round($l->ht * (1 + $taux / 100), 2), round($l->ttc, 2),
                "Le TTC de « {$l->nom} » ne découle pas de son HT.");
        }
    }

    // -------------------------------------------------------------- FAMILLES

    public function test_les_sous_totaux_de_famille_donnent_le_total_general(): void
    {
        $r        = $this->ecran();
        $familles = $r->viewData('familles');

        $this->assertSame(
            round(array_sum(array_column($familles, 'ht')), 2),
            round((float) $r->viewData('totalHt'), 2),
            'La somme des sous-totaux de famille doit faire le total général.'
        );
        $this->assertSame(
            round(array_sum(array_column($familles, 'qte')), 2),
            round((float) $r->viewData('qteTotal'), 2),
            'Le bandeau et la colonne Quantité doivent sortir de la même source.'
        );
    }

    public function test_chaque_famille_est_la_somme_de_ses_lignes(): void
    {
        $familles = $this->ecran()->viewData('familles');

        if (!$familles) {
            $this->markTestSkipped('Aucune famille.');
        }

        foreach ($familles as $f) {
            $this->assertSame(
                round(array_sum(array_map(fn ($l) => $l->ht, $f['lignes'])), 2),
                round($f['ht'], 2),
                "Le sous-total de « {$f['nom']} » ne fait pas la somme de ses lignes."
            );
        }
    }

    public function test_un_produit_n_apparait_que_dans_une_seule_famille(): void
    {
        $lignes = $this->lignes($this->ecran()->viewData('familles'));

        if (!$lignes) {
            $this->markTestSkipped('Aucune ligne à contrôler.');
        }

        $ids = array_map(fn ($l) => $l->id, $lignes);

        // Vingt produits relèvent de plusieurs familles : les faire figurer
        // dans chacune gonflerait la somme des sous-totaux au-delà du chiffre
        // d'affaires réel.
        $this->assertSame(count($ids), count(array_unique($ids)),
            'Un produit compté dans deux familles fausse la somme des sous-totaux.');
    }

    public function test_les_produits_sans_famille_ne_disparaissent_pas(): void
    {
        $sansFamille = Produit::where('statut', \Help::$STATUT_ACTIF)
            ->whereDoesntHave('categories')->count();

        if ($sansFamille === 0) {
            $this->markTestSkipped('Tous les produits actifs ont une famille.');
        }

        $familles = $this->ecran()->viewData('familles');

        $this->assertArrayHasKey('Sans famille', $familles,
            'Les produits sans famille doivent être regroupés, pas écartés du total.');
    }

    // -------------------------------------------------------------- PÉRIMÈTRE

    public function test_un_bon_annule_ne_compte_plus(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['qte_servi' => 5]);

        $avant = (float) $this->ecran()->viewData('qteVendue');

        // Validé par le fournisseur, mais annulé depuis.
        $bon->update(['statut' => 0]);

        $apres = (float) $this->ecran()->viewData('qteVendue');

        $this->assertSame(round($avant - 5, 2), round($apres, 2),
            "Un bon annulé continuait d'alimenter le chiffre d'affaires.");
    }

    public function test_un_produit_desactive_qui_a_vendu_reste_compte(): void
    {
        $bon     = $this->unBonServi();
        $produit = Produit::find($bon->produit_id);

        if (!$produit) {
            $this->markTestSkipped('Bon sans produit.');
        }

        $avant = (float) $this->ecran()->viewData('totalHt');

        // Retiré du catalogue APRÈS avoir vendu : son chiffre d'affaires reste
        // acquis. C'est le piège d'un simple filtre sur le statut.
        $produit->update(['statut' => 0]);

        $r      = $this->ecran();
        $apres  = (float) $r->viewData('totalHt');
        $ids    = array_map(fn ($l) => $l->id, $this->lignes($r->viewData('familles')));

        $this->assertSame(round($avant, 2), round($apres, 2),
            'Désactiver un produit ayant vendu ne doit rien retirer du chiffre d\'affaires.');
        $this->assertContains($produit->id, $ids,
            'Le produit désactivé mais vendeur doit rester listé.');
    }

    public function test_le_stock_desactive_ne_compte_pas_dans_la_quantite(): void
    {
        // La ligne de stock doit porter sur un produit RÉELLEMENT LISTÉ : le
        // stock des produits hors liste n'a jamais alimenté la colonne, et une
        // ligne prise au hasard ne mettrait donc rien en défaut.
        $bon = $this->unBonServi();

        if (!$bon->fournisseur_id) {
            $this->markTestSkipped('Bon sans fournisseur.');
        }

        // Le produit vendu n'a pas nécessairement de stock enregistré. On lui en
        // crée plutôt que de sauter le test : un test sauté ne prouve rien. La
        // transaction annule cette ligne à la fin.
        $ligneStock = StockProduit::create([
            'fournisseur_id' => $bon->fournisseur_id,
            'produit_id'     => $bon->produit_id,
            'qte'            => 250,
            'prix'           => 1000,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $avant = (float) $this->ecran()->viewData('qteTotal');

        $ligneStock->update(['statut' => 0]);

        $apres = (float) $this->ecran()->viewData('qteTotal');

        // Le bandeau et la colonne divergeaient dès qu'une ligne de stock
        // sortait du circuit : les deux la retiennent ou l'écartent ensemble.
        $this->assertSame(round($avant - (float) $ligneStock->qte, 2), round($apres, 2));

        $r = $this->ecran();
        $this->assertSame(
            round(array_sum(array_column($r->viewData('familles'), 'qte')), 2),
            round((float) $r->viewData('qteTotal'), 2)
        );
    }

    // ---------------------------------------------------------------- DATES

    public function test_le_filtre_de_dates_ecarte_les_bons_hors_periode(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['fournisseur_validation' => '2019-06-15 10:00:00', 'qte_servi' => 7]);

        // Le fait générateur est le SERVICE du bon, pas sa saisie.
        $dedans = (float) $this->ecran(['du' => '2019-06-01', 'au' => '2019-06-30'])
            ->viewData('qteVendue');
        $dehors = (float) $this->ecran(['du' => '2019-07-01', 'au' => '2019-07-31'])
            ->viewData('qteVendue');

        $this->assertGreaterThanOrEqual(7.0, $dedans);
        $this->assertSame(round($dedans - $dehors, 2), 7.0);
    }

    public function test_sans_filtre_la_periode_couvre_tout_l_historique(): void
    {
        $r = $this->ecran();

        $this->assertNull($r->viewData('du'));
        $this->assertNull($r->viewData('au'));
    }
}
