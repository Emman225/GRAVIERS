<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Les soldes du livreur et de l'apporteur, reconstitués depuis les pièces.
 *
 * Comme celui du fournisseur, `solde` est un cumul tenu à la main. Il dérive
 * dès qu'un événement lui échappe : une course supprimée, un vidage de base,
 * ou une demande de paiement dont l'enregistrement a échoué après que le solde
 * eut été débité.
 */
class SoldeLivreurApporteurTest extends TestCase
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------- LIVREUR

    private function unLivreurAvecCourse(): Livreur
    {
        $course = Livraison::whereNotNull('livreur_id')->first();

        if (!$course) {
            $this->markTestSkipped('Aucune course rattachée à un livreur.');
        }

        $livreur = Livreur::find($course->livreur_id);

        if (!$livreur) {
            $this->markTestSkipped('Le livreur de la course est introuvable.');
        }

        // Une seule course livrée, à 1 500, et aucune demande en cours.
        Livraison::where('livreur_id', $livreur->id)
            ->where('id', '!=', $course->id)
            ->update(['livreur_id' => null]);

        $course->update([
            'etat_livraison' => \Help::$LIVRAISON_LIVREE,
            'cout_livraison' => 1500,
            'forfait_base'   => 0,
            'frais_km'       => 0,
        ]);

        DemandePaiement::where('user_id', $livreur->user_id)->delete();

        return $livreur;
    }

    public function test_le_solde_du_livreur_suit_ses_courses_livrees(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        $this->assertSame(1500.0, $livreur->soldeCalcule());
    }

    public function test_une_course_non_livree_ne_rapporte_rien(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        Livraison::where('livreur_id', $livreur->id)
            ->update(['etat_livraison' => \Help::$LIVRAISON_EN_COURS]);

        $this->assertSame(0.0, $livreur->soldeCalcule());
    }

    public function test_une_demande_en_cours_sort_du_disponible(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        DemandePaiement::create([
            'montant'          => 500,
            'user_id'          => $livreur->user_id,
            'mode_paiement_id' => 6,
            'paye'             => 0,
        ]);

        $this->assertSame(1000.0, $livreur->soldeCalcule());
    }

    public function test_le_rattrapage_livreur_releve_un_solde_ampute(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        // Solde plus bas que les courses : de l'argent a disparu du tableau
        // de bord. Celui-la est rendu sans qu'on ait a le demander.
        $livreur->update(['solde' => 0]);

        Artisan::call('livreur:rattraper-solde', ['--apply' => true]);

        $this->assertSame(1500.0, round((float) $livreur->fresh()->solde));
    }

    public function test_le_rattrapage_livreur_ne_baisse_pas_sans_qu_on_le_demande(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        // Solde plus haut que les courses : peut venir de courses supprimees,
        // dont la preuve du gain a disparu mais pas le gain lui-meme.
        $livreur->update(['solde' => 26500]);

        Artisan::call('livreur:rattraper-solde', ['--apply' => true]);

        $this->assertSame(26500.0, round((float) $livreur->fresh()->solde));
    }

    public function test_le_rattrapage_livreur_baisse_quand_on_le_demande(): void
    {
        $livreur = $this->unLivreurAvecCourse();
        $livreur->update(['solde' => 26500]);

        Artisan::call('livreur:rattraper-solde', ['--apply' => true, '--baisser' => true]);

        $this->assertSame(1500.0, round((float) $livreur->fresh()->solde));
    }

    // -------------------------------------------------------------- APPORTEUR

    private function unApporteur(): Apporteur
    {
        $apporteur = Apporteur::whereNotNull('user_id')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        CommissionApporteur::where('apporteur_id', $apporteur->id)->forceDelete();
        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        return $apporteur;
    }

    public function test_le_solde_de_l_apporteur_suit_ses_commissions(): void
    {
        $apporteur = $this->unApporteur();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'montant'      => 3000,
            'type_affaire' => 'VENTE',
            'statut'       => 1,
        ]);

        $this->assertSame(3000.0, $apporteur->soldeCalcule());
    }

    public function test_une_commission_de_location_est_desormais_enregistree(): void
    {
        $apporteur = $this->unApporteur();

        // Ce que le circuit location ne savait pas faire : laisser une trace.
        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'location_id'  => 1,
            'montant'      => 2500,
            'type_affaire' => 'LOCATION',
            'statut'       => 1,
        ]);

        // `commande_id` doit pouvoir rester vide : une location n'est pas une
        // commande. C'est ce que la migration rend possible.
        $this->assertNull($commission->fresh()->commande_id);
        $this->assertSame(1, (int) $commission->fresh()->location_id);
        $this->assertSame(2500.0, $apporteur->soldeCalcule());
    }

    public function test_le_rattrapage_apporteur_ne_baisse_pas_sans_qu_on_le_demande(): void
    {
        $apporteur = $this->unApporteur();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'montant'      => 1000,
            'type_affaire' => 'VENTE',
            'statut'       => 1,
        ]);

        // Solde plus haut que les commissions : peut venir d'une location
        // d'avant le correctif, qui n'a laissé aucune trace. On n'y touche pas.
        $apporteur->update(['solde' => 8000]);

        Artisan::call('apporteur:rattraper-solde', ['--apply' => true]);

        $this->assertSame(8000.0, round((float) $apporteur->fresh()->solde));
    }

    public function test_le_rattrapage_apporteur_releve_un_solde_ampute(): void
    {
        $apporteur = $this->unApporteur();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'montant'      => 1000,
            'type_affaire' => 'VENTE',
            'statut'       => 1,
        ]);

        // Solde plus bas que les commissions : de l'argent a disparu du
        // tableau de bord. Celui-là est rendu sans qu'on ait à le demander.
        $apporteur->update(['solde' => 0]);

        Artisan::call('apporteur:rattraper-solde', ['--apply' => true]);

        $this->assertSame(1000.0, round((float) $apporteur->fresh()->solde));
    }

    public function test_le_rattrapage_apporteur_baisse_quand_on_le_demande(): void
    {
        $apporteur = $this->unApporteur();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id,
            'montant'      => 1000,
            'type_affaire' => 'VENTE',
            'statut'       => 1,
        ]);

        $apporteur->update(['solde' => 8000]);

        Artisan::call('apporteur:rattraper-solde', ['--apply' => true, '--baisser' => true]);

        $this->assertSame(1000.0, round((float) $apporteur->fresh()->solde));
    }
}
