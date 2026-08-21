<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Le « Solde disponible » du fournisseur doit être justifié par ses pièces.
 *
 * `fournisseur.solde` est une colonne tenue à la main, créditée à la validation
 * d'un bon et débitée depuis cinq endroits. Elle dérive donc dès qu'un
 * événement lui échappe — un bon supprimé, un vidage de la base qui efface les
 * bons sans toucher aux soldes — et le fournisseur voit alors un montant que
 * plus rien ne justifie, dont il peut demander le paiement.
 */
class SoldeFournisseurTest extends TestCase
{
    use DatabaseTransactions;

    private function unFournisseurAvecBons(): Fournisseur
    {
        $fournisseur = Fournisseur::whereHas('enlevements', fn ($q) =>
            $q->whereNotNull('fournisseur_validation')
        )->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec des bons validés.');
        }

        return $fournisseur;
    }

    public function test_le_solde_calcule_ne_retient_que_les_bons_valides(): void
    {
        $fournisseur = $this->unFournisseurAvecBons();

        $attendu = (float) Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')
            ->get()
            ->sum(fn (Enlevement $e) => $e->montantDu());

        // Un bon non validé ne doit rien apporter : le fournisseur ne l'a pas
        // encore servi.
        $nonValide = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNull('fournisseur_validation')->first();

        if ($nonValide) {
            $this->assertGreaterThan(0, $nonValide->montantDu());
        }

        $this->assertSame($attendu, $fournisseur->soldeCalcule());
    }

    public function test_un_reglement_diminue_le_solde(): void
    {
        $fournisseur = $this->unFournisseurAvecBons();
        $bon = Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')->first();

        $avant = $fournisseur->soldeCalcule();

        PaiementFournisseur::create([
            'fournisseur_id' => $fournisseur->id,
            'enlevement_id'  => $bon->id,
            'montant'        => 1000,
            'statut'         => 1,
            'date_paiement'  => now(),
        ]);

        $this->assertSame($avant - 1000, $fournisseur->soldeCalcule());
    }

    public function test_une_demande_refusee_ne_ampute_pas_le_solde(): void
    {
        $fournisseur = $this->unFournisseurAvecBons();
        $avant = $fournisseur->soldeCalcule();

        // Refusée (paye = 2) : le montant a été restitué, il ne doit pas être
        // retranché une seconde fois.
        DemandePaiement::create([
            'montant'  => 5000,
            'user_id'  => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'paye'     => 2,
        ]);

        $this->assertSame($avant, $fournisseur->soldeCalcule());

        // En attente (paye = 0) : le montant est réservé, il sort du disponible.
        DemandePaiement::create([
            'montant'  => 5000,
            'user_id'  => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'paye'     => 0,
        ]);

        $this->assertSame($avant - 5000, $fournisseur->soldeCalcule());
    }

    public function test_le_rattrapage_realigne_une_colonne_qui_a_derive(): void
    {
        $fournisseur = $this->unFournisseurAvecBons();
        $justifie = $fournisseur->soldeCalcule();

        // On simule la dérive : un solde que plus aucune pièce ne justifie.
        $fournisseur->update(['solde' => $justifie + 35000]);

        Artisan::call('fournisseur:rattraper-solde', ['--apply' => true]);

        $this->assertSame($justifie, round((float) $fournisseur->fresh()->solde));
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $fournisseur = $this->unFournisseurAvecBons();
        $fausseValeur = $fournisseur->soldeCalcule() + 12345;

        $fournisseur->update(['solde' => $fausseValeur]);

        Artisan::call('fournisseur:rattraper-solde');

        $this->assertSame(
            round($fausseValeur),
            round((float) $fournisseur->fresh()->solde),
            "La simulation ne doit rien modifier."
        );
    }
}
