<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La création d'une catégorie de produits.
 *
 * Deux façons de faire tomber la page en erreur 500, l'une comme l'autre à la
 * portée du premier clic :
 *
 *   - SANS IMAGE : le contrôleur appelait `store()` sur un fichier absent, alors
 *     que rien dans la validation n'exigeait d'en joindre un — « Call to a
 *     member function store() on null » ;
 *   - SANS PARENT : `parent_id` n'accepte pas le vide en base, et aucun parent
 *     choisi produisait une violation de contrainte.
 *
 * L'écran de modification, lui, gardait déjà le repli sur zéro pour le parent —
 * la création était le seul chemin à ne pas l'avoir.
 */
class CreationCategorieTest extends TestCase
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

    private function creer(array $donnees): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->post('/products-category', $donnees);
    }

    public function test_une_categorie_se_cree_sans_image(): void
    {
        $nom = 'Categorie recette ' . uniqid();

        $reponse = $this->creer([
            'nom'         => $nom,
            'description' => 'Creee pour la recette.',
        ]);

        // Elle tombait en 500 : page blanche, aucune explication.
        $reponse->assertRedirect();

        $categorie = Categorie::where('nom', $nom)->first();

        $this->assertNotNull($categorie, 'La catégorie doit être créée même sans image.');
        $this->assertNull($categorie->image);
        // Zéro = racine, la convention de l'écran de modification.
        $this->assertSame(0, (int) $categorie->parent_id);
    }

    public function test_une_categorie_se_cree_avec_son_image(): void
    {
        Storage::fake('public');

        $nom = 'Categorie image ' . uniqid();

        $reponse = $this->creer([
            'nom'         => $nom,
            'description' => 'Creee pour la recette.',
            'image'       => UploadedFile::fake()->image('categorie.png', 40, 40),
        ]);

        $reponse->assertRedirect();

        $categorie = Categorie::where('nom', $nom)->first();

        $this->assertNotNull($categorie);
        $this->assertNotNull($categorie->image);
        Storage::disk('public')->assertExists($categorie->image);

        // L'icône suit l'image, comme avant.
        $this->assertSame($categorie->image, $categorie->icon);
    }

    public function test_un_parent_choisi_est_conserve(): void
    {
        $parent = Categorie::where('statut', 1)->first();

        if (!$parent) {
            $this->markTestSkipped('Aucune catégorie pour servir de parent.');
        }

        $nom = 'Categorie fille ' . uniqid();

        $this->creer([
            'nom'         => $nom,
            'description' => 'Creee pour la recette.',
            'parent'      => $parent->id,
        ])->assertRedirect();

        $this->assertSame((int) $parent->id, (int) Categorie::where('nom', $nom)->first()->parent_id);
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        Storage::fake('public');

        $nom = 'Categorie fichier ' . uniqid();

        // Rien ne contrôlait le fichier : n'importe quoi partait sur le disque.
        $reponse = $this->creer([
            'nom'         => $nom,
            'description' => 'Creee pour la recette.',
            'image'       => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ]);

        $reponse->assertSessionHasErrors('image');
        $this->assertNull(Categorie::where('nom', $nom)->first());
    }

    public function test_le_nom_reste_obligatoire(): void
    {
        $this->creer(['description' => 'Sans nom.'])->assertSessionHasErrors('nom');
    }
}
