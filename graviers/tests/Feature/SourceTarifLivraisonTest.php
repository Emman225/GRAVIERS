<?php

namespace Tests\Feature;

use App\Models\CoutLivraisonLivreur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\UniteProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * D'où vient la rémunération d'une course : la grille, ou le tarif du livreur.
 *
 * Deux tarifications coexistent — la grille de facturation du livreur, et son
 * mode de tarification en repli. Le montant seul ne permettait pas de les
 * distinguer : on ne pouvait que déduire « la grille a répondu » du fait que la
 * colonne « Frais km » soit vide. Une déduction, pas une preuve — et fausse dès
 * qu'un livreur est au forfait, qui produit lui aussi un kilométrage nul.
 *
 * La source est donc enregistrée AU MOMENT DU CALCUL. La recalculer après coup
 * donnerait la réponse d'aujourd'hui, pas celle du jour de la course.
 */
class SourceTarifLivraisonTest extends TestCase
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

    private function unLivreur(): Livreur
    {
        $livreur = Livreur::first();

        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur en base.');
        }

        $livreur->update([
            'mode_tarification' => 'mixte',
            'tarif_forfait_base' => 2000,
            'tarif_km'           => 50,
        ]);

        CoutLivraisonLivreur::where('livreur_id', $livreur->id)->forceDelete();

        return $livreur->fresh();
    }

    // ------------------------------------------------------- LE CALCUL

    public function test_la_grille_est_annoncee_comme_source(): void
    {
        $livreur = $this->unLivreur();
        $unite   = UniteProduit::first();

        CoutLivraisonLivreur::create([
            'livreur_id'       => $livreur->id,
            'unite_produit_id' => $unite->id,
            'unite_min'        => 0,
            'unite_max'        => 20,
            'distance_min_km'  => 0,
            'distance_max_km'  => 20,
            'prix'             => 2400,
        ]);

        $tarif = $livreur->tarifLivraison($unite->id, 10, 15, 0, 1);

        $this->assertSame('grille', $tarif['source']);
    }

    public function test_le_repli_est_annonce_comme_tel(): void
    {
        $livreur = $this->unLivreur();
        $unite   = UniteProduit::first();

        $tarif = $livreur->tarifLivraison($unite->id, 10, 15, 0, 1);

        $this->assertSame('tarif', $tarif['source']);
    }

    // -------------------------------------------------- CE QUI EST GARDE

    public function test_la_source_se_lit_en_clair(): void
    {
        $livraison = new Livraison(['source_tarif' => 'grille']);
        $this->assertSame('Grille', $livraison->libelleSourceTarif());
        $this->assertTrue($livraison->tarifVientDeLaGrille());

        $livraison = new Livraison(['source_tarif' => 'tarif']);
        $this->assertSame('Tarif livreur', $livraison->libelleSourceTarif());
        $this->assertFalse($livraison->tarifVientDeLaGrille());
    }

    public function test_une_course_ancienne_ne_ment_pas(): void
    {
        // Les livraisons anterieures n ont pas l information : on l admet au
        // lieu de la deviner.
        $livraison = new Livraison([]);

        $this->assertSame('Non renseigné', $livraison->libelleSourceTarif());
        $this->assertFalse($livraison->tarifVientDeLaGrille());
    }

    public function test_la_colonne_est_persistee(): void
    {
        $modele = Livraison::whereNotNull('livreur_id')->first();

        if (!$modele) {
            $this->markTestSkipped('Aucune livraison exploitable.');
        }

        $copie = $modele->replicate();
        $copie->numero       = 'SRC-' . uniqid();
        $copie->source_tarif = 'grille';
        $copie->save();

        $this->assertSame('grille', $copie->fresh()->source_tarif,
            'La source doit survivre a l enregistrement.');
    }

    // ------------------------------------------------------------ L'ECRAN

    public function test_l_ecran_des_dettes_montre_la_source(): void
    {
        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/livreurs/livraisons');

        $reponse->assertOk();
        $reponse->assertSee('Tarif appliqué', false);
    }

    public function test_chaque_ligne_porte_sa_source(): void
    {
        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/livreurs/livraisons');

        $lignes = $reponse->viewData('lignes');

        if ($lignes->isEmpty()) {
            $this->markTestSkipped('Aucune ligne a verifier.');
        }

        foreach ($lignes as $ligne) {
            $this->assertContains($ligne->source_tarif,
                ['Grille', 'Tarif livreur', 'Non renseigné'],
                'Chaque ligne doit annoncer une source connue.');
        }
    }
}
