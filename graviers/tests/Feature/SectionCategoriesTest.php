<?php

namespace Tests\Feature;

use App\Models\Categorie;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA SECTION « ACHAT PAR CATEGORIE » DE L'ACCUEIL.
 *
 * Elle reposait sur un carrousel regle sur huit colonnes pour cinq categories :
 * il ne faisait defiler rien du tout, et ses fleches promettaient un contenu
 * cache qui n'existait pas. Une grille les montre toutes.
 *
 * Deux defauts de fond y ont ete corriges :
 *
 *   · L'ADRESSE DE L'ICONE. Le code prefixait aveuglement par « storage/ ».
 *     Or l'icone peut aussi etre une adresse complete saisie au back-office —
 *     il y en a en base — et le prefixe la cassait. C'etait de surcroit un
 *     chemin RELATIF : correct depuis l'accueil, faux partout ailleurs.
 *
 *   · LE BLOC NE DISAIT RIEN. Un nom et une image, sans indiquer ce que la
 *     categorie contient. Le nombre de produits est deja charge par le
 *     controleur : il ne coute aucune requete et repond a la seule question
 *     que se pose le visiteur.
 */
class SectionCategoriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_section_affiche_une_grille_et_non_un_carrousel(): void
    {
        $reponse = $this->get('/');

        $reponse->assertOk();
        $reponse->assertSee('cat-grille', false);

        // Les fleches du carrousel promettaient un contenu cache inexistant.
        $reponse->assertDontSee('carausel-8-columns-arrows', false);
    }

    public function test_chaque_bloc_annonce_le_nombre_de_produits(): void
    {
        $reponse = $this->get('/');

        $reponse->assertSee('cat-compte', false);

        // Au moins une categorie du catalogue reel doit annoncer un decompte.
        $reponse->assertSeeInOrder(['cat-nom', 'cat-compte'], false);
    }

    public function test_une_icone_deposee_sur_le_disque_passe_par_storage(): void
    {
        $categorie = Categorie::where('statut', 1)
            ->whereNotNull('icon')
            ->where('icon', 'not like', 'http%')
            ->first();

        if (!$categorie) {
            $this->markTestSkipped('Aucune categorie avec une icone locale.');
        }

        $this->get('/')->assertSee(asset('storage/' . $categorie->icon), false);
    }

    public function test_une_icone_donnee_en_adresse_complete_n_est_pas_prefixee(): void
    {
        // Le back-office accepte une adresse complete, et il y en a en base.
        // Prefixer par « storage/ » produisait une adresse impossible.
        $categorie = Categorie::where('statut', 1)->first();

        if (!$categorie) {
            $this->markTestSkipped('Aucune categorie active.');
        }

        $ancienne = $categorie->icon;
        $categorie->update(['icon' => 'https://exemple.test/image.png']);

        $reponse = $this->get('/');

        $reponse->assertSee('https://exemple.test/image.png', false);
        $reponse->assertDontSee('storage/https://exemple.test/image.png', false);

        $categorie->update(['icon' => $ancienne]);
    }

    public function test_une_categorie_sans_icone_garde_la_forme_du_bloc(): void
    {
        // Une image cassee deforme le bloc et fait desordre : on affiche
        // l'initiale, le cadre reste identique aux autres.
        $categorie = Categorie::where('statut', 1)->first();

        if (!$categorie) {
            $this->markTestSkipped('Aucune categorie active.');
        }

        $ancienne = $categorie->icon;
        $categorie->update(['icon' => null]);

        $this->get('/')->assertSee('cat-initiale', false);

        $categorie->update(['icon' => $ancienne]);
    }
}
