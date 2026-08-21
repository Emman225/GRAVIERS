<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\PaiementApporteur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Une demande de paiement d'apporteur acceptée solde ses commissions.
 *
 * Troisième et dernier des trois tiers. L'apporteur est payé par deux chemins :
 * la demande qu'il initie, et le règlement qu'un administrateur saisit sur une
 * de ses commissions. Seul le second écrivait dans `paiement_apporteur` : ses
 * commissions restaient donc entièrement dues après avoir été payées, et le
 * formulaire « Enregistrer un paiement de commission » proposait encore la
 * totalité.
 */
class DemandeApporteurSoldeLesCommissionsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: Apporteur, 1: User, 2: User} */
    private function acteurs(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if ($admins->count() < 2 || !$apporteur) {
            $this->markTestSkipped('Il faut deux administrateurs et un apporteur.');
        }

        CommissionApporteur::where('apporteur_id', $apporteur->id)->forceDelete();
        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        CommissionApporteur::create([
            'apporteur_id' => $apporteur->id, 'montant' => 3000,
            'type_affaire' => 'VENTE', 'statut' => 1,
        ]);

        return [$apporteur, $admins[0], $admins[1]];
    }

    private function duSurLesCommissions(Apporteur $apporteur): float
    {
        return (float) CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->whereNull('deleted_at')
            ->get()
            ->sum(fn (CommissionApporteur $c) => $c->resteAPayerCommission());
    }

    public function test_la_demande_acceptee_solde_les_commissions(): void
    {
        [$apporteur, $premier, $second] = $this->acteurs();

        $this->assertSame(3000.0, $this->duSurLesCommissions($apporteur));

        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $apporteur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-apporteur-accepter');

        // C'est ce chiffre que lit le formulaire « Commissions à payer ».
        $this->assertSame(2000.0, $this->duSurLesCommissions($apporteur));
        $this->assertSame(
            1000.0,
            (float) PaiementApporteur::where('demande_paiement_id', $demande->id)->sum('montant')
        );
    }

    public function test_le_reglement_apparait_dans_le_journal_des_paiements(): void
    {
        [$apporteur, $premier, $second] = $this->acteurs();

        $demande = DemandePaiement::create([
            'numero'           => 'DEM-JOURNAL',
            'montant'          => 50,
            'user_id'          => $apporteur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-apporteur-accepter');

        // Le versement doit se lire dans le journal des règlements, avec son
        // reçu : c'est la seule trace consultable de ce qui a été payé.
        $html = $this->actingAs($premier)->get('/apporteurs/paiements')->getContent();

        $this->assertStringContainsString('DEM-JOURNAL', $html);
        $this->assertSame(
            1,
            PaiementApporteur::where('demande_paiement_id', $demande->id)
                ->where('statut', 1)->count()
        );
    }

    public function test_le_solde_ne_retranche_pas_deux_fois_le_versement(): void
    {
        [$apporteur, $premier, $second] = $this->acteurs();

        $avant = $apporteur->soldeCalcule();

        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $apporteur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-apporteur-accepter');

        // Le montant sort UNE fois : comme demande. Le règlement qu'elle a
        // engendré porte `demande_paiement_id` et n'est pas recompté.
        $this->assertSame($avant - 1000, $apporteur->soldeCalcule());
    }

    public function test_une_demande_refusee_n_impute_rien(): void
    {
        [$apporteur, $premier, $second] = $this->acteurs();

        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $apporteur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-apporteur-refuser');

        $this->assertSame(3000.0, $this->duSurLesCommissions($apporteur));
        $this->assertSame(0, PaiementApporteur::where('demande_paiement_id', $demande->id)->count());
    }

    public function test_le_rattrapage_traite_les_demandes_deja_acceptees(): void
    {
        [$apporteur, $premier, $second] = $this->acteurs();

        // L'état d'avant : acceptée, mais sans imputation.
        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $apporteur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
            'user_valide2_id'  => $second->id,
            'paye'             => 1,
        ]);

        $this->assertSame(3000.0, $this->duSurLesCommissions($apporteur));

        Artisan::call('apporteur:imputer-demandes-payees', ['--apply' => true]);

        $this->assertSame(2000.0, $this->duSurLesCommissions($apporteur));
        $this->assertSame(
            1,
            PaiementApporteur::where('demande_paiement_id', $demande->id)->count()
        );

        // Rejouable : une demande déjà imputée est ignorée.
        Artisan::call('apporteur:imputer-demandes-payees', ['--apply' => true]);

        $this->assertSame(2000.0, $this->duSurLesCommissions($apporteur));
    }
}
