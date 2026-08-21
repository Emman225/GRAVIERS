<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'écran « Demande de paiement » montre TOUT ce que le tiers a touché.
 *
 * Il ne listait que les demandes qu'il avait lui-même initiées. Les règlements
 * qu'un administrateur saisit sur ses pièces — un bon pour le fournisseur, une
 * course pour le livreur, une commission pour l'apporteur — n'y figuraient
 * nulle part : son historique ne retombait pas sur ce qu'il avait réellement
 * reçu.
 */
class HistoriquePaiementTiersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_fournisseur_voit_les_reglements_de_l_entreprise(): void
    {
        $fournisseur = Fournisseur::whereNotNull('user_id')->whereHas('user')
            ->whereHas('enlevements', fn ($q) => $q->whereNotNull('fournisseur_validation'))
            ->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec un bon validé.');
        }

        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->first();

        PaiementFournisseur::create([
            'date_paiement'    => now()->toDateString(),
            'enlevement_id'    => $bon->id,
            'fournisseur_id'   => $fournisseur->id,
            'montant'          => 4321,
            'mode_paiement_id' => 6,
            'reference'        => 'REGLE-FRS-1',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($fournisseur->user)->get('/demande-de-paie')->getContent();

        $this->assertStringContainsString('REGLE-FRS-1', $html);
        $this->assertStringContainsString("L'entreprise", $html);
        $this->assertStringContainsString($bon->code_enleve, $html);
    }

    public function test_le_livreur_voit_les_reglements_de_l_entreprise(): void
    {
        $course = Livraison::whereNotNull('livreur_id')->first();

        if (!$course) {
            $this->markTestSkipped('Aucune course rattachée à un livreur.');
        }

        $livreur = Livreur::find($course->livreur_id);

        if (!$livreur || !$livreur->user_id) {
            $this->markTestSkipped('Le livreur n\'a pas de compte.');
        }

        PaiementLivreur::create([
            'date_paiement'    => now()->toDateString(),
            'livraison_id'     => $course->id,
            'livreur_id'       => $livreur->id,
            'montant'          => 777,
            'mode_paiement_id' => 6,
            'reference'        => 'REGLE-LVR-1',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($livreur->user)->get('/demande-de-paie')->getContent();

        $this->assertStringContainsString('REGLE-LVR-1', $html);
        $this->assertStringContainsString("L'entreprise", $html);
    }

    public function test_l_apporteur_voit_les_reglements_de_l_entreprise(): void
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 900,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        PaiementApporteur::create([
            'date_paiement'    => now()->toDateString(),
            'commission_id'    => $commission->id,
            'apporteur_id'     => $apporteur->id,
            'montant'          => 900,
            'mode_paiement_id' => 6,
            'reference'        => 'REGLE-APP-1',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($apporteur->user)->get('/demande-de-paie')->getContent();

        $this->assertStringContainsString('REGLE-APP-1', $html);
        $this->assertStringContainsString("L'entreprise", $html);
    }

    public function test_un_reglement_issu_d_une_demande_n_apparait_pas_deux_fois(): void
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 2500,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        $demande = DemandePaiement::create([
            'numero' => 'DEM-UNIQUE', 'montant' => 2500,
            'user_id' => $apporteur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        // Le règlement que cette demande a engendré : il ne doit pas faire une
        // seconde ligne, sinon l'historique double le montant reçu.
        PaiementApporteur::create([
            'date_paiement'       => now()->toDateString(),
            'commission_id'       => $commission->id,
            'demande_paiement_id' => $demande->id,
            'apporteur_id'        => $apporteur->id,
            'montant'             => 2500,
            'mode_paiement_id'    => 6,
            'reference'           => 'DEM-UNIQUE',
            'statut'              => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($apporteur->user)->get('/demande-de-paie')->getContent();

        $this->assertSame(1, substr_count($html, 'DEM-UNIQUE'));
    }
}
