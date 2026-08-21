<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\DemandePaiement;
use App\Models\Livreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La double validation des demandes de paiement livreur, depuis le journal.
 *
 * L'écran ne faisait que les lister : il fallait partir sur un autre écran
 * pour les traiter. Les deux pointent la même route ; c'est le contrôleur qui
 * garde la règle, si bien que deux portes d'entrée ne peuvent pas valider
 * deux fois.
 */
class ValidationDemandeLivreurTest extends TestCase
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

        // Un décaissement sort d'une caisse : l'écran l'exige.
        foreach ($admins as $a) {
            if (!$a->agence_id && ($agence = Agence::first())) {
                $a->update(['agence_id' => $agence->id]);
            }
        }

        return [$admins[0]->fresh(), $admins[1]->fresh()];
    }

    private function uneDemande(): DemandePaiement
    {
        $livreur = Livreur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur rattaché à un compte.');
        }

        return DemandePaiement::create([
            'montant'          => 3210,
            'user_id'          => $livreur->user_id,
            'numero_compte'    => '0709080706',
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
        ]);
    }

    public function test_le_bouton_de_premiere_validation_est_propose(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/livreurs/paiements')->getContent();

        // On vise le LIEN et non le libellé : « 1re validation » est aussi un
        // en-tête de colonne, la chaîne seule ne prouverait rien.
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-livreur-accepter',
            $html
        );
        $this->assertStringContainsString('retour=show.livreurs.paiements', $html);
    }

    public function test_le_premier_validateur_ne_se_voit_pas_proposer_la_seconde(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $admin->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/livreurs/paiements')->getContent();

        $this->assertStringContainsString("En attente d'un autre administrateur", $html);
        $this->assertStringNotContainsString(
            'valide-demande-' . $demande->id . '-livreur-accepter',
            $html
        );
    }

    public function test_un_autre_admin_peut_donner_la_seconde(): void
    {
        [$premier, $second] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $premier->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($second)->get('/livreurs/paiements')->getContent();

        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-livreur-accepter',
            $html
        );
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-livreur-refuser',
            $html
        );
    }

    public function test_la_validation_ramene_sur_le_journal_des_paiements(): void
    {
        [$premier] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($premier)->get(
            '/valide-demande-' . $demande->id . '-livreur-accepter?retour=show.livreurs.paiements'
        );

        $reponse->assertRedirect(route('show.livreurs.paiements'));
        $this->assertSame((int) $premier->id, (int) $demande->fresh()->user_valide_id);
    }

    public function test_les_deux_validateurs_sont_nommes(): void
    {
        [$premier, $second] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update([
            'user_valide_id'  => $premier->id,
            'user_valide2_id' => $second->id,
            'paye'            => 1,
        ]);

        URL::forceRootUrl('');
        $html = $this->actingAs($premier)->get('/livreurs/paiements')->getContent();

        $this->assertStringContainsString($premier->login, $html);
        $this->assertStringContainsString($second->login, $html);
    }
}
