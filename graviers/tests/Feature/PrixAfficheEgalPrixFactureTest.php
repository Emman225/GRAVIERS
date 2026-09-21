<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Produit;
use App\Models\StockProduit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le prix montré au client est celui qui lui sera facturé.
 *
 * Le catalogue affichait `produit.prix_moyen` pendant que le panier retenait
 * Produit::prixPour() — le prix fournisseur le plus bas parmi les stocks
 * actifs. Les deux ne coïncidaient que par hasard :
 *
 *   - la Bétonnière 350 L était annoncée à 20 000 et arrivait au panier à 100 ;
 *   - la Brique Geo était annoncée à 150 et y passait à 5 000.
 *
 * Le client ne payait donc pas le prix qu'on lui avait montré, dans un sens
 * comme dans l'autre. L'alignement existait sur l'accueil et la recherche,
 * recopié à l'identique dans chacun, mais manquait à la LOCATION, à la seconde
 * page d'accueil et aux pages de catégorie.
 */
class PrixAfficheEgalPrixFactureTest extends TestCase
{
    use DatabaseTransactions;

    /** Un produit dont le prix catalogue diffère franchement du prix fournisseur. */
    private function unProduitAvecEcart(string $typeAffaire, float $prixMoyen, float $prixStock): Produit
    {
        $modele = Produit::first();
        $bon    = Enlevement::whereNotNull('fournisseur_id')->first();

        if (!$modele || !$bon) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        $produit = Produit::create([
            'nom'              => 'Produit ecart ' . uniqid(),
            'description'      => 'Produit créé pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => $typeAffaire,
            'prix_moyen'       => $prixMoyen,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        StockProduit::create([
            'fournisseur_id' => $bon->fournisseur_id,
            'produit_id'     => $produit->id,
            'qte'            => 50,
            'prix'           => $prixStock,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $produit->fresh();
    }

    /** Le prix que la liste rendue par une page affiche pour ce produit. */
    private function prixAffichePar(string $url, Produit $produit): ?float
    {
        URL::forceRootUrl('');
        $reponse = $this->get($url);
        $reponse->assertOk();

        foreach (collect($reponse->viewData('produits')) as $p) {
            if ($p->id === $produit->id) {
                return (float) $p->prix_moyen;
            }
        }

        return null;
    }

    public function test_le_catalogue_de_location_montre_le_prix_du_panier(): void
    {
        // Le cas signalé : annoncée 20 000, facturée 100.
        $produit = $this->unProduitAvecEcart('LOCATION', prixMoyen: 20000, prixStock: 100);

        // On ne fige plus le montant : depuis que le prix de vente porte le
        // pourcentage DALAKOUN, il dépend du taux en vigueur. Ce qui doit
        // rester vrai, quel que soit ce taux, c'est l'ÉGALITÉ entre le prix
        // montré et le prix facturé.
        $this->assertSame(
            round($produit->prixPour(null), 2),
            round($this->prixAffichePar('/location-materiel-construction', $produit) ?? -1, 2),
            'Le catalogue de location annonçait un prix que le panier ne confirmait pas.'
        );
    }

    public function test_l_alignement_traverse_la_pagination(): void
    {
        // Le cas inverse, plus grave : annoncee 150, facturee 5 000. Les pages
        // de boutique passent un PAGINATEUR et non une collection : l'oublier
        // laisserait la moitie du catalogue avec son ancien prix.
        $produit = $this->unProduitAvecEcart('VENTE', prixMoyen: 150, prixStock: 5000);

        $attendu = round($produit->prixPour(null), 2);

        $page = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([$produit->fresh()]), 1, 12, 1
        );

        Produit::alignerPrixAffiche($page);

        $this->assertSame($attendu, round((float) $page->getCollection()->first()->prix_moyen, 2),
            'Le prix annonce au catalogue doit etre celui que le panier facturera.');
    }

    public function test_un_produit_sans_stock_garde_son_prix_catalogue(): void
    {
        $modele = Produit::first();

        if (!$modele) {
            $this->markTestSkipped('Aucun produit.');
        }

        $produit = Produit::create([
            'nom'              => 'Produit sans stock ' . uniqid(),
            'description'      => 'Produit créé pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => 'LOCATION',
            'prix_moyen'       => 7500,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        // Aucun fournisseur ne l'a tarifé : le prix catalogue fait foi, des
        // deux côtés.
        $liste = collect([$produit]);
        Produit::alignerPrixAffiche($liste);

        $this->assertSame(7500.0, round((float) $liste->first()->prix_moyen, 2));
        $this->assertSame(7500.0, round($produit->prixPour(null), 2));
    }

    public function test_une_ligne_de_stock_desactivee_ne_fait_pas_le_prix(): void
    {
        $produit = $this->unProduitAvecEcart('VENTE', prixMoyen: 9000, prixStock: 300);

        StockProduit::where('produit_id', $produit->id)->update(['statut' => 0]);

        $liste = collect([$produit->fresh()]);
        Produit::alignerPrixAffiche($liste);

        // Le stock retiré du circuit ne dicte plus le prix — ni à l'écran, ni
        // au panier.
        // Le stock retire du circuit ne dicte plus le prix : le produit retombe
        // sur son prix catalogue, sans marge appliquee faute de cout connu.
        $this->assertSame(9000.0, round((float) $liste->first()->prix_moyen, 2));
        $this->assertSame(9000.0, round($produit->fresh()->prixPour(null), 2));
    }

    public function test_l_alignement_ne_depend_pas_du_nombre_de_produits(): void
    {
        $a = $this->unProduitAvecEcart('VENTE', prixMoyen: 100, prixStock: 900);
        $b = $this->unProduitAvecEcart('VENTE', prixMoyen: 200, prixStock: 800);
        $c = $this->unProduitAvecEcart('VENTE', prixMoyen: 300, prixStock: 700);

        // C'est l'INVARIANT qui compte, pas un nombre figé : le coût ne doit pas
        // croître avec la taille du catalogue. Appelé produit par produit,
        // prixPour() déclencherait une requête par article — 80 pour 80 produits.
        $mesure = function (array $produits): int {
            // Le journal s'accumule : sans purge, la seconde mesure contient
            // aussi les requêtes de la première.
            \Illuminate\Support\Facades\DB::enableQueryLog();
            \Illuminate\Support\Facades\DB::flushQueryLog();
            Produit::alignerPrixAffiche(collect($produits));
            $n = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $n;
        };

        $pourDeux  = $mesure([$a, $b]);
        $pourTrois = $mesure([$a, $b, $c]);

        $this->assertSame($pourDeux, $pourTrois,
            'Le nombre de requêtes doit rester le même quel que soit le nombre de produits.');
        $this->assertLessThanOrEqual(2, $pourDeux,
            'Une requête pour les prix d\'achat, une pour le taux en vigueur : pas davantage.');

        // Et le calcul reste juste — au taux en vigueur, quel qu'il soit.
        $this->assertSame(round($a->fresh()->prixPour(null), 2), round((float) $a->prix_moyen, 2));
        $this->assertSame(round($b->fresh()->prixPour(null), 2), round((float) $b->prix_moyen, 2));
    }
}
