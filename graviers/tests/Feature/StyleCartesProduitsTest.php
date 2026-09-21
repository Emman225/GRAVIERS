<?php

namespace Tests\Feature;

use App\Models\Categorie;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE MEME DESSIN DE CARTE SUR TOUS LES CATALOGUES.
 *
 * Les regles vivaient dans un bloc <style> de la page d'accueil, portees par
 * « .product-grid-4 ». La meme carte n'avait donc pas la meme tete selon la
 * page : la liste par categorie emploie « .product-grid » et n'en heritait
 * rien ; la recherche et la location portaient bien la classe, mais la feuille
 * de style ne quittait pas l'accueil.
 *
 * Elles sont desormais accrochees a la carte elle-meme et chargees par le
 * gabarit public : une seule definition, la meme partout.
 */
class StyleCartesProduitsTest extends TestCase
{
    use DatabaseTransactions;

    /** Les pages du site public qui presentent des cartes produit. */
    private function pagesCatalogue(): array
    {
        $categorie = Categorie::where('statut', 1)->get()
            ->first(fn ($c) => $c->produits()->where('produit.statut', 1)->count() > 0);

        $pages = [
            'accueil'   => '/',
            'recherche' => route('client.search') . '?search=sable',
            'location'  => route('client.location'),
        ];

        if ($categorie) {
            $pages['categorie'] = route('product.categorie', $categorie->nom);
        }

        return $pages;
    }

    public function test_la_feuille_partagee_est_servie_sur_toutes_les_pages(): void
    {
        foreach ($this->pagesCatalogue() as $nom => $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Cartes produits compactes', false);
        }
    }

    public function test_l_accueil_ne_garde_pas_sa_propre_copie_des_regles(): void
    {
        // Deux definitions concurrentes, dont une seule serait mise a jour le
        // jour ou le dessin changera : c'est exactement ce qui avait produit
        // l'ecart entre les pages.
        $accueil = file_get_contents(resource_path('views/client/index.blade.php'));

        $this->assertStringNotContainsString('.product-grid-4 .product-cart-wrap', $accueil,
            "Le dessin des cartes ne doit exister qu'une fois, dans la feuille partagee.");
    }

    public function test_le_bas_de_carte_est_dispose_pareil_partout(): void
    {
        // Sur l'accueil, prix au-dessus du bouton ; ailleurs, cote a cote.
        // Meme carte, deux dessins.
        foreach ($this->pagesCatalogue() as $nom => $url) {
            $html = $this->get($url)->getContent();

            if (!str_contains($html, 'product-card-bottom')) {
                continue;
            }

            $this->assertStringContainsString('product-card-bottom d-flex flex-column', $html,
                "Page « {$nom} » : le bas de carte doit suivre la disposition de l'accueil.");
        }
    }

    public function test_les_regles_epargnent_la_carte_horizontale(): void
    {
        // « .style-2 » est la carte HORIZONTALE du gabarit — image a gauche,
        // texte a droite — que ces hauteurs fixes deformeraient.
        $feuille = file_get_contents(resource_path('views/client/_styleCartesProduits.blade.php'));

        $this->assertStringContainsString(':not(.style-2)', $feuille);

        // Chaque regle de carte doit porter l'exclusion, pas seulement la
        // premiere : une seule oubliee suffirait a ecraser la carte horizontale.
        preg_match_all('/^\s*\.product-cart-wrap[^\{,]*/m', $feuille, $selecteurs);

        foreach ($selecteurs[0] as $selecteur) {
            $this->assertStringContainsString(':not(.style-2)', $selecteur,
                "Ce selecteur toucherait aussi la carte horizontale : {$selecteur}");
        }
    }
}
