<?php

namespace Tests\Feature;

use App\Http\Controllers\JetonsApiComptableController;
use App\Models\Audit;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\JournalComptable;
use App\Models\User;
use App\Services\Comptabilite\Ecrivain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 4 : l'API (lot 121, 22/09/2026).
 *
 * Réservée à l'administrateur du site DALAKOUN : jeton Sanctum, deux
 * aptitudes (« comptabilite:lecture » toujours, « comptabilite:ecriture »
 * en plus pour le seul point d'écriture), chaque appel journalisé.
 */
class ApiComptableTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }
        $this->admin = $admin;
        DB::table('ecriture_comptable')->delete();
        DB::table('personal_access_tokens')->delete();
    }

    private function compte(string $numero): CompteComptable
    {
        return CompteComptable::firstOrCreate(['nature' => 'GENERAL', 'numero' => $numero], ['libelle' => 'Recette API ' . $numero, 'statut' => 1]);
    }

    private function uneEcriture(string $date, float $montant = 10000, array $anomalies = []): EcritureComptable
    {
        static $rang = 0;
        $rang++;
        $journal = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();

        return Ecrivain::enregistrer(null, 'API-' . $rang . '-' . substr((string) hrtime(true), -6), [
            'origine' => EcritureComptable::ORIGINE_FACTURE, 'source_type' => 'facture', 'source_id' => 900000 + $rang, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => $date, 'piece' => 'PA-' . $rang, 'reference_fne' => 'FNE-API-' . $rang,
            'libelle' => 'Vente API n° ' . $rang, 'service' => \Help::$COMMANDE,
        ], [
            ['rubrique' => 'CLIENT', 'compte' => $this->compte('411000'), 'tiers' => '411API', 'libelle' => 'Client API', 'montant' => $montant, 'sens' => 'D'],
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'libelle' => 'Vente API', 'montant' => $montant, 'sens' => 'C'],
        ], $anomalies);
    }

    // ------------------------------------------------------------------ jetons (web)

    public function test_seul_un_administrateur_gere_les_jetons(): void
    {
        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }
        $this->actingAs($gestionnaire)->get('/comptabilite/jetons-api')->assertStatus(403);
        $this->actingAs($gestionnaire)->post('/comptabilite/jetons-api', ['nom' => 'Intrus'])->assertStatus(403);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_un_administrateur_cree_lit_et_revoque_un_jeton(): void
    {
        $reponse = $this->actingAs($this->admin)->post('/comptabilite/jetons-api', ['nom' => 'Sage — comptable DALAKOUN']);
        $reponse->assertRedirect()->assertSessionHas('jetonEnClair');
        $jeton = PersonalAccessToken::first();
        $this->assertNotNull($jeton);
        $this->assertSame('Sage — comptable DALAKOUN', $jeton->name);
        $this->assertSame([JetonsApiComptableController::APTITUDE_LECTURE], $jeton->abilities);
        $this->assertSame($this->admin->id, (int) DB::table('personal_access_tokens')->value('cree_par_id'));

        $page = $this->actingAs($this->admin)->get('/comptabilite/jetons-api');
        $page->assertOk()->assertSee('Sage — comptable DALAKOUN')->assertSee(JetonsApiComptableController::APTITUDE_LECTURE);

        $this->actingAs($this->admin)->post('/comptabilite/jetons-api', ['nom' => 'Avec écriture', 'ecriture' => '1']);
        $avecEcriture = PersonalAccessToken::where('name', 'Avec écriture')->first();
        $this->assertContains(JetonsApiComptableController::APTITUDE_ECRITURE, $avecEcriture->abilities);

        $this->actingAs($this->admin)->delete('/comptabilite/jetons-api/' . $jeton->id)->assertRedirect();
        $this->assertNull(PersonalAccessToken::find($jeton->id));
        $this->assertNotNull(PersonalAccessToken::find($avecEcriture->id), 'Révoquer un jeton ne touche pas les autres.');
    }

    // ------------------------------------------------------------------ authentification

    public function test_l_api_refuse_sans_jeton_et_sans_droit_admin(): void
    {
        $this->getJson('/api/comptabilite/ecritures')->assertStatus(401);

        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->where('statut', \Help::$STATUT_ACTIF)->first();
        if ($gestionnaire) {
            Sanctum::actingAs($gestionnaire, [JetonsApiComptableController::APTITUDE_LECTURE]);
            $this->getJson('/api/comptabilite/ecritures')->assertStatus(403);
        }

        Sanctum::actingAs($this->admin, []);   // jeton sans la moindre aptitude
        $this->getJson('/api/comptabilite/ecritures')->assertStatus(403);
    }

    public function test_le_jeton_reel_fonctionne_de_bout_en_bout(): void
    {
        // Pas Sanctum::actingAs() ici : un vrai jeton, un vrai en-tête, pour prouver
        // que le garde 'sanctum' et le format « id|jeton » fonctionnent réellement.
        $jeton = $this->admin->createToken('Recette API', [JetonsApiComptableController::APTITUDE_LECTURE]);
        $this->uneEcriture('2026-09-10');

        $reponse = $this->withHeader('Authorization', 'Bearer ' . $jeton->plainTextToken)
            ->getJson('/api/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09');
        $reponse->assertOk()->assertJsonCount(1, 'donnees');

        // Le garde Sanctum mémorise l'utilisateur résolu pour la durée d'une requête réelle ;
        // en test, la même application sert plusieurs appels simulés — sans ce nettoyage,
        // le second appel verrait encore le premier jeton, quel que soit l'en-tête envoyé.
        \Illuminate\Support\Facades\Auth::forgetGuards();

        // Un jeton mal formé est refusé.
        $this->withHeader('Authorization', 'Bearer ' . $jeton->accessToken->id . '|un-faux-jeton')
            ->getJson('/api/comptabilite/ecritures')->assertStatus(401);

        \Illuminate\Support\Facades\Auth::forgetGuards();

        // Un jeton révoqué aussi.
        $jeton->accessToken->delete();
        $this->withHeader('Authorization', 'Bearer ' . $jeton->plainTextToken)
            ->getJson('/api/comptabilite/ecritures')->assertStatus(401);
    }

    // ------------------------------------------------------------------ lecture

    public function test_la_liste_est_paginee_et_filtrable(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE]);
        $septembre1 = $this->uneEcriture('2026-09-05', 10000);
        $this->uneEcriture('2026-09-10', 5000);
        $aout = $this->uneEcriture('2026-08-20', 3000);

        $reponse = $this->getJson('/api/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09&par_page=1');
        $reponse->assertOk()
            ->assertJsonCount(1, 'donnees')
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.par_page', 1)
            ->assertJsonPath('donnees.0.identifiant', $septembre1->identifiant)
            ->assertJsonPath('donnees.0.lignes.0.compte', '411000');

        $this->getJson('/api/comptabilite/ecritures?mode_periode=DATES&du=2026-08-01&au=2026-09-30')
            ->assertOk()->assertJsonCount(3, 'donnees');

        $this->getJson('/api/comptabilite/ecritures?mode_periode=MOIS&periode=2026-08')
            ->assertOk()->assertJsonPath('donnees.0.identifiant', $aout->identifiant);

        // Le mode « année » de l'écran vaut aussi pour l'API : août et
        // septembre sont dans la même année.
        $this->getJson('/api/comptabilite/ecritures?mode_periode=ANNEE&periode=2026')
            ->assertOk()->assertJsonCount(3, 'donnees')
            ->assertJsonPath('periode.du', '2026-01-01')
            ->assertJsonPath('periode.au', '2026-12-31');
    }

    public function test_l_accuse_de_reception_accepte_les_trois_modes_de_periode(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE, JetonsApiComptableController::APTITUDE_ECRITURE]);
        $ecriture = $this->uneEcriture('2026-07-15', 8000);

        // Ce que le site peut transmettre, l'API doit pouvoir l'accuser : le
        // mode « année » ne doit pas être refusé à la validation.
        $this->postJson('/api/comptabilite/deversements', ['mode_periode' => 'ANNEE', 'periode' => '2026', 'format' => 'CSV'])
            ->assertCreated()
            ->assertJsonPath('donnees.mode_periode', 'ANNEE');

        $this->assertSame(\App\Models\EcritureComptable::ETAT_EXPORTEE, $ecriture->fresh()->etat);
    }

    public function test_le_detail_se_lit_par_l_identifiant_stable(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE]);
        $ecriture = $this->uneEcriture('2026-09-10', 12500);

        $this->getJson('/api/comptabilite/ecritures/' . $ecriture->identifiant)
            ->assertOk()
            ->assertJsonPath('donnees.identifiant', $ecriture->identifiant)
            ->assertJsonPath('donnees.total_debit', 12500);

        $this->getJson('/api/comptabilite/ecritures/IDENTIFIANT-INCONNU')->assertStatus(404);
    }

    public function test_l_export_donne_csv_sage_et_json(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE]);
        $this->uneEcriture('2026-09-10', 8000);

        $csv = $this->get('/api/comptabilite/ecritures/export?mode_periode=MOIS&periode=2026-09&format=csv');
        $csv->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $csv->headers->get('content-type'));

        $sage = $this->get('/api/comptabilite/ecritures/export?mode_periode=MOIS&periode=2026-09&format=sage');
        $sage->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $sage->headers->get('content-type'));

        $this->getJson('/api/comptabilite/ecritures/export?mode_periode=MOIS&periode=2026-09&format=json')
            ->assertOk()->assertJsonCount(1, 'donnees');
    }

    // ------------------------------------------------------------------ écriture (accusé de réception)

    public function test_un_jeton_lecture_seule_ne_peut_pas_accuser_reception(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE]);
        $this->uneEcriture('2026-09-10');

        $this->postJson('/api/comptabilite/deversements', ['mode_periode' => 'MOIS', 'periode' => '2026-09'])
            ->assertStatus(403);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, EcritureComptable::first()->etat);
    }

    public function test_l_accuse_de_reception_marque_les_ecritures_exportees(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE, JetonsApiComptableController::APTITUDE_ECRITURE]);
        $une = $this->uneEcriture('2026-09-10', 10000);
        $deux = $this->uneEcriture('2026-09-15', 6000);

        $reponse = $this->postJson('/api/comptabilite/deversements', ['mode_periode' => 'MOIS', 'periode' => '2026-09', 'format' => 'json']);
        $reponse->assertCreated()
            ->assertJsonPath('donnees.nombre_ecritures', 2)
            ->assertJsonPath('donnees.etat', \App\Models\DeversementComptable::ACCUSE_RECU)
            ->assertJsonCount(2, 'donnees.ecritures');

        foreach ([$une, $deux] as $ecriture) {
            $ecriture->refresh();
            $this->assertSame(EcritureComptable::ETAT_EXPORTEE, $ecriture->etat);
            $this->assertNotNull($ecriture->exportee_le);
        }

        $deversement = \App\Models\DeversementComptable::first();
        $this->assertSame(\App\Models\DeversementComptable::ACCUSE_RECU, $deversement->etat);
        $this->assertNotNull($deversement->accuse_le);

        // Identifiant stable, pas de doublon : un second appel sur la même période ne trouve plus rien à envoyer.
        $this->postJson('/api/comptabilite/deversements', ['mode_periode' => 'MOIS', 'periode' => '2026-09'])
            ->assertStatus(422);
        $this->assertSame(1, \App\Models\DeversementComptable::count());
    }

    public function test_une_periode_en_anomalie_refuse_l_accuse_de_reception(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE, JetonsApiComptableController::APTITUDE_ECRITURE]);
        $this->uneEcriture('2026-09-10', 10000);
        $mauvaise = $this->uneEcriture('2026-09-12', 4000, [[
            'code' => 'PRODUIT_SANS_FAMILLE', 'objet' => 'x', 'colonne' => 'y', 'cause' => 'z', 'onglet' => null, 'rang' => 1,
        ]]);

        $reponse = $this->postJson('/api/comptabilite/deversements', ['mode_periode' => 'MOIS', 'periode' => '2026-09']);
        $reponse->assertStatus(422);
        $this->assertStringContainsString('anomalie', $reponse->json('message'));
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $mauvaise->fresh()->etat);
        $this->assertSame(0, \App\Models\DeversementComptable::count());
    }

    // ------------------------------------------------------------------ journal des appels

    public function test_chaque_appel_est_journalise(): void
    {
        Sanctum::actingAs($this->admin, [JetonsApiComptableController::APTITUDE_LECTURE]);
        $avant = Audit::count();

        $this->getJson('/api/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09');

        $this->assertSame($avant + 1, Audit::count());
        $trace = Audit::orderByDesc('id')->first();
        $this->assertStringContainsString('Appel API comptable', $trace->action);
        $this->assertStringContainsString('GET', $trace->action);
        $this->assertSame('GET', $trace->methode);
        $this->assertSame($this->admin->id, (int) $trace->user_id);
        $this->assertSame(200, $trace->donnees['code_reponse'] ?? null);
    }

    public function test_un_appel_sans_jeton_ne_journalise_rien_et_ne_casse_rien(): void
    {
        $avant = Audit::count();
        // Le journal se pose APRÈS le contrôle du jeton : un appel refusé avant authentification
        // n'a pas de jeton à tracer. La requête doit malgré tout répondre proprement, jamais planter.
        $this->getJson('/api/comptabilite/ecritures')->assertStatus(401);
        $this->assertSame($avant, Audit::count());
    }
}
