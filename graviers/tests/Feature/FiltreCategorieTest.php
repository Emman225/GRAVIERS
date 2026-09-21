<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE TRI ET LE FILTRE DE LA PAGE D'UNE CATEGORIE.
 *
 * « Trier par » ne triait rien : les cinq entrees du menu pointaient sur « # »,
 * un reste du gabarit. Le filtre par prix, lui, etait commente dans la barre
 * laterale. Et la pagination affichait « 1 2 3 ... 6 » en dur, quel que soit le
 * nombre d'articles.
 *
 * Le prix retenu est celui que le visiteur VOIT — prix personnalise s'il en a
 * un, sinon prix catalogue calcule. Trier sur la colonne stockee classerait
 * selon un montant que personne n'affiche : c'est exactement ce qui avait casse
 * le filtre par montant de l'application mobile.
 */
class FiltreCategorieTest extends TestCase
{
    use DatabaseTransactions;

    /** Une categorie qui porte assez de produits pour que trier ait un sens. */
    private function uneCategorieFournie(): Categorie
    {
        $categorie = Categorie::where('statut', 1)->get()
            ->first(fn ($c) => $c->produits()
                ->where('produit.statut', 1)
                ->where('produit.type_affaire', 'VENTE')
                ->avecFournisseur()
                ->count() >= 2);

        if (!$categorie) {
            $this->markTestSkipped('Aucune categorie avec au moins deux produits vendables.');
        }

        return $categorie;
    }

    /** Les prix affiches, dans l'ordre de la page. */
    private function prixAffiches(string $html): array
    {
        preg_match_all('/<span> ([\d\. ]+) fcfa<\/span>/u', $html, $trouves);

        return array_map(
            fn ($p) => (float) str_replace([' ', '.', "\u{a0}"], '', $p),
            $trouves[1]
        );
    }

    public function test_le_menu_de_tri_propose_de_vrais_liens(): void
    {
        $categorie = $this->uneCategorieFournie();

        $reponse = $this->get(route('product.categorie', $categorie->nom));

        $reponse->assertOk();
        $reponse->assertSee('tri=prix_asc', false);
        $reponse->assertSee('tri=prix_desc', false);

        // Les entrees mortes du gabarit ne doivent plus etre proposees.
        $reponse->assertDontSee('<li><a class="active" href="#">Tendance</a></li>', false);
    }

    public function test_le_tri_par_prix_croissant_classe_vraiment(): void
    {
        $categorie = $this->uneCategorieFournie();

        $html = $this->get(route('product.categorie', $categorie->nom) . '?tri=prix_asc')
            ->assertOk()->getContent();

        $prix = $this->prixAffiches($html);

        if (count($prix) < 2) {
            $this->markTestSkipped('Prix illisibles dans la page.');
        }

        $trie = $prix;
        sort($trie);

        $this->assertEquals($trie, $prix, 'Les produits doivent sortir du moins cher au plus cher.');
    }

    public function test_le_tri_par_prix_decroissant_inverse_l_ordre(): void
    {
        $categorie = $this->uneCategorieFournie();

        $html = $this->get(route('product.categorie', $categorie->nom) . '?tri=prix_desc')
            ->assertOk()->getContent();

        $prix = $this->prixAffiches($html);

        if (count($prix) < 2) {
            $this->markTestSkipped('Prix illisibles dans la page.');
        }

        $trie = $prix;
        rsort($trie);

        $this->assertEquals($trie, $prix);
    }

    public function test_le_tri_en_cours_est_annonce(): void
    {
        // L'entete affichait « Tendance » en dur : le visiteur ne pouvait pas
        // savoir sur quoi la liste etait classee.
        $categorie = $this->uneCategorieFournie();

        $this->get(route('product.categorie', $categorie->nom) . '?tri=prix_desc')
            ->assertSee('Prix décroissant');
    }

    public function test_un_tri_invente_retombe_sur_le_defaut(): void
    {
        // Le critere vient de l'adresse : rien ne doit permettre d'y ecrire
        // n'importe quoi.
        $categorie = $this->uneCategorieFournie();

        $this->get(route('product.categorie', $categorie->nom) . '?tri=<script>alert(1)</script>')
            ->assertOk()
            ->assertSee('Tendance');
    }

    public function test_le_filtre_par_prix_restreint_la_liste(): void
    {
        $categorie = $this->uneCategorieFournie();

        $tout = $this->prixAffiches(
            $this->get(route('product.categorie', $categorie->nom) . '?tri=prix_asc')->getContent()
        );

        if (count($tout) < 2) {
            $this->markTestSkipped('Prix illisibles dans la page.');
        }

        // Une borne prise DANS les prix reels : la liste doit se reduire sans
        // devenir vide — une fixture qui rend zero ne prouverait rien.
        $plafond = $tout[0];

        $filtre = $this->prixAffiches(
            $this->get(route('product.categorie', $categorie->nom) . '?prix_max=' . $plafond)->getContent()
        );

        $this->assertNotEmpty($filtre, 'Le produit le moins cher doit rester.');
        $this->assertLessThan(count($tout), count($filtre), 'La liste doit vraiment se reduire.');

        foreach ($filtre as $prix) {
            $this->assertLessThanOrEqual($plafond, $prix);
        }
    }

    public function test_des_bornes_inversees_sont_remises_dans_l_ordre(): void
    {
        // Saisir 5000 puis 1000 est une maladresse, pas une demande de liste
        // vide : on remet les bornes dans l'ordre.
        $categorie = $this->uneCategorieFournie();

        $normal  = $this->get(route('product.categorie', $categorie->nom) . '?prix_min=1&prix_max=99999999')->getContent();
        $inverse = $this->get(route('product.categorie', $categorie->nom) . '?prix_min=99999999&prix_max=1')->getContent();

        $this->assertEquals(
            count($this->prixAffiches($normal)),
            count($this->prixAffiches($inverse))
        );
    }

    public function test_le_filtre_et_le_tri_voyagent_ensemble(): void
    {
        // Filtrer ne doit pas reclasser la liste dans le dos du visiteur.
        $categorie = $this->uneCategorieFournie();

        $this->get(route('product.categorie', $categorie->nom) . '?tri=prix_desc&prix_min=1')
            ->assertOk()
            ->assertSee('name="tri" value="prix_desc"', false);
    }

    public function test_la_pagination_n_est_plus_ecrite_en_dur(): void
    {
        $categorie = $this->uneCategorieFournie();

        $reponse = $this->get(route('product.categorie', $categorie->nom));

        // Six pages annoncees pour quelques produits, et aucun lien actif.
        $reponse->assertDontSee('<li class="page-item active"><a class="page-link" href="#">2</a></li>', false);
    }

    public function test_le_formulaire_de_prix_n_apparait_que_sur_cette_page(): void
    {
        // La barre laterale est partagee avec l'accueil et la fiche produit, ou
        // le filtre ne filtrerait rien.
        $categorie = $this->uneCategorieFournie();

        $this->get(route('product.categorie', $categorie->nom))->assertSee('filtre-prix', false);
        $this->get('/')->assertDontSee('filtre-prix', false);
    }

    public function test_la_page_n_affiche_aucun_reste_de_commentaire(): void
    {
        // Le bloc de pagination etait DEJA en commentaire, et j'y ai ecrit une
        // explication : un commentaire Blade ne s'imbrique pas, le premier se
        // fermait donc trop tot et « --}} » s'affichait au milieu de l'ecran,
        // juste apres le menu « Trier par ».
        $categorie = $this->uneCategorieFournie();

        $html = $this->get(route('product.categorie', $categorie->nom))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('--}}', $html,
            'Un marqueur de commentaire ne doit jamais atteindre la page.');

        $this->assertStringNotContainsString('{{--', $html);
    }

    public function test_la_pagination_est_reellement_rendue(): void
    {
        // Elle etait prise dans le bloc commente : elle ne s'affichait pas du
        // tout. J'avais conclu trop vite qu'une seule page expliquait son
        // absence.
        $categorie = $this->uneCategorieFournie();

        $html = $this->get(route('product.categorie', $categorie->nom))
            ->assertOk()->getContent();

        $this->assertStringContainsString('pagination-area', $html,
            "Le conteneur de pagination doit etre rendu, meme quand il n'y a qu'une page.");
    }
}
