<?php

namespace Tests\Feature;

use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Facture;
use App\Models\JournalComptable;
use App\Models\User;
use App\Services\Comptabilite\Ecrivain;
use App\Services\Comptabilite\RapportsComptables;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 3b : les rapports comptables
 * (lot 120, 22/09/2026). Tous en lecture seule : aucun n'écrit dans les
 * écritures, et les écritures EN ANOMALIE en sont exclues.
 */
class RapportsComptablesTest extends TestCase
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
    }

    private function compte(string $numero): CompteComptable
    {
        return CompteComptable::firstOrCreate(['nature' => 'GENERAL', 'numero' => $numero], ['libelle' => 'Recette ' . $numero, 'statut' => 1]);
    }

    private function uneVente(string $date, float $ht = 10000, float $tva = 1800, array $entete = [], array $anomalies = []): EcritureComptable
    {
        static $rang = 0;
        $rang++;
        $journal = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();
        $famille = \App\Models\Categorie::firstOrCreate(['nom' => 'Famille recette rapports'], ['description' => 'Recette', 'parent_id' => 0, 'statut' => 1]);

        return Ecrivain::enregistrer(null, ($entete['identifiant'] ?? 'RAP-' . $rang . '-' . substr((string) hrtime(true), -6)), array_merge([
            'origine' => EcritureComptable::ORIGINE_FACTURE, 'source_type' => 'facture', 'source_id' => $rang, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => $date, 'piece' => 'FR-' . $rang, 'reference_fne' => 'FNE-RAP-' . $rang,
            'libelle' => 'Vente de recette n° ' . $rang, 'service' => \Help::$COMMANDE, 'client_id' => null,
        ], $entete), [
            ['rubrique' => 'CLIENT', 'compte' => $this->compte('411000'), 'tiers' => '411RAP', 'libelle' => 'Client de recette', 'montant' => $ht + $tva, 'sens' => 'D'],
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'categorie_id' => $famille->id, 'libelle' => 'Gravier de recette', 'montant' => $ht, 'sens' => 'C'],
            ['rubrique' => 'TVA', 'compte' => $this->compte('443100'), 'libelle' => 'TVA facturée', 'montant' => $tva, 'sens' => 'C'],
        ], $anomalies);
    }

    private function enAdmin()
    {
        return $this->actingAs($this->admin);
    }

    public function test_la_page_d_accueil_liste_les_treize_rapports(): void
    {
        $page = $this->enAdmin()->get('/comptabilite/rapports')->assertOk();
        foreach (array_column(\App\Http\Controllers\RapportsComptablesController::RAPPORTS, 0) as $titre) {
            $page->assertSee($titre);
        }
    }

    public function test_les_ecritures_en_anomalie_sont_exclues_des_rapports(): void
    {
        $bonne = $this->uneVente('2026-09-10', 10000, 1800);
        $mauvaise = $this->uneVente('2026-09-11', 5000, 900, [], [[
            'code' => 'PRODUIT_SANS_FAMILLE', 'objet' => 'x', 'colonne' => 'y', 'cause' => 'z', 'onglet' => null, 'rang' => 1,
        ]]);
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $mauvaise->etat);

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $soldes = RapportsComptables::soldesDesComptes($du, $au);
        $client = collect($soldes)->firstWhere('compte', '411000');
        $this->assertEqualsWithDelta(11800, $client['debit'], 0.001, 'L\'écriture en anomalie ne doit pas entrer dans les totaux.');
    }

    public function test_soldes_des_comptes_ouverture_mouvements_cloture(): void
    {
        $this->uneVente('2026-08-20', 8000, 1440);   // avant la période : dans l'ouverture
        $this->uneVente('2026-09-05', 10000, 1800);  // dans la période

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $soldes = RapportsComptables::soldesDesComptes($du, $au);
        $client = collect($soldes)->firstWhere('compte', '411000');

        $this->assertEqualsWithDelta(9440, $client['ouverture'], 0.001);
        $this->assertEqualsWithDelta(11800, $client['debit'], 0.001, 'Seul le mouvement de septembre.');
        $this->assertEqualsWithDelta(9440 + 11800, $client['cloture'], 0.001);

        $page = $this->enAdmin()->get('/comptabilite/rapports/soldes?mode_periode=MOIS&periode=2026-09');
        $page->assertOk()->assertSee('411000');
    }

    public function test_grand_livre_donne_le_solde_progressif(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800);
        $this->uneVente('2026-09-10', 5000, 900);

        $livre = RapportsComptables::grandLivre('411000', \Illuminate\Support\Carbon::parse('2026-09-01'), \Illuminate\Support\Carbon::parse('2026-09-30'));
        $this->assertCount(2, $livre['mouvements']);
        $this->assertEqualsWithDelta(11800, $livre['mouvements'][0]['solde'], 0.001);
        $this->assertEqualsWithDelta(11800 + 5900, $livre['mouvements'][1]['solde'], 0.001);
        $this->assertEqualsWithDelta(11800 + 5900, $livre['cloture'], 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/grand-livre?mode_periode=MOIS&periode=2026-09&compte=411000')
            ->assertOk()->assertSee('411000')->assertSee('FR-');
    }

    public function test_balance_generale_et_par_tiers_s_equilibrent(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800);

        $generale = RapportsComptables::balance(\Illuminate\Support\Carbon::parse('2026-09-01'), \Illuminate\Support\Carbon::parse('2026-09-30'), 'generale');
        $totalDebit = array_sum(array_column($generale, 'debit'));
        $totalCredit = array_sum(array_column($generale, 'credit'));
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.001, 'Une balance générale s\'équilibre toujours.');

        $tiers = RapportsComptables::balance(\Illuminate\Support\Carbon::parse('2026-09-01'), \Illuminate\Support\Carbon::parse('2026-09-30'), 'tiers');
        $ligne = collect($tiers)->firstWhere('cle', '411RAP');
        $this->assertNotNull($ligne);
        $this->assertEqualsWithDelta(11800, $ligne['solde_debit'], 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/balance?mode_periode=MOIS&periode=2026-09&sorte=tiers')->assertOk()->assertSee('411RAP');
    }

    public function test_balance_consolidee_regroupe_par_classe_et_par_famille(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800);

        $consolidee = RapportsComptables::balanceConsolidee(\Illuminate\Support\Carbon::parse('2026-09-01'), \Illuminate\Support\Carbon::parse('2026-09-30'));
        $classe4 = collect($consolidee['classes'])->firstWhere('classe', '4');
        $this->assertNotNull($classe4);
        $this->assertSame('Tiers', $classe4['libelle']);
        $famille = collect($consolidee['familles'])->firstWhere('famille', 'Famille recette rapports');
        $this->assertNotNull($famille);
        $this->assertEqualsWithDelta(10000, $famille['montant'], 0.001);
    }

    public function test_le_rapprochement_fne_signale_une_facture_sans_ecriture(): void
    {
        // Une facture certifiée D'AVANT le module (insertion directe, sans passer par le
        // modèle) : le crochet Facture::booted() ne se déclenche pas, comme pour une
        // facture historique reprise après coup — c'est exactement le cas « ABSENTE ».
        $id = DB::table('facture')->insertGetId([
            'numero' => 'FR-RAP-' . uniqid(), 'user_id' => User::value('id'), 'montant' => 15000,
            'statut' => 2, 'fne_status' => 'certified', 'fne_certified_at' => '2026-09-12 10:00:00', 'fne_reference' => 'FNE-ORPHELINE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $facture = Facture::find($id);
        $this->assertSame(0, EcritureComptable::where('source_type', 'facture')->where('source_id', $id)->count());
        $lignes = RapportsComptables::rapprochementFne(\Illuminate\Support\Carbon::parse('2026-09-01'), \Illuminate\Support\Carbon::parse('2026-09-30'));
        $ligne = collect($lignes)->firstWhere('facture', $facture->numero);
        $this->assertNotNull($ligne);
        $this->assertSame('ABSENTE', $ligne['etat']);

        $page = $this->enAdmin()->get('/comptabilite/rapports/rapprochement?mode_periode=MOIS&periode=2026-09');
        $page->assertOk()->assertSee($facture->numero)->assertSee('Écriture absente');
    }

    public function test_les_treize_ecrans_repondent_pour_un_administrateur(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800);
        foreach ([
            'index', 'deversements', 'familles', 'soldes', 'grand-livre', 'balance', 'consolidee',
            'rapprochement', 'anomalies', 'ventes', 'taxes', 'clients', 'tresorerie', 'journal-des-ventes',
        ] as $chemin) {
            $adresse = '/comptabilite/rapports' . ($chemin === 'index' ? '' : '/' . $chemin) . '?mode_periode=MOIS&periode=2026-09';
            $this->enAdmin()->get($adresse)->assertOk();
        }
    }

    public function test_les_rapports_sont_reserves_aux_administrateurs(): void
    {
        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }
        $this->actingAs($gestionnaire)->get('/comptabilite/rapports')->assertStatus(403);
        $this->actingAs($gestionnaire)->get('/comptabilite/rapports/soldes')->assertStatus(403);
    }

    public function test_un_rapport_n_ecrit_jamais_dans_les_ecritures(): void
    {
        $ecriture = $this->uneVente('2026-09-05', 10000, 1800);
        $avant = $ecriture->fresh(['lignes'])->toArray();

        foreach (['soldes', 'balance', 'consolidee', 'grand-livre?compte=411000', 'ventes', 'taxes', 'clients', 'tresorerie', 'rapprochement', 'anomalies', 'familles', 'journal-des-ventes'] as $chemin) {
            $this->enAdmin()->get('/comptabilite/rapports/' . $chemin . (str_contains($chemin, '?') ? '&' : '?') . 'mode_periode=MOIS&periode=2026-09');
        }

        $this->assertSame($avant, $ecriture->fresh(['lignes'])->toArray(), 'Aucun rapport ne doit modifier une écriture.');
    }
}
