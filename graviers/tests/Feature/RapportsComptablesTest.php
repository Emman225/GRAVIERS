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

    private function analytique(string $numero): CompteComptable
    {
        return CompteComptable::firstOrCreate(['nature' => 'ANALYTIQUE', 'numero' => $numero], ['libelle' => 'Produit de recette', 'statut' => 1]);
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
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'analytique' => $this->analytique('ANA-RAP'),
             'categorie_id' => $famille->id, 'libelle' => 'Gravier de recette', 'montant' => $ht, 'sens' => 'C'],
            ['rubrique' => 'TVA', 'compte' => $this->compte('443100'), 'libelle' => 'TVA facturée', 'montant' => $tva, 'sens' => 'C'],
        ], $anomalies);
    }

    /** Un règlement client, tel que le produit le moteur de trésorerie. */
    private function unReglement(string $date, float $montant, ?int $lignePaiementId = null, array $entete = []): EcritureComptable
    {
        static $rang = 0;
        $rang++;
        $journal = JournalComptable::where('type', JournalComptable::TYPE_CAISSE)->first()
            ?: JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();

        return Ecrivain::enregistrer(null, 'REG-' . $rang . '-' . substr((string) hrtime(true), -6), array_merge([
            'origine' => 'ENCAISSEMENT',
            'source_type' => $lignePaiementId ? 'ligne_paiement' : null,
            'source_id' => $lignePaiementId,
            'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => $date, 'piece' => 'REC-' . $rang,
            'libelle' => 'Règlement de recette n° ' . $rang, 'service' => \Help::$COMMANDE,
        ], $entete), [
            ['rubrique' => 'TRESORERIE', 'compte' => $this->compte('571100'), 'libelle' => 'Encaissement', 'montant' => $montant, 'sens' => 'D'],
            ['rubrique' => 'CLIENT', 'compte' => $this->compte('411000'), 'tiers' => '411RAP', 'libelle' => 'Client de recette', 'montant' => $montant, 'sens' => 'C'],
        ], []);
    }

    /** Une vraie ligne de paiement : c'est elle qui porte le moyen et la facture nommée. */
    private function uneLigneDePaiement(\App\Models\ModePaiement $mode, ?int $factureId = null): int
    {
        $client = \App\Models\Client::first();
        $paiement = DB::table('paiement')->insertGetId([
            'client_id' => $client?->id, 'code' => 'RAP' . substr((string) hrtime(true), -8),
            'libelle' => 'Règlement de recette', 'facture_id' => $factureId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('ligne_paiement')->insertGetId([
            'paiement_id' => $paiement, 'mode_paiement_id' => $mode->id, 'moyen_paiement' => $mode->libelle,
            'montant' => 0, 'statut' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
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

    public function test_le_grand_livre_se_decline_par_tiers_et_par_analytique(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800);

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');

        // Le choix de compte n'est pas le même d'une déclinaison à l'autre :
        // c'est ce qui distingue vraiment les trois grands livres.
        $generaux = RapportsComptables::comptesMouvementes($du, $au, 'generale')->pluck('numero');
        $tiers = RapportsComptables::comptesMouvementes($du, $au, 'tiers')->pluck('numero');
        $analytiques = RapportsComptables::comptesMouvementes($du, $au, 'analytique')->pluck('numero');

        $this->assertTrue($generaux->contains('411000'));
        $this->assertSame(['411RAP'], $tiers->all(), 'Le grand livre des tiers ne propose que des comptes tiers.');
        $this->assertSame(['ANA-RAP'], $analytiques->all(), 'Le grand livre analytique ne propose que des comptes analytiques.');
        $this->assertFalse($tiers->contains('411000'), 'Un compte général n\'a rien à faire dans la liste des tiers.');

        // Et chaque livre ne retient que les lignes portant SA clé.
        $livreTiers = RapportsComptables::grandLivre('411RAP', $du, $au, 'tiers');
        $this->assertCount(1, $livreTiers['mouvements']);
        $this->assertEqualsWithDelta(11800, $livreTiers['cloture'], 0.001);

        $livreAnalytique = RapportsComptables::grandLivre('ANA-RAP', $du, $au, 'analytique');
        $this->assertCount(1, $livreAnalytique['mouvements']);
        $this->assertEqualsWithDelta(-10000, $livreAnalytique['cloture'], 0.001, 'Une vente est au crédit : le solde est créditeur.');
        $this->assertEqualsWithDelta(10000, $livreAnalytique['cloture_credit'], 0.001);
        $this->assertSame(0.0, $livreAnalytique['cloture_debit']);

        foreach (['generale' => '411000', 'tiers' => '411RAP', 'analytique' => 'ANA-RAP'] as $sorte => $cle) {
            $this->enAdmin()->get('/comptabilite/rapports/grand-livre?mode_periode=MOIS&periode=2026-09&sorte=' . $sorte)
                ->assertOk()->assertSee($cle);
        }
    }

    public function test_l_ouverture_et_le_solde_de_fin_sortent_en_debit_et_en_credit(): void
    {
        $this->uneVente('2026-08-20', 8000, 1440);   // avant la période : le report à nouveau
        $this->uneVente('2026-09-05', 10000, 1800);  // le mouvement de la période

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');

        // Le compte client est débiteur, le compte de ventes créditeur : chacun
        // doit tomber dans SA colonne, et l'autre rester à zéro.
        $soldes = collect(RapportsComptables::soldesDesComptes($du, $au));
        $client = $soldes->firstWhere('compte', '411000');
        $ventes = $soldes->firstWhere('compte', '701100');

        $this->assertEqualsWithDelta(9440, $client['ouverture_debit'], 0.001);
        $this->assertSame(0.0, $client['ouverture_credit']);
        $this->assertEqualsWithDelta(21240, $client['cloture_debit'], 0.001);
        $this->assertSame(0.0, $client['cloture_credit']);

        $this->assertEqualsWithDelta(8000, $ventes['ouverture_credit'], 0.001, 'Une vente ne s\'ouvre jamais au débit.');
        $this->assertSame(0.0, $ventes['ouverture_debit']);
        $this->assertEqualsWithDelta(18000, $ventes['cloture_credit'], 0.001);

        // Le report à nouveau de la balance s'équilibre comme le reste.
        $balance = collect(RapportsComptables::balance($du, $au, 'generale'));
        $this->assertEqualsWithDelta($balance->sum('ouverture_debit'), $balance->sum('ouverture_credit'), 0.001);

        $livre = RapportsComptables::grandLivre('411000', $du, $au);
        $this->assertEqualsWithDelta(9440, $livre['ouverture_debit'], 0.001);
        $this->assertSame(0.0, $livre['ouverture_credit']);
        $this->assertEqualsWithDelta(21240, $livre['cloture_debit'], 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/balance?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('Ouverture débit')->assertSee('Solde final crédit');
    }

    public function test_la_situation_des_clients_compte_cinq_tranches(): void
    {
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        foreach ([[10, 1000], [45, 2000], [75, 3000], [100, 4000], [150, 5000]] as [$jours, $montant]) {
            $this->uneVente($au->copy()->subDays($jours)->toDateString(), $montant, 0);
        }

        $ligne = collect(RapportsComptables::situationDesClients($au))->firstWhere('compte', '411RAP');
        $this->assertNotNull($ligne);

        $this->assertEqualsWithDelta(1000, $ligne['moins_30'], 0.001);
        $this->assertEqualsWithDelta(2000, $ligne['de_30_60'], 0.001);
        $this->assertEqualsWithDelta(3000, $ligne['de_60_90'], 0.001);
        $this->assertEqualsWithDelta(4000, $ligne['de_90_120'], 0.001, 'La tranche 90-120 ne doit plus retomber dans « plus de 90 ».');
        $this->assertEqualsWithDelta(5000, $ligne['plus_120'], 0.001);

        // L'invariant : les cinq tranches recouvrent exactement le dû, sans trou
        // ni double compte.
        $this->assertEqualsWithDelta(
            $ligne['non_lettre'],
            $ligne['moins_30'] + $ligne['de_30_60'] + $ligne['de_60_90'] + $ligne['de_90_120'] + $ligne['plus_120'],
            0.001
        );

        $this->enAdmin()->get('/comptabilite/rapports/clients?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('90 à 120 j')->assertSee('Plus de 120 j');
    }

    public function test_le_detail_par_facture_impute_les_reglements_et_nomme_les_moyens(): void
    {
        $modes = \App\Models\ModePaiement::orderBy('id')->take(2)->get();
        if ($modes->count() < 2) {
            $this->markTestSkipped('Il faut deux modes de règlement.');
        }
        [$premier, $second] = [$modes[0], $modes[1]];

        $this->uneVente('2026-09-01', 10000, 0, ['piece' => 'FAC-A', 'source_id' => 776]);
        $this->uneVente('2026-09-10', 5000, 0, ['piece' => 'FAC-B', 'source_id' => 777]);

        // Le premier règlement NOMME la facture B : il doit y aller, alors même
        // que la facture A est plus ancienne et encore due.
        $this->unReglement('2026-09-11', 2000, $this->uneLigneDePaiement($premier, 777));
        $this->unReglement('2026-09-12', 6000, $this->uneLigneDePaiement($second));
        $this->unReglement('2026-09-13', 4000, $this->uneLigneDePaiement($premier));

        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $detail = collect(RapportsComptables::facturesDesClients($au))->keyBy('numero');

        $this->assertEqualsWithDelta(2000, $detail['FAC-B']['regle'], 0.001, 'Le règlement qui nomme une facture y va.');
        $this->assertEqualsWithDelta(3000, $detail['FAC-B']['reste'], 0.001);
        $this->assertSame($premier->libelle, $detail['FAC-B']['moyen']);

        $this->assertEqualsWithDelta(10000, $detail['FAC-A']['regle'], 0.001, 'Les autres soldent la plus ancienne d\'abord.');
        $this->assertEqualsWithDelta(0, $detail['FAC-A']['reste'], 0.001);
        $this->assertSame($second->libelle . ', ' . $premier->libelle, $detail['FAC-A']['moyen'], 'Les deux moyens sont listés.');
        $this->assertSame('—', $detail['FAC-A']['tranche'], 'Une facture soldée n\'a plus d\'âge.');

        // L'invariant qui relie le détail au récapitulatif : ce qui reste dû,
        // facture par facture, est exactement le solde du client.
        $resume = collect(RapportsComptables::situationDesClients($au))->firstWhere('compte', '411RAP');
        $this->assertEqualsWithDelta($resume['solde'], $detail->sum('reste'), 0.001);
        $this->assertEqualsWithDelta($resume['facture'], $detail->sum('montant'), 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/clients?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('FAC-B')->assertSee('Moyen de paiement');
    }

    public function test_le_detail_des_taxes_retombe_sur_le_recapitulatif_mensuel(): void
    {
        $this->uneVente('2026-09-05', 10000, 1800, ['piece' => 'FAC-T1']);
        $this->uneVente('2026-09-20', 4000, 720, ['piece' => 'FAC-T2']);

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $recap = RapportsComptables::taxesCollectees($du, $au);
        $detail = RapportsComptables::detailDesTaxes($du, $au);

        $this->assertCount(2, $detail);
        $this->assertEqualsWithDelta(array_sum(array_column($recap, 'tva')), array_sum(array_column($detail, 'tva')), 0.001,
            'Le détail et le récapitulatif comptent la même TVA.');
        $this->assertEqualsWithDelta(array_sum(array_column($recap, 'airsi')), array_sum(array_column($detail, 'airsi')), 0.001);

        $ligne = collect($detail)->firstWhere('numero', 'FAC-T1');
        $this->assertEqualsWithDelta(10000, $ligne['ht'], 0.001);
        $this->assertEqualsWithDelta(1800, $ligne['tva'], 0.001);
        $this->assertEqualsWithDelta(11800, $ligne['ttc'], 0.001, 'Le TTC est ce que le client doit vraiment.');
        $this->assertEqualsWithDelta($ligne['ht'] + $ligne['tva'] + $ligne['airsi'], $ligne['ttc'], 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/taxes?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('FAC-T1')->assertSee('Montant TTC');
    }

    public function test_le_detail_de_tresorerie_nomme_le_beneficiaire_et_retombe_sur_le_recapitulatif(): void
    {
        $client = \App\Models\Client::first();
        $this->unReglement('2026-09-12', 6000, null, [
            'client_id' => $client?->id, 'tiers_type' => 'client', 'tiers_id' => $client?->id,
        ]);

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $recap = RapportsComptables::tresorerie($du, $au);
        $detail = RapportsComptables::detailDeTresorerie($du, $au);

        $this->assertCount(1, $detail);
        $this->assertEqualsWithDelta(array_sum(array_column($recap, 'entrees')), array_sum(array_column($detail, 'entree')), 0.001,
            'Le détail et le récapitulatif comptent les mêmes entrées.');
        $this->assertEqualsWithDelta(array_sum(array_column($recap, 'sorties')), array_sum(array_column($detail, 'sortie')), 0.001);

        $this->assertSame($client->display_name ?: 'Client n° ' . $client->id, $detail[0]['beneficiaire'],
            'Le bénéficiaire est nommé, pas laissé au compte tiers.');
        $this->assertSame('Encaissement', $detail[0]['nature']);
        $this->assertEqualsWithDelta(6000, $detail[0]['entree'], 0.001);

        $this->enAdmin()->get('/comptabilite/rapports/tresorerie?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('Bénéficiaire ou client');
    }

    public function test_le_cycle_par_famille_compte_le_servi_pas_le_demande(): void
    {
        $modele = \App\Models\Livraison::with('detailCommande.commande', 'enlevement')
            ->whereHas('enlevement')->whereHas('detailCommande.commande')->first();
        if (!$modele) {
            $this->markTestSkipped('Aucune livraison rattachée à un bon et à une commande.');
        }

        $commande = $modele->detailCommande->commande;
        $ligne = $modele->detailCommande;

        // Une seule commande dans la période, une seule ligne, un seul bon :
        // les autres sont poussées loin dans le passé.
        \App\Models\Commande::where('id', '!=', $commande->id)->update(['date_commande' => '2020-01-01']);
        $commande->update(['date_commande' => '2026-09-15', 'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT]);
        \App\Models\DetailCommande::where('commande_id', $commande->id)->where('id', '!=', $ligne->id)->delete();
        $ligne->update(['qte' => 10, 'prix' => 1000]);
        \App\Models\Livraison::where('detail_commande_id', $ligne->id)->where('id', '!=', $modele->id)->delete();
        $modele->enlevement->update(['qte' => 10, 'qte_servi' => 6, 'facture_id' => null]);

        $du = \Illuminate\Support\Carbon::parse('2026-09-01');
        $au = \Illuminate\Support\Carbon::parse('2026-09-30');
        $cycle = RapportsComptables::cycleParFamille($du, $au);
        $total = fn ($champ) => array_sum(array_column($cycle, $champ));

        $this->assertEqualsWithDelta(10000, $total('commande'), 0.001);
        $this->assertEqualsWithDelta(6000, $total('livre'), 0.001, 'Le livré compte ce qui est SERVI, pas ce qui est demandé.');
        $this->assertEqualsWithDelta(4000, $total('commande_non_livre'), 0.001);
        $this->assertEqualsWithDelta(0, $total('facture'), 0.001);
        $this->assertEqualsWithDelta(6000, $total('livre_non_facture'), 0.001, 'Sorti du dépôt, pas encore facturé.');

        // Les trois écarts sont des soustractions exactes, famille par famille.
        foreach ($cycle as $c) {
            $this->assertEqualsWithDelta($c['commande'], $c['livre'] + $c['commande_non_livre'], 0.001);
            $this->assertEqualsWithDelta($c['livre'], $c['facture'] + $c['livre_non_facture'], 0.001);
            $this->assertEqualsWithDelta($c['facture'], $c['deverse'] + $c['facture_non_deverse'], 0.001);
        }

        // Une commande annulée sort du compte.
        $commande->update(['etat_commande' => \Help::$AFFAIRE_ANNULEE]);
        $this->assertSame([], RapportsComptables::cycleParFamille($du, $au));

        $commande->update(['etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT]);
        $this->enAdmin()->get('/comptabilite/rapports/familles?mode_periode=MOIS&periode=2026-09')
            ->assertOk()->assertSee('Livré non facturé')->assertSee('Commandé non livré');
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
