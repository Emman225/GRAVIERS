<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Les commissions d'apporteur sont au franc entier.
 *
 * Le franc CFA n'a pas de décimales. Trois des quatre circuits qui créent une
 * commission stockaient pourtant le résultat brut du pourcentage : 4,72 F,
 * 1,06 F… Le solde de l'apporteur, lui, est arrondi. Son tableau de bord
 * annonçait donc 104 F pendant que le formulaire « Enregistrer un paiement de
 * commission » proposait 103,72 F — deux chiffres pour la même dette, et un
 * reliquat de 0,28 F que personne ne pourra jamais payer.
 */
class CommissionArrondieTest extends TestCase
{
    use DatabaseTransactions;

    private function unApporteurSansCommission(): Apporteur
    {
        $apporteur = Apporteur::whereNotNull('user_id')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        CommissionApporteur::where('apporteur_id', $apporteur->id)->forceDelete();
        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        return $apporteur;
    }

    public function test_le_solde_et_les_commissions_annoncent_le_meme_montant(): void
    {
        $apporteur = $this->unApporteurSansCommission();

        // Le cas constaté en production : 99 + 4,72 = 103,72, arrondi à 104.
        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 99,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);
        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 4.72,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        $commissions = CommissionApporteur::where('apporteur_id', $apporteur->id)->get();
        $aPayer = (float) $commissions->sum(fn (CommissionApporteur $c) => $c->resteAPayerCommission());

        // L'écart d'origine : 104 annoncé, 103,72 à payer.
        $this->assertSame(104.0, $apporteur->soldeCalcule());
        $this->assertSame(103.72, round($aPayer, 2));

        Artisan::call('apporteur:arrondir-commissions', ['--apply' => true]);

        $apresArrondi = (float) CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->get()
            ->sum(fn (CommissionApporteur $c) => $c->resteAPayerCommission());

        // Les deux disent maintenant la même chose.
        $this->assertSame(104.0, $apresArrondi);
        $this->assertSame(104.0, $apporteur->fresh()->soldeCalcule());
    }

    public function test_le_solde_du_tableau_de_bord_suit_l_arrondi(): void
    {
        $apporteur = $this->unApporteurSansCommission();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 4.72,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);
        $apporteur->update(['solde' => 5]);

        Artisan::call('apporteur:arrondir-commissions', ['--apply' => true]);

        // Le solde est recalculé depuis les commissions arrondies : sans cela,
        // la correction ne ferait que déplacer l'écart de la commission vers le
        // solde.
        $this->assertSame(5.0, round((float) $apporteur->fresh()->solde));
        $this->assertSame(5.0, (float) CommissionApporteur::where('apporteur_id', $apporteur->id)->sum('montant'));
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $apporteur = $this->unApporteurSansCommission();

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 4.72,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        Artisan::call('apporteur:arrondir-commissions');

        $this->assertSame(4.72, round((float) $commission->fresh()->montant, 2));
    }

    public function test_une_commission_deja_ronde_n_est_pas_touchee(): void
    {
        $apporteur = $this->unApporteurSansCommission();

        $commission = CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 2500,
            'type_affaire' => 'LOCATION', 'statut' => 1,
        ]);

        $avant = $commission->updated_at;

        Artisan::call('apporteur:arrondir-commissions', ['--apply' => true]);

        $this->assertSame(2500.0, (float) $commission->fresh()->montant);
        $this->assertEquals($avant, $commission->fresh()->updated_at);
    }
}
