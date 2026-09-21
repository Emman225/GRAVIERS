<?php

namespace Tests\Feature;

use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UN PRODUIT NE DISPARAÎT PAS PARCE QU'IL LUI MANQUE UNE IMAGE PAR DÉFAUT.
 *
 * Le catalogue de l'application joignait `image_produit` en jointure INTERNE,
 * en exigeant une image marquée « par défaut » ET active. Un produit dont
 * l'image existait sans porter ce marqueur disparaissait donc entièrement de
 * l'application — du catalogue, de la recherche ET de sa catégorie — alors que
 * le site continuait de l'afficher, lui qui ne joint jamais cette table.
 *
 * C'est ainsi que « Perceuse et visseuse » figurait dans la catégorie
 * « Equipements » sur le site, et que les deux onglets de cette même catégorie
 * étaient vides sur le mobile.
 *
 * Le marqueur « par défaut » sert à CHOISIR quelle image montrer. Il ne doit
 * jamais décider si le produit existe.
 */
class ProduitSansImageParDefautTest extends TestCase
{
    use DatabaseTransactions;

    /** Un produit actif ayant au moins une image active. */
    private function unProduitAvecImage(): object
    {
        $p = DB::table('produit')
            ->where('produit.statut', \Help::$STATUT_ACTIF)
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('image_produit')
                    ->whereColumn('image_produit.produit_id', 'produit.id')
                    ->where('image_produit.statut', \Help::$STATUT_ACTIF);
            })
            ->first();

        if (!$p) {
            $this->markTestSkipped('Aucun produit actif avec image en base.');
        }

        return $p;
    }

    private function estDansLeCatalogue(int $id): bool
    {
        foreach (Produit::liste(null, 3000) as $p) {
            if ((int) $p->id === $id) {
                return true;
            }
        }

        return false;
    }

    public function test_un_produit_sans_image_par_defaut_reste_au_catalogue(): void
    {
        $produit = $this->unProduitAvecImage();

        // TÉMOIN : il est bien là AVANT qu'on retire le marqueur. Sans ce
        // contrôle, un produit absent pour une tout autre raison ferait passer
        // l'essai pour une démonstration.
        $this->assertTrue($this->estDansLeCatalogue((int) $produit->id),
            'Le produit témoin doit être au catalogue au départ.');

        DB::table('image_produit')
            ->where('produit_id', $produit->id)
            ->update(['defaut' => 0]);

        $this->assertTrue($this->estDansLeCatalogue((int) $produit->id),
            'Le produit « ' . $produit->nom . ' » a disparu du catalogue parce '
            . 'qu\'aucune de ses images n\'est marquée « par défaut ». Ce '
            . 'marqueur choisit l\'image à montrer ; il ne décide pas si le '
            . 'produit existe.');
    }

    public function test_il_garde_une_image_a_afficher(): void
    {
        $produit = $this->unProduitAvecImage();

        DB::table('image_produit')
            ->where('produit_id', $produit->id)
            ->update(['defaut' => 0]);

        $trouve = null;
        foreach (Produit::liste(null, 3000) as $p) {
            if ((int) $p->id === (int) $produit->id) {
                $trouve = $p;
                break;
            }
        }

        $this->assertNotNull($trouve);
        $this->assertNotEmpty($trouve->image,
            'À défaut d\'image marquée, on montre la première image active — '
            . 'et non rien du tout.');
    }

    public function test_un_produit_sans_aucune_image_reste_visible(): void
    {
        $produit = $this->unProduitAvecImage();

        DB::table('image_produit')
            ->where('produit_id', $produit->id)
            ->update(['statut' => 2]);

        $this->assertTrue($this->estDansLeCatalogue((int) $produit->id),
            'Un produit sans aucune image reste un produit : il doit rester '
            . 'achetable, avec le visuel de repli de l\'application.');
    }

    /**
     * PLUSIEURS IMAGES NE FONT PAS PLUSIEURS PRODUITS.
     *
     * C'est ce qui interdisait de remplacer simplement la jointure interne par
     * une jointure externe : le produit serait revenu autant de fois qu'il a
     * d'images, et `distinct()` ne les aurait pas fondues puisque l'image
     * diffère d'une ligne à l'autre.
     */
    public function test_un_produit_a_plusieurs_images_ne_revient_qu_une_fois(): void
    {
        $produit = $this->unProduitAvecImage();

        $modele = DB::table('image_produit')
            ->where('produit_id', $produit->id)->first();

        foreach ([1, 2] as $i) {
            DB::table('image_produit')->insert([
                'image'      => 'essai-' . $i . '-' . $modele->image,
                'produit_id' => $produit->id,
                'defaut'     => 0,
                'statut'     => \Help::$STATUT_ACTIF,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $occurrences = 0;
        foreach (Produit::liste(null, 3000) as $p) {
            if ((int) $p->id === (int) $produit->id) {
                $occurrences++;
            }
        }

        $this->assertSame(1, $occurrences,
            'Le produit revient ' . $occurrences . ' fois : le catalogue le '
            . 'duplique autant qu\'il a d\'images.');
    }

    /** La liste par catégorie applique la même règle que le catalogue. */
    public function test_la_liste_par_categorie_applique_la_meme_regle(): void
    {
        $lien = DB::table('categorie_produit')->first();

        if (!$lien) {
            $this->markTestSkipped('Aucun produit rangé dans une catégorie.');
        }

        $avant = collect(Produit::listeSurCategorie(
            null, 3000, [$lien->categorie_id]))->pluck('id')->count();

        DB::table('image_produit')
            ->where('produit_id', $lien->produit_id)
            ->update(['defaut' => 0]);

        $apres = collect(Produit::listeSurCategorie(
            null, 3000, [$lien->categorie_id]))->pluck('id')->count();

        $this->assertSame($avant, $apres,
            'Retirer le marqueur « par défaut » a vidé la catégorie de '
            . ($avant - $apres) . ' produit(s).');
    }
}
