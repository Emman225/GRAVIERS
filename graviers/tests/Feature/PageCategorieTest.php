<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA SAISIE A SA PROPRE PAGE, ET LES ACTIONS SONT DES ICONES.
 *
 * Le formulaire de categorie partageait l'ecran de la liste : on ne savait
 * plus si l'on consultait ou si l'on saisissait, et la liste reculait de tout
 * un formulaire au premier clic. Les deux commandes de chaque ligne, elles,
 * dormaient derriere un menu deroulant.
 */
class PageCategorieTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)
            ->firstOrFail();
    }

    public function test_la_liste_ne_porte_plus_de_formulaire(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/products-category');

        $reponse->assertOk();
        $reponse->assertDontSee('enctype="multipart/form-data"', false);
        $reponse->assertSee(route('product.nouvelleCategorie'), false);
    }

    public function test_la_creation_a_sa_page(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/nouvelle-categorie');

        $reponse->assertOk();
        $reponse->assertSee('enctype="multipart/form-data"', false);
        // La liste ne suit pas le formulaire sur sa page.
        $reponse->assertDontSee('id="listeCategories"', false);
    }

    public function test_la_modification_prerempli_le_formulaire(): void
    {
        $categorie = Categorie::firstOrFail();

        $reponse = $this->actingAs($this->admin())->get('/edit-category-' . $categorie->id);

        $reponse->assertOk();
        $reponse->assertSee($categorie->nom, false);
        $reponse->assertSee(route('product.editCategoryTraitement', $categorie), false);
    }

    public function test_les_actions_sont_des_icones_avec_leur_titre(): void
    {
        $categorie = Categorie::firstOrFail();

        $html = $this->actingAs($this->admin())->get('/products-category')->getContent();

        // La cellule d'action de la ligne : elle porte les deux commandes.
        $this->assertMatchesRegularExpression(
            '#href="[^"]*edit-category-' . $categorie->id . '"[^>]*title="Modifier les informations"#',
            $html,
            'Le bouton de modification a perdu son titre au survol.'
        );
        $this->assertMatchesRegularExpression(
            '#href="[^"]*delete-category-' . $categorie->id . '"[^>]*title="Supprimer"#',
            $html,
            'Le bouton de suppression a perdu son titre au survol.'
        );
        // Uniquement dans le tableau : l'en-tete de page a ses propres menus.
        preg_match('#<tbody>(.*?)</tbody>#s', $html, $m);
        $this->assertNotEmpty($m, 'Le tableau des categories est introuvable.');
        $this->assertStringNotContainsString('data-bs-toggle="dropdown"', $m[1],
            'Les commandes sont retournees dans un menu deroulant.');
    }

    public function test_le_menu_produits_reste_ouvert_sur_les_pages_de_saisie(): void
    {
        $categorie = Categorie::firstOrFail();

        foreach (['/nouvelle-categorie', '/edit-category-' . $categorie->id] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->getContent();

            $this->assertMatchesRegularExpression(
                '#<a class="active"[^>]*>Categories</a>#',
                $html,
                'Sur ' . $url . ', le menu ne dit plus ou l on se trouve.'
            );
        }
    }

    public function test_une_categorie_se_cree_depuis_sa_page(): void
    {
        $avant = Categorie::count();

        $this->actingAs($this->admin())->get('/nouvelle-categorie');
        $reponse = $this->post('/products-category', [
            'nom' => 'Categorie de controle',
            'parent' => '',
            'description' => 'controle',
        ]);

        $reponse->assertRedirect(route('product.category'));
        $this->assertSame($avant + 1, Categorie::count());
    }

    public function test_un_nom_vide_renvoie_sur_le_formulaire_et_non_sur_la_liste(): void
    {
        $avant = Categorie::count();

        $this->actingAs($this->admin())->get('/nouvelle-categorie');
        $reponse = $this->post('/products-category', [
            'nom' => '',
            'parent' => '',
            'description' => '',
        ]);

        $reponse->assertRedirect(route('product.nouvelleCategorie'));
        $this->assertSame($avant, Categorie::count(), 'Une categorie sans nom a ete creee.');
    }
}
