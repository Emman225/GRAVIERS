<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LES FILTRES DU CATALOGUE MOBILE FILTRENT.
 *
 * Le panneau « Recherchez un produit » propose trois critères. Deux ne
 * fonctionnaient pas :
 *
 *   · CATÉGORIE — la condition vivait dans un `leftJoin`. Une jointure externe
 *     ne retire aucune ligne : elle se contente de ne rien rattacher. Cocher
 *     « Gravier » (4 produits) ou « Sable » (3) rendait les 25 produits dans les
 *     deux cas. Le filtre n'a jamais rien filtré.
 *
 *   · MONTANT — la requête interrogeait la colonne `produit.prix_moyen`. Depuis
 *     que le prix de vente se calcule, cette colonne n'est plus ce que le client
 *     voit : une barre de fer affichée 5 136 F y est stockée à 4 800.
 *     L'application renvoyait le prix AFFICHÉ, la requête cherchait le prix
 *     STOCKÉ : aucun produit ne revenait jamais.
 *
 * Et les critères s'ajoutaient au lieu de se cumuler (`orWhereIn`) : cocher une
 * catégorie puis un produit rendait ce produit même hors de la catégorie.
 */
class FiltresCatalogueTest extends TestCase
{
    use DatabaseTransactions;

    /** Les produits que le catalogue sait afficher, filtres au repos. */
    private function catalogue()
    {
        $liste = Produit::liste(null, 3000);

        if ($liste->count() === 0) {
            $this->markTestSkipped('Catalogue vide.');
        }

        return $liste;
    }

    public function test_le_filtre_par_categorie_restreint_vraiment(): void
    {
        $catalogue = $this->catalogue();
        $ids = $catalogue->pluck('id')->all();

        $categorie = Categorie::whereIn(
            'id',
            DB::table('categorie_produit')->whereIn('produit_id', $ids)->pluck('categorie_id')
        )->first();

        if (!$categorie) {
            $this->markTestSkipped('Aucune catégorie rattachée à un produit affichable.');
        }

        $dansLaCategorie = DB::table('categorie_produit')
            ->where('categorie_id', $categorie->id)->pluck('produit_id')->all();

        $attendu = count(array_intersect($ids, $dansLaCategorie));

        $this->assertGreaterThan(0, $attendu, 'Fixture inutile : la catégorie est vide.');
        $this->assertLessThan($catalogue->count(), $attendu,
            "Fixture complaisante : cette catégorie contient TOUT le catalogue, "
            . "le filtre paraîtrait bon même cassé.");

        $rendu = Produit::liste(null, 3000, [$categorie->id]);

        $this->assertEquals($attendu, $rendu->count(),
            "Le filtre doit rendre les produits de la catégorie, et eux seuls.");

        // Et chacun appartient bien à la catégorie cochée.
        foreach ($rendu as $p) {
            $this->assertContains($p->id, $dansLaCategorie);
        }
    }

    public function test_le_filtre_par_montant_porte_sur_le_prix_affiche(): void
    {
        $catalogue = $this->catalogue();

        $prixAffiche = (int) round((float) $catalogue->first()->prix_moyen);
        $prixStocke  = (int) round((float) DB::table('produit')
            ->where('id', $catalogue->first()->id)->value('prix_moyen'));

        $attendu = $catalogue->filter(
            fn ($p) => (int) round((float) $p->prix_moyen) === $prixAffiche
        )->count();

        $rendu = Produit::liste(null, 3000, [], [], [$prixAffiche]);

        $this->assertEquals($attendu, $rendu->count(),
            "Le filtre doit porter sur le montant que le client a vu.");

        $this->assertGreaterThan(0, $rendu->count(),
            "Un montant pris dans le catalogue lui-même ne peut pas rendre zéro produit.");

        // Le coeur du défaut : les deux prix diffèrent depuis la réforme des
        // marges. Si un jour ils redevenaient identiques, ce test cesserait de
        // prouver quoi que ce soit — autant le dire ici.
        if ($prixAffiche === $prixStocke) {
            $this->markTestIncomplete(
                'Prix affiché et prix stocké identiques : le cas testé ne se distingue plus.'
            );
        }
    }

    public function test_les_criteres_se_cumulent_au_lieu_de_s_ajouter(): void
    {
        $catalogue = $this->catalogue();
        $ids = $catalogue->pluck('id')->all();

        $categorie = Categorie::whereIn(
            'id',
            DB::table('categorie_produit')->whereIn('produit_id', $ids)->pluck('categorie_id')
        )->first();

        if (!$categorie) {
            $this->markTestSkipped('Aucune catégorie rattachée à un produit affichable.');
        }

        $dansLaCategorie = DB::table('categorie_produit')
            ->where('categorie_id', $categorie->id)->pluck('produit_id')->all();

        // Un produit affichable qui N'EST PAS dans cette catégorie.
        $intrus = collect($ids)->first(fn ($id) => !in_array($id, $dansLaCategorie));

        if (!$intrus) {
            $this->markTestSkipped('Tous les produits sont dans cette catégorie.');
        }

        $rendu = Produit::liste(null, 3000, [$categorie->id], [$intrus]);

        $this->assertEquals(0, $rendu->count(),
            "Cocher une catégorie ET un produit qui n'y est pas ne doit rien rendre : "
            . "un filtre restreint, il n'ajoute pas.");
    }

    public function test_les_filtres_au_repos_rendent_tout_le_catalogue(): void
    {
        // Le cas ordinaire — ouvrir l'écran sans rien cocher — ne doit pas être
        // abîmé par les corrections ci-dessus.
        $this->assertGreaterThan(0, Produit::liste(null, 3000, [], [], [])->count());
    }

    public function test_un_produit_inactif_ne_revient_par_aucun_critere(): void
    {
        $inactif = Produit::where('statut', '!=', \Help::$STATUT_ACTIF)->first();

        if (!$inactif) {
            $this->markTestSkipped('Aucun produit inactif en base.');
        }

        $this->assertEquals(0, Produit::liste(null, 3000, [], [$inactif->id])->count(),
            "Un produit retiré du catalogue ne doit pas reparaître par le filtre.");
    }
}
