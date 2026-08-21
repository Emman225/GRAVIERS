<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\DemandePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Les demandes d'apporteur apparaissent — et se valident — sur leur journal.
 *
 * Elles n'existaient que sur l'écran dédié : l'administrateur qui suit ce
 * qu'on doit aux apporteurs ne les voyait pas, et devait changer d'écran pour
 * les traiter.
 */
class ValidationDemandeApporteurEcranTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0: User, 1: User} */
    private function deuxAdmins(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->take(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Il faut deux administrateurs actifs.');
        }

        return [$admins[0], $admins[1]];
    }

    private function uneDemande(): DemandePaiement
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        return DemandePaiement::create([
            'montant'          => 6543,
            'user_id'          => $apporteur->user_id,
            'numero_compte'    => '0102030405',
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
        ]);
    }

    public function test_la_demande_apparait_sur_le_journal(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($admin)->get('/apporteurs/paiements');
        $reponse->assertOk();

        $html = $reponse->getContent();

        $this->assertStringContainsString('Demandes de paiement initiées par les apporteurs', $html);
        $this->assertStringContainsString('6 543', $html);
        $this->assertStringContainsString("L'apporteur", $html);
        $this->assertStringContainsString('0102030405', $html);
    }

    public function test_le_bouton_de_premiere_validation_est_propose(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/apporteurs/paiements')->getContent();

        // On vise le LIEN et non le libellé : « 1re validation » est aussi un
        // en-tête de colonne, la chaîne seule ne prouverait rien.
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-apporteur-accepter',
            $html
        );
        $this->assertStringContainsString('retour=show.apporteurs.paiements', $html);
    }

    public function test_le_premier_validateur_ne_se_voit_pas_proposer_la_seconde(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $admin->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/apporteurs/paiements')->getContent();

        $this->assertStringContainsString("En attente d'un autre administrateur", $html);
        $this->assertStringNotContainsString(
            'valide-demande-' . $demande->id . '-apporteur-accepter',
            $html
        );
    }

    public function test_un_autre_admin_peut_donner_la_seconde(): void
    {
        [$premier, $second] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $premier->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($second)->get('/apporteurs/paiements')->getContent();

        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-apporteur-accepter',
            $html
        );
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-apporteur-refuser',
            $html
        );
    }

    public function test_la_validation_ramene_sur_le_journal_des_paiements(): void
    {
        [$premier] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($premier)->get(
            '/valide-demande-' . $demande->id . '-apporteur-accepter?retour=show.apporteurs.paiements'
        );

        $reponse->assertRedirect(route('show.apporteurs.paiements'));
        $this->assertSame((int) $premier->id, (int) $demande->fresh()->user_valide_id);
    }
}
