<?php

namespace Tests\Feature;

use App\Models\CoutLivraison;
use App\Models\CoutLivraisonLivreur;
use App\Models\Livreur;
use App\Models\UniteProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La facturation du livreur, sur la même grille que le client.
 *
 * Le client paie le transport sur 96 tranches — unité, quantité, distance. Le
 * livreur était payé sur un réglage unique : 2 500 F forfaitaires, pour 10 km
 * comme pour 80. Deux structures incompatibles, d'où des marges qui partaient
 * de 37 % à 79 % sans qu'aucune décision ne l'ait voulu.
 *
 * Trois exigences tiennent le mécanisme :
 *   - la grille prime, mais SEULEMENT si une tranche couvre le cas ;
 *   - sans grille, le livreur est payé exactement comme avant ;
 *   - le tarif d'une tranche couvre tout le chargement, sans multiplication
 *     par les rotations — la tranche est déjà indexée sur la quantité.
 */
class GrilleFacturationLivreurTest extends TestCase
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

        // On repart d un livreur au forfait, sans grille : c est l etat d avant.
        $livreur->update([
            'mode_tarification' => 'base',
            'cout_livraison'    => 2500,
            'tarif_km'          => 0,
            'part_grille'       => null,
        ]);

        CoutLivraisonLivreur::where('livreur_id', $livreur->id)->forceDelete();

        return $livreur->fresh();
    }

    private function uneUnite(): UniteProduit
    {
        $unite = UniteProduit::first();

        if (!$unite) {
            $this->markTestSkipped('Aucune unite de produit.');
        }

        return $unite;
    }

    private function uneTranche(Livreur $livreur, UniteProduit $unite, float $prix): CoutLivraisonLivreur
    {
        return CoutLivraisonLivreur::create([
            'livreur_id'       => $livreur->id,
            'unite_produit_id' => $unite->id,
            'unite_min'        => 0,
            'unite_max'        => 20,
            'distance_min_km'  => 0,
            'distance_max_km'  => 20,
            'prix'             => $prix,
        ]);
    }

    // ------------------------------------------------------------- LE TARIF

    public function test_sans_grille_le_livreur_garde_son_tarif(): void
    {
        // C est ce qui rend le deploiement sans risque : rien ne change tant
        // qu aucune grille n est renseignee.
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $tarif = $livreur->tarifLivraison($unite->id, 10, 15, 0, 1);

        $this->assertSame('tarif', $tarif['source']);
        $this->assertSame(2500.0, round($tarif['total'], 2));
    }

    public function test_la_grille_prime_quand_une_tranche_couvre_le_cas(): void
    {
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $this->uneTranche($livreur, $unite, 2400);

        $tarif = $livreur->tarifLivraison($unite->id, 10, 15, 0, 1);

        $this->assertSame('grille', $tarif['source']);
        $this->assertSame(2400.0, round($tarif['total'], 2));
    }

    public function test_hors_tranche_on_retombe_sur_l_ancien_tarif(): void
    {
        // Une grille incomplete ne doit pas laisser une course sans remuneration.
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $this->uneTranche($livreur, $unite, 2400);

        // 80 km : au-dela de la tranche 0-20.
        $tarif = $livreur->tarifLivraison($unite->id, 10, 80, 0, 1);

        $this->assertSame('tarif', $tarif['source']);
        $this->assertSame(2500.0, round($tarif['total'], 2));
    }

    public function test_le_tarif_de_la_tranche_ne_se_multiplie_pas_par_les_rotations(): void
    {
        // La tranche est deja indexee sur la quantite : multiplier en plus
        // compterait le volume deux fois.
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $this->uneTranche($livreur, $unite, 2400);

        $unVoyage    = $livreur->tarifLivraison($unite->id, 10, 15, 0, 1);
        $troisVoyages = $livreur->tarifLivraison($unite->id, 10, 15, 0, 3);

        $this->assertSame($unVoyage['total'], $troisVoyages['total']);
        $this->assertSame(2400.0, round($troisVoyages['total'], 2));
    }

    public function test_le_repli_garde_la_multiplication_d_origine(): void
    {
        // Sans grille, le comportement historique doit etre intact : trois
        // rotations, c est trois deplacements.
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $tarif = $livreur->tarifLivraison($unite->id, 10, 15, 0, 3);

        $this->assertSame(7500.0, round($tarif['total'], 2));
    }

    public function test_une_unite_inconnue_ne_fait_pas_tomber_le_calcul(): void
    {
        $livreur = $this->unLivreur();

        $tarif = $livreur->tarifLivraison(null, 10, 15, 0, 1);

        $this->assertSame('tarif', $tarif['source']);
        $this->assertSame(2500.0, round($tarif['total'], 2));
    }

    // -------------------------------------------------------------- LA MARGE

    public function test_la_marge_se_lit_face_au_tarif_client(): void
    {
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        CoutLivraison::create([
            'unite_produit_id' => $unite->id,
            'unite_min'        => 0,
            'unite_max'        => 20,
            'distance_min_km'  => 0,
            'distance_max_km'  => 20,
            'prix_km'          => 4000,
        ]);

        $tranche = $this->uneTranche($livreur, $unite, 2400);

        $this->assertSame(4000.0, $tranche->prixClient());
        $this->assertSame(1600.0, $tranche->marge());
        $this->assertSame(40.0, $tranche->tauxMarge());
    }

    // -------------------------------------------------------------- L'ÉCRAN

    public function test_l_ecran_repond_et_montre_la_marge(): void
    {
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $this->uneTranche($livreur, $unite, 2400);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/livreur-' . $livreur->id . '/facturation');

        $reponse->assertOk();
        $reponse->assertSee('Le client paie', false);
        $reponse->assertSee('DALAKOUN garde', false);
    }

    public function test_on_ne_paie_pas_le_livreur_plus_que_le_client(): void
    {
        // Ce ne serait plus une marge, mais une perte — et elle ne se
        // decouvrirait qu au moment de regler la dette du livreur.
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        CoutLivraison::create([
            'unite_produit_id' => $unite->id,
            'unite_min'        => 0,
            'unite_max'        => 20,
            'distance_min_km'  => 0,
            'distance_max_km'  => 20,
            'prix_km'          => 4000,
        ]);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->post('/livreur-' . $livreur->id . '/facturation', [
            'unite_produit_id' => $unite->id,
            'unite_min'        => 0,
            'unite_max'        => 20,
            'distance_min_km'  => 0,
            'distance_max_km'  => 20,
            'prix'             => 5000,
        ]);

        $reponse->assertSessionHas('erreur_grille');

        $this->assertSame(0, CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count());
    }

    public function test_deux_tranches_qui_se_recouvrent_sont_refusees(): void
    {
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $this->uneTranche($livreur, $unite, 2400);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->post('/livreur-' . $livreur->id . '/facturation', [
            'unite_produit_id' => $unite->id,
            'unite_min'        => 10,
            'unite_max'        => 30,
            'distance_min_km'  => 10,
            'distance_max_km'  => 30,
            'prix'             => 1000,
        ]);

        $reponse->assertSessionHas('erreur_grille');

        $this->assertSame(1, CoutLivraisonLivreur::where('livreur_id', $livreur->id)->count());
    }

    public function test_une_tranche_supprimee_rend_le_livreur_a_son_tarif(): void
    {
        $livreur = $this->unLivreur();
        $unite   = $this->uneUnite();

        $tranche = $this->uneTranche($livreur, $unite, 2400);

        URL::forceRootUrl('');

        $this->actingAs($this->unAdmin())
            ->delete('/livreur-' . $livreur->id . '/facturation/' . $tranche->id);

        $tarif = $livreur->fresh()->tarifLivraison($unite->id, 10, 15, 0, 1);

        $this->assertSame('tarif', $tarif['source']);
    }
}
