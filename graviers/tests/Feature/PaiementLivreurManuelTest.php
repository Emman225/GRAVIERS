<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\PaiementLivreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'enregistrement d'un paiement livreur, rouvert.
 *
 * Il avait été DÉSACTIVÉ pour empêcher un double paiement : la demande validée
 * ne laissait aucune trace dans `paiement_livreur`, si bien qu'une course payée
 * par ce chemin restait due et pouvait être réglée une seconde fois. La cause
 * est traitée — une demande validée solde désormais les courses —, la saisie
 * peut donc reprendre.
 */
class PaiementLivreurManuelTest extends TestCase
{
    use DatabaseTransactions;

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

        Livraison::where('livreur_id', $livreur->id)
            ->where('id', '!=', $course->id)->update(['livreur_id' => null]);

        $course->update([
            'etat_livraison' => \Help::$LIVRAISON_LIVREE,
            'cout_livraison' => 1500,
            'forfait_base'   => 0,
            'frais_km'       => 0,
        ]);

        PaiementLivreur::where('livraison_id', $course->id)->forceDelete();
        DemandePaiement::where('user_id', $livreur->user_id)->delete();

        return $livreur;
    }

    private function unAdminEnAgence(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        // Un décaissement doit sortir d'une caisse : le contrôleur refuse la
        // saisie sans agence. On en rattache une le temps du test plutôt que de
        // sauter — un test qui saute ne prouve rien. La transaction annule tout.
        if (!$admin->agence_id) {
            $agence = \App\Models\Agence::first();

            if (!$agence) {
                $this->markTestSkipped('Aucune agence en base.');
            }

            $admin->update(['agence_id' => $agence->id]);
        }

        return $admin->fresh();
    }

    public function test_l_ecran_propose_d_enregistrer_un_paiement(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdminEnAgence())->get('/livreurs/paiements');
        $reponse->assertOk();

        $html = $reponse->getContent();
        $this->assertStringContainsString('Enregistrer un paiement livreur', $html);
        // La course due est proposée, et non plus seulement listée.
        $this->assertStringContainsString('Courses à payer', $html);
    }

    public function test_le_paiement_saisi_attend_une_seconde_validation(): void
    {
        $livreur = $this->unLivreurAvecCourse();
        $course  = Livraison::where('livreur_id', $livreur->id)->first();

        URL::forceRootUrl('');
        $this->actingAs($this->unAdminEnAgence())->post('/livreurs/paiements', [
            'livraison_ids'    => [$course->id],
            'montant'          => 1500,
            'mode_paiement_id' => 6,
            'date_paiement'    => now()->toDateString(),
        ]);

        $paiement = PaiementLivreur::where('livraison_id', $course->id)->first();

        $this->assertNotNull($paiement, "Le paiement n'a pas été enregistré.");
        // statut 2 = en attente de la 2e validation, comme pour les fournisseurs.
        $this->assertSame(2, (int) $paiement->statut);
    }

    public function test_un_montant_superieur_au_reste_est_refuse(): void
    {
        $livreur = $this->unLivreurAvecCourse();
        $course  = Livraison::where('livreur_id', $livreur->id)->first();

        URL::forceRootUrl('');
        $this->actingAs($this->unAdminEnAgence())->post('/livreurs/paiements', [
            'livraison_ids'    => [$course->id],
            'montant'          => 99999,
            'mode_paiement_id' => 6,
        ]);

        $this->assertSame(0, PaiementLivreur::where('livraison_id', $course->id)->count());
    }

    public function test_une_demande_validee_solde_la_course(): void
    {
        $livreur = $this->unLivreurAvecCourse();
        $course  = Livraison::where('livreur_id', $livreur->id)->first();

        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Il faut deux administrateurs actifs.');
        }

        $this->assertSame(1500.0, $course->resteAPayerLivreur());

        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $livreur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $admins[0]->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($admins[1])->get('/valide-demande-' . $demande->id . '-livreur-accepter');

        // La course n'est plus due que de 500 : elle ne peut donc plus être
        // réglée une seconde fois pour la totalité.
        $this->assertSame(500.0, $course->fresh()->resteAPayerLivreur());
        $this->assertSame(
            1000.0,
            (float) PaiementLivreur::where('demande_paiement_id', $demande->id)->sum('montant')
        );
    }

    public function test_le_solde_ne_retranche_pas_deux_fois_la_demande(): void
    {
        $livreur = $this->unLivreurAvecCourse();

        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Il faut deux administrateurs actifs.');
        }

        $avant = $livreur->soldeCalcule();

        $demande = DemandePaiement::create([
            'montant'          => 1000,
            'user_id'          => $livreur->user_id,
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
            'user_valide_id'   => $admins[0]->id,
        ]);

        URL::forceRootUrl('');
        $this->actingAs($admins[1])->get('/valide-demande-' . $demande->id . '-livreur-accepter');

        // Le montant sort UNE fois : comme demande. Le règlement qu'elle a
        // engendré porte `demande_paiement_id` et n'est pas recompté.
        $this->assertSame($avant - 1000, $livreur->soldeCalcule());
    }
}
