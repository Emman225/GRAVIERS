<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\PaiementFournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Une demande de paiement acceptée solde les bons du fournisseur.
 *
 * L'entreprise paie ses fournisseurs par deux chemins : le règlement d'un bon
 * précis, et la demande de paiement portant sur un montant. Seul le premier
 * écrivait dans `paiement_fournisseur`. Les bons restaient donc entièrement
 * dus après avoir été payés : le popup « Enregistrer un paiement fournisseur »
 * proposait encore la totalité, et un administrateur pouvait régler une
 * seconde fois ce qui l'était déjà.
 */
class DemandeValideeSoldeLesBonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: Fournisseur, 1: User, 2: User} */
    private function acteurs(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Il faut deux administrateurs actifs.');
        }

        $fournisseur = Fournisseur::whereHas('enlevements', fn ($q) =>
            $q->whereNotNull('fournisseur_validation')
        )->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec des bons validés.');
        }

        return [$fournisseur, $admins[0], $admins[1]];
    }

    private function bonsDu(Fournisseur $fournisseur)
    {
        return Enlevement::where('fournisseur_id', $fournisseur->id)
            ->whereNotNull('fournisseur_validation')
            ->whereNull('deleted_at')
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    public function test_la_demande_acceptee_impute_les_bons_du_plus_ancien(): void
    {
        [$fournisseur, $premier, $second] = $this->acteurs();

        $bons = $this->bonsDu($fournisseur);
        $duAvant = (float) $bons->sum(fn (Enlevement $e) => $e->resteAPayer());

        if ($duAvant < 100) {
            $this->markTestSkipped('Les bons de ce fournisseur sont déjà soldés.');
        }

        $montant = min(1000.0, $duAvant);

        $demande = DemandePaiement::create([
            'montant'          => $montant,
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-fournisseur-accepter');

        // Le versement est inscrit au journal des bons…
        $lignes = PaiementFournisseur::where('demande_paiement_id', $demande->id)->get();
        $this->assertNotEmpty($lignes, "La demande acceptée n'a rien inscrit sur les bons.");
        $this->assertSame($montant, (float) $lignes->sum('montant'));

        // …et le plus ancien bon non soldé est servi en premier.
        $premierBonNonSolde = $bons->first(fn (Enlevement $e) => $e->resteAPayer() >= 1);
        $this->assertSame(
            (int) $premierBonNonSolde->id,
            (int) $lignes->first()->enlevement_id
        );

        // Ce que les bons doivent encore a diminué d'autant : c'est ce chiffre
        // que lit le popup « Enregistrer un paiement fournisseur ».
        $duApres = (float) $this->bonsDu($fournisseur)->sum(fn (Enlevement $e) => $e->resteAPayer());
        $this->assertSame($duAvant - $montant, $duApres);
    }

    public function test_le_solde_ne_retranche_pas_deux_fois_le_meme_versement(): void
    {
        [$fournisseur, $premier, $second] = $this->acteurs();

        $duAvant = (float) $this->bonsDu($fournisseur)->sum(fn (Enlevement $e) => $e->resteAPayer());

        if ($duAvant < 100) {
            $this->markTestSkipped('Les bons de ce fournisseur sont déjà soldés.');
        }

        $montant = min(1000.0, $duAvant);
        $soldeAvant = $fournisseur->soldeCalcule();

        $demande = DemandePaiement::create([
            'montant'          => $montant,
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-fournisseur-accepter');

        // Le montant sort UNE fois : comme demande. Le règlement qu'elle a
        // engendré porte `demande_paiement_id` et n'est donc pas recompté.
        $this->assertSame($soldeAvant - $montant, $fournisseur->soldeCalcule());
    }

    public function test_le_reglement_cree_porte_les_deux_validateurs(): void
    {
        [$fournisseur, $premier, $second] = $this->acteurs();

        $duAvant = (float) $this->bonsDu($fournisseur)->sum(fn (Enlevement $e) => $e->resteAPayer());

        if ($duAvant < 100) {
            $this->markTestSkipped('Les bons de ce fournisseur sont déjà soldés.');
        }

        $demande = DemandePaiement::create([
            'montant'          => min(500.0, $duAvant),
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-fournisseur-accepter');

        $ligne = PaiementFournisseur::where('demande_paiement_id', $demande->id)->first();

        $this->assertNotNull($ligne);
        $this->assertSame((int) $premier->id, (int) $ligne->user_valide_id);
        $this->assertSame((int) $second->id, (int) $ligne->user_valide2_id);
    }

    public function test_une_demande_refusee_n_impute_rien(): void
    {
        [$fournisseur, $premier, $second] = $this->acteurs();

        $demande = DemandePaiement::create([
            'montant'          => 500,
            'user_id'          => $fournisseur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $premier->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($second)->get('/valide-demande-' . $demande->id . '-fournisseur-refuser');

        $this->assertSame(
            0,
            PaiementFournisseur::where('demande_paiement_id', $demande->id)->count()
        );
    }
}
