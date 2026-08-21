<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Le rattrapage des demandes acceptées AVANT que l'imputation n'existe.
 *
 * Une demande acceptée solde désormais les bons au moment de la 2e validation.
 * Mais ce mécanisme n'agit qu'à ce moment-là : les demandes acceptées avant
 * n'ont rien inscrit dans `paiement_fournisseur`. Leurs bons restent
 * entièrement dus alors que le fournisseur a été payé, et le popup
 * « Enregistrer un paiement fournisseur » propose encore la totalité.
 *
 * Le scénario rejoué est celui constaté en production :
 *   - 400 F dus sur les bons ;
 *   - un administrateur règle 100 F à la main      -> reste 300 ;
 *   - le fournisseur demande 100 F, deux administrateurs valident, il est payé
 *     -> mais les bons annoncent toujours 300 au lieu de 200.
 */
class ImputerDemandesPayeesTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: Fournisseur, 1: DemandePaiement, 2: float} */
    private function scenarioDeProduction(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        $fournisseur = Fournisseur::whereHas('enlevements', fn ($q) =>
            $q->whereNotNull('fournisseur_validation')
        )->first();

        if ($admins->count() < 2 || !$fournisseur) {
            $this->markTestSkipped('Il faut deux administrateurs et un fournisseur avec des bons validés.');
        }

        $du = (float) $this->duSurLesBons($fournisseur);

        if ($du < 200) {
            $this->markTestSkipped('Les bons de ce fournisseur ne portent pas assez de dette.');
        }

        // Le règlement saisi à la main par un administrateur.
        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->orderBy('id')->first();

        PaiementFournisseur::create([
            'date_paiement'    => now()->toDateString(),
            'enlevement_id'    => $bon->id,
            'fournisseur_id'   => $fournisseur->id,
            'montant'          => 100,
            'mode_paiement_id' => 6,
            'statut'           => 1,
            'user_valide_id'   => $admins[0]->id,
            'user_valide2_id'  => $admins[1]->id,
        ]);

        // La demande du fournisseur, acceptée SANS imputation : l'état d'avant.
        $demande = DemandePaiement::create([
            'numero'           => '260818212210',
            'montant'          => 100,
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $admins[0]->id,
            'user_valide2_id'  => $admins[1]->id,
            'paye'             => 1,
        ]);

        return [$fournisseur, $demande, $du];
    }

    private function duSurLesBons(Fournisseur $fournisseur): float
    {
        return (float) Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')
            ->whereNull('deleted_at')
            ->get()
            ->sum(fn (Enlevement $e) => $e->resteAPayer());
    }

    public function test_le_rattrapage_solde_les_bons_de_la_demande_deja_payee(): void
    {
        [$fournisseur, $demande, $du] = $this->scenarioDeProduction();

        // L'état constaté : le fournisseur a été payé deux fois 100, mais les
        // bons n'en ont enregistré qu'un.
        $this->assertSame($du - 100, $this->duSurLesBons($fournisseur));

        Artisan::call('fournisseur:imputer-demandes-payees', ['--apply' => true]);

        $this->assertSame($du - 200, $this->duSurLesBons($fournisseur));
        $this->assertSame(
            100.0,
            (float) PaiementFournisseur::where('demande_paiement_id', $demande->id)->sum('montant')
        );
    }

    public function test_le_rattrapage_ne_change_pas_le_solde_du_fournisseur(): void
    {
        [$fournisseur] = $this->scenarioDeProduction();

        $avant = $fournisseur->soldeCalcule();

        Artisan::call('fournisseur:imputer-demandes-payees', ['--apply' => true]);

        // Les règlements créés portent `demande_paiement_id` : le calcul les
        // ignore, la demande étant déjà comptée. Sans cela, le fournisseur
        // perdrait une seconde fois le montant qu'il a touché une seule.
        $this->assertSame($avant, $fournisseur->soldeCalcule());
    }

    public function test_relancer_le_rattrapage_n_impute_pas_deux_fois(): void
    {
        [$fournisseur, $demande] = $this->scenarioDeProduction();

        Artisan::call('fournisseur:imputer-demandes-payees', ['--apply' => true]);
        $apresUnPassage = $this->duSurLesBons($fournisseur);

        Artisan::call('fournisseur:imputer-demandes-payees', ['--apply' => true]);

        $this->assertSame($apresUnPassage, $this->duSurLesBons($fournisseur));
        $this->assertSame(
            1,
            PaiementFournisseur::where('demande_paiement_id', $demande->id)->count()
        );
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        [$fournisseur, $demande] = $this->scenarioDeProduction();

        $avant = $this->duSurLesBons($fournisseur);

        Artisan::call('fournisseur:imputer-demandes-payees');

        $this->assertSame($avant, $this->duSurLesBons($fournisseur));
        $this->assertSame(
            0,
            PaiementFournisseur::where('demande_paiement_id', $demande->id)->count()
        );
    }
}
