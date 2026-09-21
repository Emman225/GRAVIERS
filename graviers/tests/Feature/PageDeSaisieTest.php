<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA SAISIE A SA PROPRE PAGE : REGIONS ET VILLES.
 *
 * Les deux formulaires partageaient l'ecran de leur liste. On ne savait plus
 * si l'on consultait ou si l'on saisissait, et la liste reculait de toute la
 * hauteur du formulaire — de la carte, pour les regions — au premier clic.
 */
class PageDeSaisieTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)
            ->firstOrFail();
    }

    /* ================= LES REGIONS ================= */

    public function test_la_liste_des_regions_ne_porte_plus_ni_formulaire_ni_carte(): void
    {
        $html = $this->actingAs($this->admin())->get('/les-regions')->getContent();

        $this->assertStringNotContainsString('name="nom"', $html,
            'Le formulaire est reste sur la liste.');
        $this->assertStringNotContainsString('id="map"', $html,
            'La carte est restee sur la liste : elle la faisait reculer d autant.');
        $this->assertStringContainsString(route('show.nouvelleRegion'), $html,
            'Aucun bouton ne mene a la page de saisie : la creation devient '
            . 'inatteignable.');
    }

    public function test_la_creation_de_region_a_sa_page_avec_sa_carte(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/nouvelle-region');

        $reponse->assertOk();
        $reponse->assertSee('name="nom"', false);
        $reponse->assertSee('id="map"', false);
        $reponse->assertSee('id="long"', false);
        $reponse->assertSee('id="lat"', false);
        // La liste ne suit pas le formulaire sur sa page.
        $reponse->assertDontSee('id="listeRegions"', false);
    }

    public function test_la_modification_de_region_prerempli_le_formulaire(): void
    {
        $region = Region::firstOrFail();

        $reponse = $this->actingAs($this->admin())->get('/modifier-region-' . $region->id);

        $reponse->assertOk();
        $reponse->assertSee($region->nom, false);
        $reponse->assertSee(route('show.modifierRegionValid', $region), false);
    }

    public function test_une_region_se_cree_depuis_sa_page(): void
    {
        $avant = Region::count();

        $this->actingAs($this->admin())->get('/nouvelle-region');
        $reponse = $this->post('/les-regions', [
            'nom' => 'Region de controle',
            'adresse_geo' => 'Abidjan',
            'long' => '-4.016107',
            'lat' => '5.320357',
        ]);

        $reponse->assertRedirect(route('show.lesRegions'));
        $this->assertSame($avant + 1, Region::count());
    }

    public function test_une_region_incomplete_renvoie_sur_le_formulaire(): void
    {
        $avant = Region::count();

        $this->actingAs($this->admin())->get('/nouvelle-region');
        $reponse = $this->post('/les-regions', [
            'nom' => '', 'adresse_geo' => '', 'long' => '', 'lat' => '',
        ]);

        $reponse->assertRedirect(route('show.nouvelleRegion'));
        $this->assertSame($avant, Region::count(), 'Une region vide a ete creee.');
    }

    /* ================= LES VILLES ================= */

    public function test_la_liste_des_villes_ne_porte_plus_de_formulaire(): void
    {
        $html = $this->actingAs($this->admin())->get('/les-villes')->getContent();

        $this->assertStringNotContainsString('name="nom"', $html,
            'Le formulaire est reste sur la liste.');
        $this->assertStringContainsString(route('dest.nouvelleVille'), $html,
            'Aucun bouton ne mene a la page de saisie.');
    }

    public function test_la_creation_de_ville_a_sa_page(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/nouvelle-ville');

        $reponse->assertOk();
        $reponse->assertSee('name="region_id"', false);
        $reponse->assertDontSee('id="listeVilles"', false);
    }

    public function test_la_modification_de_ville_prerempli_le_formulaire(): void
    {
        $ville = Ville::firstOrFail();

        $reponse = $this->actingAs($this->admin())->get('/modifier-ville-' . $ville->id);

        $reponse->assertOk();
        $reponse->assertSee($ville->nom, false);
        $reponse->assertSee(route('dest.modifierVilleValid', $ville), false);
    }

    public function test_une_ville_se_cree_depuis_sa_page(): void
    {
        $avant = Ville::count();

        $this->actingAs($this->admin())->get('/nouvelle-ville');
        $reponse = $this->post('/les-villes', [
            'nom' => 'Ville de controle',
            'region_id' => Region::firstOrFail()->id,
        ]);

        $reponse->assertRedirect(route('dest.lesVilles'));
        $this->assertSame($avant + 1, Ville::count());
    }

    public function test_une_ville_sans_nom_renvoie_sur_le_formulaire(): void
    {
        $avant = Ville::count();

        $this->actingAs($this->admin())->get('/nouvelle-ville');
        $reponse = $this->post('/les-villes', ['nom' => '', 'region_id' => '']);

        $reponse->assertRedirect(route('dest.nouvelleVille'));
        $this->assertSame($avant, Ville::count(), 'Une ville sans nom a ete creee.');
    }

    /* ================= LE MENU SUIT ================= */

    public function test_le_menu_reste_ouvert_sur_les_pages_de_saisie(): void
    {
        $region = Region::firstOrFail();
        $ville = Ville::firstOrFail();

        $attendus = [
            '/nouvelle-region' => 'Les régions',
            '/modifier-region-' . $region->id => 'Les régions',
            '/nouvelle-ville' => 'Villes',
            '/modifier-ville-' . $ville->id => 'Villes',
        ];

        foreach ($attendus as $url => $sousMenu) {
            $html = $this->actingAs($this->admin())->get($url)->getContent();

            $this->assertMatchesRegularExpression(
                '#<a class="active"[^>]*>' . preg_quote($sousMenu, '#') . '</a>#',
                $html,
                'Sur ' . $url . ', le menu ne dit plus ou l on se trouve.'
            );
        }
    }

    /**
     * LES COORDONNEES NE PARTENT PLUS AU SERVEUR A CHAQUE CLIC.
     *
     * Le script postait la position sur l'adresse courante, que rien
     * n'attendait : la validation la refusait sur la liste, et sur la page de
     * saisie il n'existe meme pas de route POST — ce n'aurait plus ete qu'une
     * erreur 405 par deplacement du reperage.
     */
    public function test_la_carte_ne_poste_plus_dans_le_vide(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/formRegion.blade.php'));

        $this->assertStringNotContainsString("fetch('', {", $vue,
            'Le POST fantome de la carte est revenu.');
    }
}
