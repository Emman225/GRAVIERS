<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\PaiementApporteur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'espace apporteur montre TOUT ce qu'il a touché.
 *
 * Son écran « Mes paiements » ne listait que les demandes qu'il avait
 * lui-même initiées. Les règlements qu'un administrateur saisit sur ses
 * commissions n'y figuraient nulle part : son historique ne retombait pas sur
 * ce qu'il avait réellement reçu.
 */
class EspaceApporteurPaiementsTest extends TestCase
{
    use DatabaseTransactions;

    private function unApporteur(): Apporteur
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        CommissionApporteur::where('apporteur_id', $apporteur->id)->forceDelete();
        PaiementApporteur::where('apporteur_id', $apporteur->id)->forceDelete();
        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        return $apporteur;
    }

    public function test_le_reglement_saisi_par_un_admin_apparait(): void
    {
        $apporteur = $this->unApporteur();

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
            'reference'        => 'REGLE-ADMIN-APP',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($apporteur->user)->get('/apporteur/paiement');
        $reponse->assertOk();

        $html = $reponse->getContent();

        $this->assertStringContainsString('REGLE-ADMIN-APP', $html);
        $this->assertStringContainsString("L'entreprise", $html);
        $this->assertStringContainsString('Commission', $html);
    }

    public function test_un_reglement_issu_d_une_demande_n_apparait_pas_deux_fois(): void
    {
        $apporteur = $this->unApporteur();

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 2500,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        $demande = DemandePaiement::create([
            'numero' => 'DEM-APP-UNIQUE', 'montant' => 2500,
            'user_id' => $apporteur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        PaiementApporteur::create([
            'date_paiement'       => now()->toDateString(),
            'commission_id'       => $commission->id,
            'demande_paiement_id' => $demande->id,
            'apporteur_id'        => $apporteur->id,
            'montant'             => 2500,
            'mode_paiement_id'    => 6,
            'reference'           => 'DEM-APP-UNIQUE',
            'statut'              => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($apporteur->user)->get('/apporteur/paiement')->getContent();

        $this->assertSame(1, substr_count($html, 'DEM-APP-UNIQUE'));
    }

    public function test_le_montant_recu_compte_les_deux_origines(): void
    {
        $apporteur = $this->unApporteur();

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 3000,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        DemandePaiement::create([
            'numero' => 'DEM-A', 'montant' => 1000,
            'user_id' => $apporteur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        PaiementApporteur::create([
            'date_paiement'    => now()->toDateString(),
            'commission_id'    => $commission->id,
            'apporteur_id'     => $apporteur->id,
            'montant'          => 500,
            'mode_paiement_id' => 6,
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($apporteur->user)->get('/apporteur/paiement');

        // 1 000 demandés + 500 réglés = 1 500 réellement reçus.
        $this->assertSame(1500.0, (float) $reponse->viewData('montantPaye'));
        $this->assertSame(2, (int) $reponse->viewData('totalPayees'));
    }
}
