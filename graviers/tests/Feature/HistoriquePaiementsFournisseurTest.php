<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'historique des paiements du fournisseur.
 *
 * L'écran ne montrait que les demandes qu'il avait lui-même initiées. Les
 * règlements saisis par un administrateur sur ses bons n'y figuraient nulle
 * part : le fournisseur ne voyait donc pas les sommes qu'on lui avait versées
 * de notre propre initiative, et son historique ne retombait pas sur ce qu'il
 * avait réellement reçu.
 */
class HistoriquePaiementsFournisseurTest extends TestCase
{
    use DatabaseTransactions;

    private function unFournisseurAvecBon(): Fournisseur
    {
        $fournisseur = Fournisseur::whereNotNull('user_id')->whereHas('user')
            ->whereHas('enlevements', fn ($q) => $q->whereNotNull('fournisseur_validation'))
            ->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec un bon validé.');
        }

        return $fournisseur;
    }

    public function test_le_reglement_saisi_par_un_admin_apparait(): void
    {
        $fournisseur = $this->unFournisseurAvecBon();

        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->first();

        PaiementFournisseur::create([
            'date_paiement'    => now()->toDateString(),
            'enlevement_id'    => $bon->id,
            'fournisseur_id'   => $fournisseur->id,
            'montant'          => 4321,
            'mode_paiement_id' => 6,
            'reference'        => 'REGLE-ADMIN-1',
            'statut'           => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($fournisseur->user)
            ->get('/liste-des-demande-de-paiement')->getContent();

        $this->assertStringContainsString('REGLE-ADMIN-1', $html);
        $this->assertStringContainsString('4 321', $html);
        $this->assertStringContainsString("L'entreprise", $html);
        $this->assertStringContainsString($bon->code_enleve, $html);
    }

    public function test_un_reglement_issu_d_une_demande_n_apparait_pas_deux_fois(): void
    {
        $fournisseur = $this->unFournisseurAvecBon();

        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->first();

        $demande = DemandePaiement::create([
            'numero'           => 'DEM-UNIQUE-1',
            'montant'          => 2500,
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'paye'             => 1,
        ]);

        // Le règlement que cette demande a engendré : il ne doit pas faire une
        // seconde ligne, sinon l'historique double le montant reçu.
        PaiementFournisseur::create([
            'date_paiement'       => now()->toDateString(),
            'enlevement_id'       => $bon->id,
            'demande_paiement_id' => $demande->id,
            'fournisseur_id'      => $fournisseur->id,
            'montant'             => 2500,
            'mode_paiement_id'    => 6,
            'reference'           => 'DEM-UNIQUE-1',
            'statut'              => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($fournisseur->user)
            ->get('/liste-des-demande-de-paiement')->getContent();

        $this->assertSame(1, substr_count($html, 'DEM-UNIQUE-1'));
    }

    public function test_le_montant_recu_compte_les_deux_origines(): void
    {
        $fournisseur = $this->unFournisseurAvecBon();

        DemandePaiement::where('user_id', $fournisseur->user_id)->delete();
        PaiementFournisseur::where('fournisseur_id', $fournisseur->id)->delete();

        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->first();

        DemandePaiement::create([
            'numero'  => 'DEM-1', 'montant' => 1000,
            'user_id' => $fournisseur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        PaiementFournisseur::create([
            'date_paiement'  => now()->toDateString(), 'enlevement_id' => $bon->id,
            'fournisseur_id' => $fournisseur->id, 'montant' => 500,
            'mode_paiement_id' => 6, 'statut' => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($fournisseur->user)
            ->get('/liste-des-demande-de-paiement')->getContent();

        // 1 000 demandés + 500 réglés = 1 500 réellement reçus.
        $this->assertStringContainsString('1 500 FCFA reçus', $html);
    }
}
