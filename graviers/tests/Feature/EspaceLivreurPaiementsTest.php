<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\PaiementLivreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'espace livreur montre TOUT ce qu'il a touché.
 *
 * Son écran ne listait que les demandes qu'il avait lui-même initiées. Les
 * règlements qu'un administrateur saisit sur ses courses n'y figuraient nulle
 * part : son historique ne retombait pas sur ce qu'il avait réellement reçu.
 */
class EspaceLivreurPaiementsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: Livreur, 1: Livraison} */
    private function unLivreurAvecCourse(): array
    {
        $course = Livraison::whereNotNull('livreur_id')->first();

        if (!$course) {
            $this->markTestSkipped('Aucune course rattachée à un livreur.');
        }

        $livreur = Livreur::find($course->livreur_id);

        if (!$livreur || !$livreur->user_id) {
            $this->markTestSkipped('Le livreur n\'a pas de compte.');
        }

        $course->update([
            'etat_livraison' => \Help::$LIVRAISON_LIVREE,
            'cout_livraison' => 2000,
            'forfait_base'   => 0,
            'frais_km'       => 0,
        ]);

        PaiementLivreur::where('livreur_id', $livreur->id)->forceDelete();
        DemandePaiement::where('user_id', $livreur->user_id)->delete();

        return [$livreur, $course->fresh()];
    }

    public function test_le_reglement_saisi_par_un_admin_apparait(): void
    {
        [$livreur, $course] = $this->unLivreurAvecCourse();

        PaiementLivreur::create([
            'date_paiement'    => now()->toDateString(),
            'livraison_id'     => $course->id,
            'livreur_id'       => $livreur->id,
            'montant'          => 1234,
            'mode_paiement_id' => 6,
            'reference'        => 'REGLE-ADMIN-LVR',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($livreur->user)->get('/liste-des-demandes-de-paiement');
        $reponse->assertOk();

        $html = $reponse->getContent();

        $this->assertStringContainsString('REGLE-ADMIN-LVR', $html);
        $this->assertStringContainsString("L'entreprise", $html);
        $this->assertStringContainsString('Course', $html);
    }

    public function test_un_reglement_issu_d_une_demande_n_apparait_pas_deux_fois(): void
    {
        [$livreur, $course] = $this->unLivreurAvecCourse();

        $demande = DemandePaiement::create([
            'numero' => 'DEM-LVR-UNIQUE', 'montant' => 800,
            'user_id' => $livreur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        PaiementLivreur::create([
            'date_paiement'       => now()->toDateString(),
            'livraison_id'        => $course->id,
            'demande_paiement_id' => $demande->id,
            'livreur_id'          => $livreur->id,
            'montant'             => 800,
            'mode_paiement_id'    => 6,
            'reference'           => 'DEM-LVR-UNIQUE',
            'statut'              => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($livreur->user)->get('/liste-des-demandes-de-paiement')->getContent();

        $this->assertSame(1, substr_count($html, 'DEM-LVR-UNIQUE'));
    }

    public function test_le_montant_recu_compte_les_deux_origines(): void
    {
        [$livreur, $course] = $this->unLivreurAvecCourse();

        DemandePaiement::create([
            'numero' => 'DEM-LVR-A', 'montant' => 1000,
            'user_id' => $livreur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        PaiementLivreur::create([
            'date_paiement'    => now()->toDateString(),
            'livraison_id'     => $course->id,
            'livreur_id'       => $livreur->id,
            'montant'          => 500,
            'mode_paiement_id' => 6,
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($livreur->user)->get('/liste-des-demandes-de-paiement');

        // 1 000 demandés + 500 réglés = 1 500 réellement reçus.
        $this->assertSame(1500.0, (float) $reponse->viewData('montantPaye'));
        $this->assertSame(2, (int) $reponse->viewData('totalPayees'));
    }
}
