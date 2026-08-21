<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La double validation des demandes de paiement, depuis le journal des
 * paiements fournisseurs.
 *
 * L'écran affichait les demandes sans permettre de les traiter : il fallait
 * partir sur un autre écran. Les deux pointent la même route ; c'est le
 * contrôleur qui garde la règle, si bien que deux portes d'entrée ne peuvent
 * pas valider deux fois.
 */
class ValidationDemandeSurPaiementsTest extends TestCase
{
    use DatabaseTransactions;

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
        $fournisseur = Fournisseur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur rattaché à un compte.');
        }

        return DemandePaiement::create([
            'montant'          => 7777,
            'user_id'          => $fournisseur->user_id,
            'numero_compte'    => '0102030405',
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
        ]);
    }

    public function test_le_bouton_de_premiere_validation_est_propose(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/fournisseurs/paiements')->getContent();

        // On vise le LIEN et non le libelle : « 1re validation » est aussi
        // un en-tete de colonne, la chaine seule ne prouverait rien.
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-fournisseur-accepter',
            $html
        );
        $this->assertStringContainsString('retour=show.fournisseurs.paiements', $html);
    }

    public function test_le_premier_validateur_ne_se_voit_pas_proposer_la_seconde(): void
    {
        [$admin] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $admin->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($admin)->get('/fournisseurs/paiements')->getContent();

        $this->assertStringContainsString("En attente d'un autre administrateur", $html);
        // Aucun lien de validation pour cette demande : le libelle « 2e
        // validation » subsiste en en-tete de colonne, on ne peut donc pas
        // le chercher tel quel.
        $this->assertStringNotContainsString(
            'valide-demande-' . $demande->id . '-fournisseur-accepter',
            $html
        );
    }

    public function test_un_autre_admin_peut_donner_la_seconde(): void
    {
        [$premier, $second] = $this->deuxAdmins();
        $demande = $this->uneDemande();
        $demande->update(['user_valide_id' => $premier->id]);

        URL::forceRootUrl('');
        $html = $this->actingAs($second)->get('/fournisseurs/paiements')->getContent();

        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-fournisseur-accepter',
            $html
        );
        $this->assertStringContainsString(
            'valide-demande-' . $demande->id . '-fournisseur-refuser',
            $html
        );
    }

    public function test_la_validation_ramene_sur_le_journal_des_paiements(): void
    {
        [$premier] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($premier)->get(
            '/valide-demande-' . $demande->id . '-fournisseur-accepter?retour=show.fournisseurs.paiements'
        );

        $reponse->assertRedirect(route('show.fournisseurs.paiements'));
        $this->assertSame((int) $premier->id, (int) $demande->fresh()->user_valide_id);
    }

    public function test_une_route_de_retour_non_prevue_est_ignoree(): void
    {
        [$premier] = $this->deuxAdmins();
        $demande = $this->uneDemande();

        URL::forceRootUrl('');
        $reponse = $this->actingAs($premier)->get(
            '/valide-demande-' . $demande->id . '-fournisseur-accepter?retour=client.index'
        );

        // Une route reçue en paramètre et suivie telle quelle ouvrirait une
        // redirection vers n'importe où : seule la liste fermée est acceptée.
        $reponse->assertRedirect(route('show.listeDeDemandeFournisseur'));
    }
}
