<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Agence;
use App\Models\Apporteur;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use App\Models\User;
use App\Services\Avances;
use App\Services\RecuDeReglement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LE REÇU DE PAIEMENT PART PAR COURRIEL (11/09/2026).
 *
 *  - à l'imputation d'une avance (vente, location, demande de livraison), le
 *    reçu de chaque règlement AV- va au client ;
 *  - à la FINALISATION d'un règlement de guichet — encaissement, créance d'un
 *    client à terme, dette d'un fournisseur, d'un livreur, d'un apporteur —,
 *    le reçu ou le bordereau va au client ou au partenaire ;
 *  - un seul envoi par règlement ; et JAMAIS un envoi qui échoue n'empêche
 *    l'opération (règle rappelée par le client le 11/09/2026).
 */
class RecuParCourrielApresReglementTest extends TestCase
{
    use DatabaseTransactions;

    /** @return User[] $n administrateurs actifs, distincts, rattachés à une agence. */
    private function admins(int $n): array
    {
        $admins = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->limit($n)->get();
        if ($admins->count() < $n) {
            $this->markTestSkipped("Il faut $n administrateurs actifs.");
        }
        $agence = Agence::value('id');
        foreach ($admins as $a) {
            if (!$a->agence_id && $agence) {
                $a->agence_id = $agence;
                $a->save();
            }
        }

        return $admins->all();
    }

    private function commandeDunClientJoignable(float $resteMinimum = 0): Commande
    {
        $commande = Commande::where('statut', '<>', 0)
            ->whereHas('client.user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))
            ->orderByDesc('id')->limit(300)->get()
            ->first(fn (Commande $c) => $c->client && $c->montantRestantDu() >= $resteMinimum);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande d\'un client joignable' . ($resteMinimum ? ' avec un reste dû' : '') . '.');
        }

        return $commande;
    }

    private function email(Client $client): string
    {
        return $client->user->email;
    }

    private function avance(Client $client, float $montant, User $admin): AvanceClient
    {
        return AvanceClient::create([
            'client_id' => $client->id, 'montant' => $montant, 'montant_consomme' => 0,
            'statut' => AvanceClient::DISPONIBLE, 'numero_recu' => 'RA-T-' . random_int(100, 999),
            'agence_id' => $admin->agence_id, 'caissier_id' => $admin->id,
            'user_valide_id' => $admin->id, 'user_valide2_id' => $admin->id,
            'date_depot' => now(), 'date_validation_1' => now(), 'date_validation_2' => now(),
        ]);
    }

    /** Un règlement de guichet validé deux fois, preuve jointe, prêt à finaliser. */
    private function reglementPretAFinaliser(array $attributs, User $a1, User $a2): array
    {
        return array_merge([
            'statut'            => 1,
            'user_valide_id'    => $a1->id,
            'user_valide2_id'   => $a2->id,
            'date_validation_1' => now(),
            'date_validation_2' => now(),
            'etat_reglement'    => DemandePaiement::PREUVE_JOINTE,
            'preuve_paiement'   => 'preuves_paiement/recette.pdf',
            'date_preuve'       => now(),
            'user_preuve_id'    => $a1->id,
            'agence_id'         => $a1->agence_id,
        ], $attributs);
    }

    // ------------------------------------------------------------------ avances

    public function test_l_imputation_d_une_avance_envoie_le_recu_au_client(): void
    {
        Mail::fake();
        [$admin]  = $this->admins(1);
        $commande = $this->commandeDunClientJoignable(1000);
        $client   = $commande->client;
        $this->avance($client, 1000, $admin);

        $resultat = Avances::imputerSurCommande($commande, $admin->id);

        $this->assertGreaterThanOrEqual(1, $resultat['impute'], 'L\'avance doit être imputée.');
        $email = $this->email($client);
        Mail::assertSent(DocumentPdfMail::class, function (DocumentPdfMail $m) use ($email) {
            return $m->typeDocument === 'Reçu de paiement'
                && str_starts_with($m->numero, 'AV-')
                && $m->emailClient === $email
                && str_starts_with($m->pdfContent, '%PDF');
        });
        $p = Paiement::where('numero_recu', $resultat['recus'][0])->first();
        $this->assertNotNull($p);
        $this->assertNotNull($p->recu_envoye_le, 'L\'envoi est daté sur le règlement AV-.');
    }

    public function test_un_envoi_qui_echoue_n_empeche_pas_l_imputation(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP injoignable (recette)'));
        [$admin]  = $this->admins(1);
        $commande = $this->commandeDunClientJoignable(1000);
        $this->avance($commande->client, 1000, $admin);

        $resultat = Avances::imputerSurCommande($commande, $admin->id);

        $this->assertGreaterThanOrEqual(1, $resultat['impute'], 'L\'imputation est acquise malgré l\'échec du courriel.');
        $p = Paiement::where('numero_recu', $resultat['recus'][0])->first();
        $this->assertNotNull($p, 'Le règlement AV- existe.');
        $this->assertSame(1, (int) $p->statut);
        $this->assertNull($p->recu_envoye_le, 'La date d\'envoi est effacée : un chemin suivant réessaiera.');
    }

    // ------------------------------------------------------------ encaissements

    public function test_la_finalisation_d_un_encaissement_envoie_le_recu_au_client(): void
    {
        Mail::fake();
        [$a1, $a2, $a3] = $this->admins(3);
        $commande = $this->commandeDunClientJoignable();

        $p = new Paiement();
        $p->forceFill($this->reglementPretAFinaliser([
            'client_id' => $commande->client_id, 'code' => 'TST' . random_int(1000, 9999),
            'libelle' => 'Recette', 'montant_total' => 1000, 'montant_restant' => 0,
            'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
            'caissier_id' => $a1->id, 'numero_recu' => 'RC-T-' . random_int(100, 999),
        ], $a1, $a2))->save();

        $this->actingAs($a3)->post(route('show.comptant.encaissements.effectuer', $p->id))
            ->assertRedirect()->assertSessionMissing('error');

        $p->refresh();
        $this->assertSame(DemandePaiement::EFFECTUEE, $p->etat_reglement);
        $this->assertNotNull($p->recu_envoye_le);
        $email = $this->email($commande->client);
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->typeDocument === 'Reçu de paiement'
            && $m->numero === $p->numero_recu && $m->emailClient === $email);
    }

    public function test_la_finalisation_d_une_creance_a_terme_envoie_le_recu_au_client(): void
    {
        Mail::fake();
        [$a1, $a2, $a3] = $this->admins(3);
        $commande = $this->commandeDunClientJoignable();

        $p = new Paiement();
        $p->forceFill($this->reglementPretAFinaliser([
            'client_id' => $commande->client_id, 'code' => 'TST' . random_int(1000, 9999),
            'libelle' => 'Règlement de créance (recette)', 'montant_total' => 2500, 'montant_restant' => 0,
            'caissier_id' => $a1->id, 'numero_recu' => 'RC-CT-T-' . random_int(100, 999),
        ], $a1, $a2))->save();

        $this->actingAs($a3)->post(route('show.creancesTerme.paiements.effectuer', $p->id))
            ->assertRedirect()->assertSessionMissing('error');

        $this->assertSame(DemandePaiement::EFFECTUEE, $p->fresh()->etat_reglement);
        $email = $this->email($commande->client);
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->typeDocument === 'Reçu de paiement'
            && $m->numero === $p->numero_recu && $m->emailClient === $email
            && str_starts_with($m->pdfContent, '%PDF'));
    }

    // ------------------------------------------------------------------- dettes

    private function fournisseurJoignable(): Fournisseur
    {
        $f = Fournisseur::whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))->first();
        if (!$f) {
            $this->markTestSkipped('Aucun fournisseur joignable.');
        }

        return $f;
    }

    public function test_la_finalisation_d_une_dette_fournisseur_envoie_le_bordereau(): void
    {
        Mail::fake();
        [$a1, $a2, $a3] = $this->admins(3);
        $f = $this->fournisseurJoignable();

        $p = new PaiementFournisseur();
        $p->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'fournisseur_id' => $f->id, 'enlevement_id' => (int) \App\Models\Enlevement::where('fournisseur_id', $f->id)->value('id'), 'montant' => 5000,
            'mode_paiement_id' => ModePaiement::value('id'), 'reference' => 'RECETTE', 'user_id' => $a1->id,
        ], $a1, $a2))->save();

        $this->actingAs($a3)->post(route('show.fournisseurs.paiements.effectuer', $p->id))
            ->assertRedirect()->assertSessionMissing('error');

        $this->assertSame(DemandePaiement::EFFECTUEE, $p->fresh()->etat_reglement);
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->typeDocument === 'Bordereau de paiement'
            && $m->emailClient === $f->user->email
            && str_starts_with($m->numero, 'PF-')
            && str_starts_with($m->pdfContent, '%PDF'));
        if (Schema::hasColumn('paiement_fournisseur', 'recu_envoye_le')) {
            $this->assertNotNull($p->fresh()->recu_envoye_le);
        }
    }

    public function test_un_envoi_qui_echoue_n_empeche_pas_la_finalisation(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP injoignable (recette)'));
        [$a1, $a2, $a3] = $this->admins(3);
        $f = $this->fournisseurJoignable();

        $p = new PaiementFournisseur();
        $p->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'fournisseur_id' => $f->id, 'enlevement_id' => (int) \App\Models\Enlevement::where('fournisseur_id', $f->id)->value('id'), 'montant' => 5000,
            'mode_paiement_id' => ModePaiement::value('id'), 'reference' => 'RECETTE', 'user_id' => $a1->id,
        ], $a1, $a2))->save();

        $this->actingAs($a3)->post(route('show.fournisseurs.paiements.effectuer', $p->id))
            ->assertRedirect()->assertSessionMissing('error');

        $this->assertSame(DemandePaiement::EFFECTUEE, $p->fresh()->etat_reglement, 'Le règlement est effectué malgré l\'échec du courriel.');
        if (Schema::hasColumn('paiement_fournisseur', 'recu_envoye_le')) {
            $this->assertNull($p->fresh()->recu_envoye_le, 'La date d\'envoi est effacée pour un nouvel essai.');
        }
    }

    public function test_le_bordereau_du_livreur_et_de_l_apporteur_partent_et_une_seule_fois(): void
    {
        Mail::fake();
        [$a1, $a2] = $this->admins(2);

        $livreur = Livreur::whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))->first();
        $apporteur = Apporteur::whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))->first();
        if (!$livreur || !$apporteur) {
            $this->markTestSkipped('Il faut un livreur et un apporteur joignables.');
        }

        $pl = new PaiementLivreur();
        $pl->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'livreur_id' => $livreur->id, 'livraison_id' => (int) \App\Models\Livraison::value('id'), 'montant' => 3000,
            'mode_paiement_id' => ModePaiement::value('id'), 'user_id' => $a1->id,
            'etat_reglement' => DemandePaiement::EFFECTUEE,
        ], $a1, $a2))->save();
        $pa = new PaiementApporteur();
        $pa->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'apporteur_id' => $apporteur->id, 'commission_id' => (int) \App\Models\CommissionApporteur::value('id'), 'montant' => 4000,
            'mode_paiement_id' => ModePaiement::value('id'), 'user_id' => $a1->id,
            'etat_reglement' => DemandePaiement::EFFECTUEE,
        ], $a1, $a2))->save();

        $this->assertTrue(RecuDeReglement::envoyerAuPartenaire($pl, 'livreur', true));
        $this->assertTrue(RecuDeReglement::envoyerAuPartenaire($pa, 'apporteur', true));
        if (Schema::hasColumn('paiement_livreur', 'recu_envoye_le')) {
            $this->assertFalse(RecuDeReglement::envoyerAuPartenaire($pl, 'livreur', true), 'Un seul envoi par règlement.');
            $this->assertFalse(RecuDeReglement::envoyerAuPartenaire($pa, 'apporteur', true));
        }

        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->emailClient === $livreur->user->email && str_starts_with($m->numero, 'PL-'));
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->emailClient === $apporteur->user->email && str_starts_with($m->numero, 'PA-'));
        Mail::assertSent(DocumentPdfMail::class, 2);
    }

    public function test_les_reglements_nes_d_une_demande_de_paiement_envoient_leur_bordereau(): void
    {
        Mail::fake();
        [$a1, $a2] = $this->admins(2);
        $f = $this->fournisseurJoignable();
        if (!Schema::hasColumn('paiement_fournisseur', 'demande_paiement_id')) {
            $this->markTestSkipped('Colonne demande_paiement_id absente.');
        }

        $demande = new DemandePaiement();
        $demande->id = 987654321;

        $p = new PaiementFournisseur();
        $p->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'fournisseur_id' => $f->id, 'enlevement_id' => (int) \App\Models\Enlevement::where('fournisseur_id', $f->id)->value('id'), 'montant' => 5000,
            'mode_paiement_id' => ModePaiement::value('id'), 'user_id' => $a1->id,
            'demande_paiement_id' => $demande->id, 'etat_reglement' => null, 'preuve_paiement' => null,
        ], $a1, $a2))->save();

        $this->assertSame(1, RecuDeReglement::envoyerPourLaDemande($demande, true));
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->emailClient === $f->user->email
            && $m->typeDocument === 'Bordereau de paiement');
    }

    public function test_un_partenaire_sans_courriel_ne_bloque_rien(): void
    {
        Mail::fake();
        [$a1, $a2] = $this->admins(2);
        $f = Fournisseur::whereDoesntHave('user', fn ($q) => $q->where('email', '<>', ''))
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))->first();
        if (!$f) {
            $this->markTestSkipped('Tous les fournisseurs ont un courriel.');
        }
        $p = new PaiementFournisseur();
        $p->forceFill($this->reglementPretAFinaliser([
            'date_paiement' => now()->toDateString(), 'fournisseur_id' => $f->id, 'enlevement_id' => (int) \App\Models\Enlevement::where('fournisseur_id', $f->id)->value('id'), 'montant' => 5000,
            'mode_paiement_id' => ModePaiement::value('id'), 'user_id' => $a1->id,
        ], $a1, $a2))->save();

        $this->assertFalse(RecuDeReglement::envoyerAuPartenaire($p, 'fournisseur', true));
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------ branchements

    public function test_les_points_d_envoi_sont_branches(): void
    {
        $trait = file_get_contents(app_path('Traits/PreuveDeReglementPartenaire.php'));
        $this->assertStringContainsString('RecuDeReglement::envoyerApresFinalisation($paiement, static::class)', $trait,
            'La finalisation de chaque guichet envoie le reçu.');

        $user = file_get_contents(app_path('Http/Controllers/UserController.php'));
        $this->assertStringContainsString('RecuDeReglement::envoyerPourLaDemande($demande)', $user,
            'La finalisation d\'une demande de paiement envoie les bordereaux.');

        $avances = file_get_contents(app_path('Services/Avances.php'));
        $this->assertStringContainsString('RecuPaiement::envoyerParCourriel($p)', $avances,
            'L\'imputation d\'une avance envoie le reçu de chaque règlement AV-.');
        $this->assertGreaterThan(strpos($avances, 'DB::transaction(function () use ($affaire, $service, $nom, $avances, $userId, &$resultat, &$crees)'),
            strpos($avances, 'RecuPaiement::envoyerParCourriel($p)'), 'L\'envoi vient APRÈS la transaction validée.');

        foreach (['CreanceClientTermeController', 'DetteFournisseurController', 'DetteLivreurController', 'DetteApporteurController'] as $c) {
            $this->assertTrue(method_exists('App\\Http\\Controllers\\' . $c, 'donneesDuRecu'), $c . ' expose les données de son reçu.');
        }
    }
}
