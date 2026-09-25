<?php

namespace Tests\Feature;

use App\Models\AnomalieComptable;
use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\DeversementComptable;
use App\Models\EcritureComptable;
use App\Models\JournalComptable;
use App\Models\User;
use App\Services\Comptabilite\Deversement;
use App\Services\Comptabilite\Ecrivain;
use App\Services\Comptabilite\FormatSage;
use App\Services\Comptabilite\JournalDesEcritures;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 3 : journal, anomalies et
 * transmission (lot 119, 22/09/2026).
 *
 * La période se choisit par dates ou par mois, le dernier choix est mémorisé.
 * Une période qui porte une anomalie ne part pas. Une écriture transmise ne
 * change plus : elle est figée, et un lot rejeté la rend à l'état « à
 * transmettre ».
 */
class JournalEtTransmissionDesEcrituresTest extends TestCase
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
        DB::table('deversement_comptable')->delete();
        DB::table('configuration')->update(['periode_transmission_comptable' => null]);
    }

    // ------------------------------------------------------------------ montage

    private function compte(string $numero): CompteComptable
    {
        return CompteComptable::firstOrCreate(
            ['nature' => 'GENERAL', 'numero' => $numero],
            ['libelle' => 'Recette ' . $numero, 'statut' => 1]
        );
    }

    /** Une écriture équilibrée, à la date voulue. */
    private function uneEcriture(string $date, float $montant = 10000, array $entete = [], array $anomalies = []): EcritureComptable
    {
        static $rang = 0;
        $rang++;
        $journal = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();

        return Ecrivain::enregistrer(null, ($entete['identifiant'] ?? 'REC-' . $rang . '-' . substr((string) hrtime(true), -6)), array_merge([
            'origine'              => EcritureComptable::ORIGINE_FACTURE,
            'source_type'          => 'facture',
            'source_id'            => $rang,
            'version'              => 1,
            'journal_comptable_id' => $journal?->id,
            'journal_code'         => $journal?->code,
            'date_ecriture'        => $date,
            'piece'                => 'F-' . $rang,
            'reference_fne'        => 'FNE-' . $rang,
            'libelle'              => 'Facture de recette n° ' . $rang,
            'service'              => \Help::$COMMANDE,
        ], $entete), [
            ['rubrique' => 'CLIENT', 'compte' => $this->compte('411000'), 'tiers' => '411REC', 'libelle' => 'Client de recette', 'montant' => $montant, 'sens' => 'D'],
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'libelle' => 'Vente de recette', 'montant' => $montant, 'sens' => 'C'],
        ], $anomalies);
    }

    private function uneAnomalie(): array
    {
        return [[
            'code' => AnomalieComptable::PRODUIT_SANS_FAMILLE, 'objet' => 'Gravier de recette', 'colonne' => 'Grande famille',
            'cause' => 'Produit sans grande famille comptable.', 'onglet' => 'produits', 'rang' => 2,
        ]];
    }

    private function enAdmin()
    {
        return $this->actingAs($this->admin);
    }

    // ------------------------------------------------------------------ la période

    public function test_la_periode_se_choisit_par_mois_ou_par_dates_et_le_dernier_choix_est_memorise(): void
    {
        $parMois = JournalDesEcritures::periode(['mode_periode' => 'MOIS', 'periode' => '2026-07']);
        $this->assertSame('MOIS', $parMois['mode']);
        $this->assertSame('2026-07-01', $parMois['du']->toDateString());
        $this->assertSame('2026-07-31', $parMois['au']->toDateString(), 'Le mois va jusqu\'à son dernier jour.');

        $parDates = JournalDesEcritures::periode(['mode_periode' => 'DATES', 'du' => '2026-09-10', 'au' => '2026-09-20']);
        $this->assertSame('2026-09-10', $parDates['du']->toDateString());
        $this->assertSame('2026-09-20', $parDates['au']->toDateString());

        // Dates à l'envers : remises dans l'ordre plutôt que de rendre une période vide.
        $inverse = JournalDesEcritures::periode(['mode_periode' => 'DATES', 'du' => '2026-09-20', 'au' => '2026-09-10']);
        $this->assertSame('2026-09-10', $inverse['du']->toDateString());

        // Le mode mémorisé sert de défaut à la visite suivante.
        JournalDesEcritures::memoriserLeMode('DATES');
        $this->assertSame('DATES', Configuration::first()->periode_transmission_comptable);
        $this->assertSame('DATES', JournalDesEcritures::periode([])['mode']);
        JournalDesEcritures::memoriserLeMode('MOIS');
        $this->assertSame('MOIS', JournalDesEcritures::periode([])['mode']);
    }

    public function test_le_journal_montre_les_ecritures_de_la_periode_et_ses_filtres(): void
    {
        $septembre = $this->uneEcriture('2026-09-15', 10000);
        $aout      = $this->uneEcriture('2026-08-15', 7000);

        $page = $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09');
        $page->assertOk()->assertSee($septembre->piece)->assertDontSee($aout->piece);
        $page->assertSee('Journal des')->assertSee('À transmettre');

        $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=DATES&du=2026-08-01&au=2026-09-30')
            ->assertOk()->assertSee($septembre->piece)->assertSee($aout->piece);

        // Filtre par état : une écriture déjà transmise n'est plus « à transmettre ».
        $septembre->update(['etat' => EcritureComptable::ETAT_EXPORTEE, 'exportee_le' => now()]);
        $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09&etat=A_EXPORTER')
            ->assertOk()->assertDontSee($septembre->piece);
        $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09&etat=EXPORTEE')
            ->assertOk()->assertSee($septembre->piece);

        $resume = JournalDesEcritures::resume($septembre->date_ecriture, $septembre->date_ecriture);
        $this->assertSame(1, $resume[EcritureComptable::ETAT_EXPORTEE]['nombre']);
        $this->assertEqualsWithDelta(10000, $resume['total']['debit'], 0.001);
    }

    public function test_le_detail_d_une_ecriture_montre_ses_lignes(): void
    {
        $ecriture = $this->uneEcriture('2026-09-15', 12500);

        $this->enAdmin()->get('/comptabilite/ecritures/' . $ecriture->id)
            ->assertOk()
            ->assertSee($ecriture->identifiant)
            ->assertSee('411000')->assertSee('701100')->assertSee('411REC')
            ->assertSee('12 500')
            ->assertSee('équilibrée');
    }

    // ------------------------------------------------------------------ anomalies

    public function test_le_rapport_d_anomalies_dit_ou_corriger_et_garde_l_historique(): void
    {
        $ecriture = $this->uneEcriture('2026-09-15', 10000, [], $this->uneAnomalie());
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $ecriture->etat);

        $page = $this->enAdmin()->get('/comptabilite/ecritures/anomalies?mode_periode=MOIS&periode=2026-09');
        $page->assertOk()
            ->assertSee('Gravier de recette')
            ->assertSee('Produit sans grande famille comptable.')
            ->assertSee('Grande famille')
            ->assertSee($ecriture->identifiant)
            ->assertSee('onglet=produits', false);   // le lien mène droit au bon onglet du paramétrage

        // Corrigée : elle quitte les anomalies ouvertes, sans être effacée.
        $ecriture->anomalies()->update(['resolue_le' => now()]);
        $this->enAdmin()->get('/comptabilite/ecritures/anomalies?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertDontSee('Gravier de recette');
        $this->enAdmin()->get('/comptabilite/ecritures/anomalies?mode_periode=MOIS&periode=2026-09&etat=corrigees')
            ->assertOk()->assertSee('Gravier de recette')->assertSee('Corrigée');
    }

    // ------------------------------------------------------------------ transmission

    public function test_une_periode_en_anomalie_ne_part_pas(): void
    {
        $this->uneEcriture('2026-09-15', 10000);
        $this->uneEcriture('2026-09-16', 4000, [], $this->uneAnomalie());

        $periode = JournalDesEcritures::periode(['mode_periode' => 'MOIS', 'periode' => '2026-09']);
        $apercu = Deversement::apercu($periode['du'], $periode['au']);
        $this->assertFalse($apercu['possible']);
        $this->assertSame(1, $apercu['nombre'], 'Seule l\'écriture saine serait partie.');

        $resultat = Deversement::transmettre($periode['du'], $periode['au'], 'MOIS', 'CSV');
        $this->assertFalse($resultat['success']);
        $this->assertStringContainsString('anomalie', $resultat['message']);
        $this->assertSame(0, DeversementComptable::count(), 'Rien n\'a été enregistré.');
        $this->assertSame(0, EcritureComptable::where('etat', EcritureComptable::ETAT_EXPORTEE)->count());

        $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'CSV'])
            ->assertRedirect();
        $this->assertSame(0, DeversementComptable::count());
    }

    public function test_la_transmission_fige_les_ecritures_et_garde_la_preuve_de_l_envoi(): void
    {
        $une   = $this->uneEcriture('2026-09-15', 10000);
        $deux  = $this->uneEcriture('2026-09-20', 6000);
        $aout  = $this->uneEcriture('2026-08-15', 3000);

        $reponse = $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'CSV']);
        $reponse->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $reponse->headers->get('content-type'));
        $this->assertStringContainsString('ecritures-comptables-2026-09.csv', (string) $reponse->headers->get('content-disposition'));

        $deversement = DeversementComptable::first();
        $this->assertNotNull($deversement);
        $this->assertSame('DEV-' . now()->year . '-0001', $deversement->numero);
        $this->assertSame($this->admin->id, (int) $deversement->user_id);
        $this->assertSame('MOIS', $deversement->mode_periode);
        $this->assertSame(2, $deversement->nombre_ecritures);
        $this->assertSame(4, $deversement->nombre_lignes);
        $this->assertEqualsWithDelta(16000, $deversement->total_debit, 0.001);
        $this->assertEqualsWithDelta(16000, $deversement->total_credit, 0.001);
        $this->assertSame(DeversementComptable::TRANSMIS, $deversement->etat);

        foreach ([$une, $deux] as $ecriture) {
            $ecriture->refresh();
            $this->assertSame(EcritureComptable::ETAT_EXPORTEE, $ecriture->etat);
            $this->assertNotNull($ecriture->exportee_le);
            $this->assertSame($deversement->id, (int) $ecriture->deversement_id);
        }
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $aout->fresh()->etat, 'Hors période : pas touchée.');

        // Le mode de période est mémorisé, et une seconde transmission ne double rien.
        $this->assertSame('MOIS', Configuration::first()->periode_transmission_comptable);
        $second = Deversement::transmettre($deversement->du, $deversement->au, 'MOIS', 'CSV');
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('Aucune écriture', $second['message']);
        $this->assertSame(1, DeversementComptable::count());
    }

    public function test_un_deversement_rejete_rend_ses_ecritures_a_transmettre(): void
    {
        $ecriture = $this->uneEcriture('2026-09-15', 10000);
        $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'SAGE']);
        $deversement = DeversementComptable::first();
        $this->assertSame(EcritureComptable::ETAT_EXPORTEE, $ecriture->fresh()->etat);

        // Accusé de réception.
        $this->enAdmin()->post('/comptabilite/ecritures/deversement-' . $deversement->id . '/accuser')->assertRedirect();
        $this->assertSame(DeversementComptable::ACCUSE_RECU, $deversement->fresh()->etat);
        $this->assertNotNull($deversement->fresh()->accuse_le);

        // Rejet : le motif est obligatoire, et les écritures repartent.
        $this->enAdmin()->post('/comptabilite/ecritures/deversement-' . $deversement->id . '/rejeter', [])
            ->assertSessionHasErrors('motif_rejet');
        $this->enAdmin()->post('/comptabilite/ecritures/deversement-' . $deversement->id . '/rejeter', ['motif_rejet' => 'Compte 701100 inconnu dans Sage'])
            ->assertRedirect();

        $deversement->refresh();
        $this->assertSame(DeversementComptable::REJETE, $deversement->etat);
        $this->assertStringContainsString('701100', $deversement->motif_rejet);
        $ecriture->refresh();
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, 'Rejetée par le logiciel comptable : elle doit pouvoir repartir.');
        $this->assertNull($ecriture->exportee_le);
        $this->assertNull($ecriture->deversement_id);

        // Elle repart bien dans un nouveau lot.
        $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'CSV']);
        $this->assertSame(2, DeversementComptable::count());
        $this->assertSame('DEV-' . now()->year . '-0002', DeversementComptable::orderByDesc('id')->value('numero'));
    }

    // ------------------------------------------------------------------ les fichiers

    public function test_le_fichier_porte_une_ligne_par_ligne_d_ecriture(): void
    {
        $ecriture = $this->uneEcriture('2026-09-15', 10000);
        $ecritures = collect([$ecriture->fresh('lignes')]);

        $entetes = FormatSage::entetes();
        $this->assertSame(array_keys(FormatSage::COLONNES), $entetes);
        foreach (['Journal', 'Date', 'Compte général', 'Compte tiers', 'Libellé', 'Débit', 'Crédit', 'Code analytique'] as $colonne) {
            $this->assertContains($colonne, $entetes);
        }

        $lignes = FormatSage::lignes($ecritures);
        $this->assertCount(2, $lignes, 'Une ligne de fichier par ligne d\'écriture.');
        $this->assertSame(count($entetes), count($lignes[0]));
        $premiere = array_combine($entetes, $lignes[0]);
        $this->assertSame('15/09/2026', $premiere['Date']);
        $this->assertSame('411000', $premiere['Compte général']);
        $this->assertSame('411REC', $premiere['Compte tiers']);
        $this->assertSame(10000.0, $premiere['Débit'], 'Les montants partent en nombres, pas en texte.');
        $this->assertSame(0.0, $premiere['Crédit']);
        $this->assertSame($ecriture->identifiant, $premiere['Pièce interne']);

        $csv = FormatSage::csv($ecritures);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Le BOM : Excel ouvre le fichier sans rien demander.');
        $this->assertStringContainsString('Compte général', $csv);
        $this->assertStringContainsString('10000,00', $csv, 'Virgule décimale, comme l\'attend un tableur français.');

        $json = FormatSage::json($ecritures);
        $this->assertSame($ecriture->identifiant, $json[0]['identifiant']);
        $this->assertCount(2, $json[0]['lignes']);
        $this->assertSame(10000.0, $json[0]['total_debit']);
    }

    public function test_le_telechargement_de_controle_ne_change_rien(): void
    {
        $ecriture = $this->uneEcriture('2026-09-15', 10000);

        $reponse = $this->enAdmin()->get('/comptabilite/ecritures/telecharger/csv?mode_periode=MOIS&periode=2026-09');
        $reponse->assertOk();
        $this->assertStringContainsString('controle-', (string) $reponse->headers->get('content-disposition'));

        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->fresh()->etat, 'Un contrôle ne transmet rien.');
        $this->assertSame(0, DeversementComptable::count());

        $reponse = $this->enAdmin()->get('/comptabilite/ecritures/telecharger/sage?mode_periode=MOIS&periode=2026-09');
        $reponse->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $reponse->headers->get('content-type'), 'Le format Sage est un classeur.');
    }

    public function test_le_fichier_d_un_deversement_se_retelecharge_a_l_identique(): void
    {
        $this->uneEcriture('2026-09-15', 10000);
        $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'CSV']);
        $deversement = DeversementComptable::first();

        $this->enAdmin()->get('/comptabilite/ecritures/deversement-' . $deversement->id . '/fichier/csv')->assertOk();
        $this->enAdmin()->get('/comptabilite/ecritures/deversement-' . $deversement->id . '/fichier/json')
            ->assertOk()->assertHeader('content-type', 'application/json');

        // Rejeté, il n'a plus d'écriture à rendre — et le dit.
        Deversement::rejeter($deversement, 'Refus de recette');
        $this->enAdmin()->get('/comptabilite/ecritures/deversement-' . $deversement->id . '/fichier/csv')
            ->assertRedirect();
    }

    // ------------------------------------------------------------------ accès

    public function test_le_suivi_des_deversements_dit_les_journaux_les_factures_et_leur_liste(): void
    {
        $ventes = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();
        $caisse = JournalComptable::where('type', JournalComptable::TYPE_CAISSE)->first();

        $this->uneEcriture('2026-09-15', 10000, ['piece' => 'F-D1', 'source_id' => 8001]);
        $this->uneEcriture('2026-09-16', 6000, ['piece' => 'F-D2', 'source_id' => 8002]);
        // Un encaissement : il est dans l'envoi, mais ce n'est pas une facture.
        $this->uneEcriture('2026-09-17', 2000, [
            'piece' => 'REC-D1', 'origine' => 'ENCAISSEMENT', 'source_type' => 'ligne_paiement', 'source_id' => 9001,
            'journal_comptable_id' => $caisse?->id, 'journal_code' => $caisse?->code,
        ]);

        $this->enAdmin()->post('/comptabilite/ecritures/transmettre?mode_periode=MOIS&periode=2026-09', ['format' => 'CSV'])->assertOk();
        $deversement = DeversementComptable::first();
        $this->assertNotNull($deversement);
        $this->assertSame(3, $deversement->nombre_ecritures);

        $resume = \App\Services\Comptabilite\RapportsComptables::resumeDesDeversements([$deversement->id]);
        $this->assertSame(2, $resume[$deversement->id]['factures'],
            'Deux factures : l\'encaissement est une écriture, pas une facture.');
        if ($ventes) {
            $this->assertStringContainsString($ventes->code, $resume[$deversement->id]['journaux']);
        }
        if ($caisse && $caisse->code !== $ventes?->code) {
            $this->assertStringContainsString($caisse->code, $resume[$deversement->id]['journaux'],
                'Un envoi couvre plusieurs journaux : la colonne les liste tous.');
        }

        $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('Journaux')->assertSee('Factures');

        // La liste des factures s'ouvre au clic, et porte bien les trois écritures.
        $this->enAdmin()->get('/comptabilite/ecritures/deversement-' . $deversement->id . '/factures')
            ->assertOk()
            ->assertSee($deversement->numero)
            ->assertSee('F-D1')->assertSee('F-D2')->assertSee('REC-D1');
    }

    public function test_le_journal_montre_le_mois_et_l_annee_de_chaque_ecriture(): void
    {
        $this->uneEcriture('2026-09-15');

        $this->enAdmin()->get('/comptabilite/ecritures?mode_periode=MOIS&periode=2026-09')
            ->assertOk()
            ->assertSee('N° facture / pièce')   // la colonne « Pièce » dit enfin ce qu'elle porte
            ->assertSee('Septembre')            // la colonne « Mois »
            ->assertSee('2026');                // la colonne « Année »
    }

    public function test_le_journal_est_reserve_aux_administrateurs(): void
    {
        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }
        $ecriture = $this->uneEcriture('2026-09-15', 10000);

        foreach (['/comptabilite/ecritures', '/comptabilite/ecritures/anomalies', '/comptabilite/ecritures/' . $ecriture->id,
                  '/comptabilite/ecritures/telecharger/csv'] as $adresse) {
            $this->actingAs($gestionnaire)->get($adresse)->assertStatus(403);
        }
        $this->actingAs($gestionnaire)->post('/comptabilite/ecritures/transmettre', ['format' => 'CSV'])->assertStatus(403);
        $this->assertSame(0, DeversementComptable::count());
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->fresh()->etat);
    }
}
