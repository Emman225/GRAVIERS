<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DecisionClientTerme;
use App\Models\DemandeCompteClientATerme;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Les décisions de crédit passent par une seconde validation.
 *
 * Accorder le statut de client à terme ou relever un plafond, c'est décider
 * combien l'entreprise accepte de ne pas être payée tout de suite. Cela se
 * décidait seul, d'un clic, et s'appliquait aussitôt — l'e-mail au client
 * partant dans la foulée, ce qui rendait le retour en arrière impossible.
 *
 * Trois exigences tiennent le mécanisme :
 *   - tant que la décision attend, RIEN ne bouge sur le client ;
 *   - celui qui saisit ne valide pas ;
 *   - l'e-mail part à la seconde validation, jamais à la première.
 */
class DecisionClientTermeTest extends TestCase
{
    use DatabaseTransactions;

    private function deuxAdmins(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->limit(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Deux administrateurs actifs sont necessaires.');
        }

        return [$admins[0], $admins[1]];
    }

    /** Un client au comptant, et sa demande de passage a terme. */
    private function uneDemande(): array
    {
        $client = Client::where('client_a_terme', 0)->first() ?? Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $client->update(['client_a_terme' => 0, 'plafond_credit' => 0, 'delai_paiement' => 30]);

        $demande = DemandeCompteClientATerme::create([
            'client_id' => $client->id,
            'user_id'   => $client->user_id ?? User::first()->id,
            'objet'     => 'Demande creee pour la recette',
            'approuve'  => 0,
        ]);

        return [$client->fresh(), $demande];
    }

    private function approuver(User $auteur, DemandeCompteClientATerme $demande, array $donnees = [])
    {
        URL::forceRootUrl('');

        return $this->actingAs($auteur)->post('/Validation-' . $demande->id . '-1', array_merge([
            'plafond_credit' => 5000000,
            'delai_paiement' => 45,
        ], $donnees));
    }

    private function valider(User $validateur, DecisionClientTerme $decision)
    {
        URL::forceRootUrl('');

        return $this->actingAs($validateur)->post('/decision-credit-' . $decision->id . '/valider');
    }

    private function derniereDecision(int $clientId): ?DecisionClientTerme
    {
        return DecisionClientTerme::where('client_id', $clientId)->latest('id')->first();
    }

    // --------------------------------------------------------- L'ACTIVATION

    public function test_une_approbation_ne_rend_pas_le_client_a_terme(): void
    {
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $this->assertSame(0, (int) $client->fresh()->client_a_terme,
            'Tant que la decision attend, le client reste au comptant.');

        $decision = $this->derniereDecision($client->id);

        $this->assertNotNull($decision);
        $this->assertTrue($decision->attendUneSecondeValidation());
        $this->assertSame(DecisionClientTerme::ACTIVATION, $decision->type);
    }

    public function test_aucun_email_ne_part_a_la_saisie(): void
    {
        // C est ce qui rendait la decision irreversible : le client etait
        // prevenu avant meme qu un second administrateur ait regarde.
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        Mail::assertNothingSent();
    }

    public function test_le_second_administrateur_applique_la_decision(): void
    {
        Mail::fake();

        [$auteur, $valideur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $decision = $this->derniereDecision($client->id);

        $this->valider($valideur, $decision);

        $client = $client->fresh();

        $this->assertSame(1, (int) $client->client_a_terme);
        $this->assertSame(5000000.0, round((float) $client->plafond_credit, 2));
        $this->assertSame(45, (int) $client->delai_paiement);
        $this->assertSame(DecisionClientTerme::APPLIQUEE, (int) $decision->fresh()->statut);
    }

    public function test_la_demande_suit_l_issue(): void
    {
        // Laisser la demande « en attente » apres une decision prise donnerait
        // deux verites contradictoires sur le meme dossier.
        Mail::fake();

        [$auteur, $valideur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);
        $this->valider($valideur, $this->derniereDecision($client->id));

        $this->assertSame(1, (int) $demande->fresh()->approuve);
    }

    public function test_celui_qui_saisit_ne_valide_pas(): void
    {
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $decision = $this->derniereDecision($client->id);

        $this->valider($auteur, $decision);

        $this->assertTrue($decision->fresh()->attendUneSecondeValidation());
        $this->assertSame(0, (int) $client->fresh()->client_a_terme);
    }

    public function test_un_gestionnaire_ne_valide_pas(): void
    {
        Mail::fake();

        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $this->valider($gestionnaire, $this->derniereDecision($client->id));

        $this->assertSame(0, (int) $client->fresh()->client_a_terme);
    }

    public function test_un_refus_de_decision_ne_change_rien(): void
    {
        Mail::fake();

        [$auteur, $valideur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $decision = $this->derniereDecision($client->id);

        URL::forceRootUrl('');
        $this->actingAs($valideur)->post('/decision-credit-' . $decision->id . '/refuser');

        $this->assertSame(0, (int) $client->fresh()->client_a_terme);
        $this->assertSame(DecisionClientTerme::REFUSEE, (int) $decision->fresh()->statut);
        Mail::assertNothingSent();
    }

    public function test_deux_decisions_ne_s_empilent_pas_sur_un_client(): void
    {
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);
        $this->approuver($auteur, $demande, ['plafond_credit' => 9000000]);

        $this->assertSame(1, DecisionClientTerme::where('client_id', $client->id)
            ->where('statut', DecisionClientTerme::EN_ATTENTE)->count(),
            'Une seule decision peut attendre par client.');
    }

    // ------------------------------------------------------------ LE PLAFOND

    public function test_une_revision_de_plafond_attend_aussi(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $client = Client::where('client_a_terme', 1)->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client a terme en base.');
        }

        $ancien = (float) $client->plafond_credit;

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-a-terme-' . $client->id . '/plafond', [
            'plafond_credit' => $ancien + 1000000,
            'delai_paiement' => 60,
            'motif'          => 'Recette',
        ]);

        $this->assertSame($ancien, (float) $client->fresh()->plafond_credit,
            'Le plafond ne doit pas bouger avant la seconde validation.');

        $decision = $this->derniereDecision($client->id);

        $this->assertNotNull($decision);
        $this->assertSame(DecisionClientTerme::PLAFOND, $decision->type);

        $this->valider($valideur, $decision);

        $this->assertSame($ancien + 1000000, (float) $client->fresh()->plafond_credit);
        $this->assertSame(60, (int) $client->fresh()->delai_paiement);
    }

    public function test_la_decision_garde_l_etat_d_avant(): void
    {
        // Sans l ancien etat fige a la saisie, on ne pourrait plus dire six mois
        // apres ce que la decision avait change.
        [$auteur] = $this->deuxAdmins();

        $client = Client::where('client_a_terme', 1)->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client a terme en base.');
        }

        $ancienPlafond = (float) $client->plafond_credit;
        $ancienDelai   = (int) $client->delai_paiement;

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-a-terme-' . $client->id . '/plafond', [
            'plafond_credit' => $ancienPlafond + 500000,
            'delai_paiement' => $ancienDelai + 5,
        ]);

        $decision = $this->derniereDecision($client->id);

        $this->assertSame($ancienPlafond, (float) $decision->ancien_plafond);
        $this->assertSame($ancienDelai, (int) $decision->ancien_delai);
        $this->assertStringContainsString('→', $decision->resume());
    }

    // -------------------------------------------------------------- L'ÉCRAN

    public function test_l_ecran_liste_les_decisions_en_attente(): void
    {
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($auteur)->get('/liste-demande-client-a-terme');

        $reponse->assertOk();
        $reponse->assertSee('seconde validation', false);
        $reponse->assertSee('Passage en client', false);
    }

    public function test_l_auteur_ne_voit_pas_le_bouton_valider(): void
    {
        // Le serveur refuse deja ; proposer le bouton ferait decouvrir
        // l interdiction au clic.
        Mail::fake();

        [$auteur] = $this->deuxAdmins();
        [$client, $demande] = $this->uneDemande();

        $this->approuver($auteur, $demande);

        $decision = $this->derniereDecision($client->id);

        $this->assertFalse($decision->peutEtreValidePar($auteur));
        $this->assertTrue($decision->peutEtreValidePar($this->deuxAdmins()[1]));
    }

    // ------------------------------------------- LE RETRAIT ET LE RETOUR

    private function unClientATerme(): Client
    {
        $client = Client::where('client_a_terme', 1)->first() ?? Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $client->update(['client_a_terme' => 1, 'plafond_credit' => 4000000, 'delai_paiement' => 30]);

        return $client->fresh();
    }

    public function test_le_retrait_attend_sa_seconde_validation(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $client = $this->unClientATerme();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-' . $client->id . '/retirer-statut-terme', [
            'motif' => 'Retards repetes',
        ]);

        $this->assertSame(1, (int) $client->fresh()->client_a_terme,
            'Le client garde son credit tant que le retrait attend.');

        $decision = $this->derniereDecision($client->id);

        $this->assertSame(DecisionClientTerme::DESACTIVATION, $decision->type);

        $this->valider($valideur, $decision);

        $this->assertSame(0, (int) $client->fresh()->client_a_terme);
    }

    public function test_le_retrait_conserve_le_plafond_pour_plus_tard(): void
    {
        // Repartir de zero obligerait a retrouver un montant que la decision
        // de retrait avait justement sous la main.
        [$auteur] = $this->deuxAdmins();
        $client = $this->unClientATerme();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-' . $client->id . '/retirer-statut-terme', []);

        $decision = $this->derniereDecision($client->id);

        $this->assertSame(4000000.0, round((float) $decision->plafond_credit, 2));
        $this->assertSame(30, (int) $decision->delai_paiement);
    }

    public function test_le_statut_se_rend_apres_un_retrait(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $client = $this->unClientATerme();

        URL::forceRootUrl('');

        // Retrait, valide.
        $this->actingAs($auteur)->post('/client-' . $client->id . '/retirer-statut-terme', []);
        $this->valider($valideur, $this->derniereDecision($client->id));

        $this->assertSame(0, (int) $client->fresh()->client_a_terme);

        // Puis retour, valide lui aussi.
        $this->actingAs($auteur)->post('/client-' . $client->id . '/rendre-statut-terme', []);

        $this->assertSame(0, (int) $client->fresh()->client_a_terme,
            'La reactivation attend elle aussi sa seconde validation.');

        $this->valider($valideur, $this->derniereDecision($client->id));

        $client = $client->fresh();

        $this->assertSame(1, (int) $client->client_a_terme);
        $this->assertSame(4000000.0, round((float) $client->plafond_credit, 2),
            'Le plafond d avant le retrait doit etre repris.');
    }

    public function test_on_ne_retire_pas_un_statut_absent(): void
    {
        [$auteur] = $this->deuxAdmins();
        [$client] = $this->uneDemande();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-' . $client->id . '/retirer-statut-terme', []);

        $this->assertNull(DecisionClientTerme::where('client_id', $client->id)
            ->where('type', DecisionClientTerme::DESACTIVATION)->first(),
            'Un client au comptant n a pas de statut a retirer.');
    }

    public function test_le_client_retire_reste_dans_la_liste(): void
    {
        // Sans cela, le bouton qui lui rend son statut deviendrait introuvable.
        [$auteur, $valideur] = $this->deuxAdmins();
        $client = $this->unClientATerme();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/client-' . $client->id . '/retirer-statut-terme', []);
        $this->valider($valideur, $this->derniereDecision($client->id));

        $reponse = $this->actingAs($auteur)->get('/list-client-a-terme');

        $reponse->assertOk();

        $ids = collect($reponse->viewData('clients'))->pluck('id')->all();

        $this->assertContains($client->id, $ids);
        $reponse->assertSee('Statut à terme retiré', false);
        $reponse->assertSee('Rendre le statut', false);
    }
}
