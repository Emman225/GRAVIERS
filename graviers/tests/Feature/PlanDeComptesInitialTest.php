<?php

namespace Tests\Feature;

use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\JournalComptable;
use App\Models\RubriqueComptable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 6 : le paramétrage de départ
 * (lot 123, 22/09/2026). Une proposition, jamais une décision : elle ne crée
 * que ce qui manque et ne remplace jamais une saisie du comptable.
 */
class PlanDeComptesInitialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('compte_comptable')->delete();
        DB::table('rubrique_comptable')->delete();
        DB::table('journal_comptable')->update(['compte_comptable_id' => null]);
        DB::table('configuration')->update(['longueur_compte_comptable' => 6, 'journal_cautions_id' => null]);
    }

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $this->artisan('comptabilite:plan-de-comptes-initial', ['--simulation' => true])
            ->expectsOutputToContain('SIMULATION')
            ->assertExitCode(0);

        $this->assertSame(0, CompteComptable::count());
        $this->assertNull(JournalComptable::where('type', 'CAISSE')->value('compte_comptable_id'));
    }

    public function test_elle_pose_les_comptes_les_rubriques_et_les_journaux(): void
    {
        $this->artisan('comptabilite:plan-de-comptes-initial')->assertExitCode(0);

        // Les racines SYSCOHADA, complétées à la longueur des réglages.
        foreach (['411000', '401100', '443100', '447100', '706100', '709000', '419100', '165000', '571100', '521100', '552100'] as $numero) {
            $this->assertTrue(CompteComptable::generaux()->where('numero', $numero)->exists(), "Compte {$numero} absent.");
        }

        // Chaque rubrique de facture a son compte.
        foreach (RubriqueComptable::toutes() as $rubrique) {
            $this->assertNotNull($rubrique->compte_comptable_id, 'Rubrique sans compte : ' . $rubrique->code);
        }
        $this->assertSame('411000', RubriqueComptable::pour(RubriqueComptable::CLIENTS)->compte->numero);
        $this->assertSame('612000', RubriqueComptable::pour(RubriqueComptable::CHARGES_LIVREURS)->compte->numero);

        // Les journaux de trésorerie ont leur compte, le journal des ventes n'en a pas.
        $this->assertSame('571100', JournalComptable::where('type', 'CAISSE')->first()->compte->numero);
        $this->assertSame('521100', JournalComptable::where('type', 'BANQUE')->first()->compte->numero);
        $this->assertSame('552100', JournalComptable::where('type', 'MOBILE_MONEY')->first()->compte->numero);
        $this->assertNull(JournalComptable::where('type', 'VENTES')->first()->compte_comptable_id);

        // Le journal des cautions est réglé sur la caisse.
        $this->assertSame(JournalComptable::where('type', 'CAISSE')->value('id'), (int) Configuration::first()->journal_cautions_id);
    }

    public function test_elle_ne_remplace_jamais_une_saisie_du_comptable(): void
    {
        // Le comptable a déjà posé SON compte clients et SON journal de caisse.
        $sien = CompteComptable::create(['nature' => 'GENERAL', 'numero' => '411000', 'libelle' => 'Clients — libellé du comptable', 'statut' => 1]);
        $autre = CompteComptable::create(['nature' => 'GENERAL', 'numero' => '999999', 'libelle' => 'Compte maison', 'statut' => 1]);
        RubriqueComptable::pour(RubriqueComptable::TVA_COLLECTEE)->update(['compte_comptable_id' => $autre->id]);
        $caisse = JournalComptable::where('type', 'CAISSE')->first();
        $caisse->update(['compte_comptable_id' => $autre->id]);

        $this->artisan('comptabilite:plan-de-comptes-initial')->assertExitCode(0);

        $this->assertSame('Clients — libellé du comptable', $sien->fresh()->libelle, 'Un compte existant garde son libellé.');
        $this->assertSame(1, CompteComptable::where('numero', '411000')->count(), 'Pas de doublon sur un numéro déjà pris.');
        $this->assertSame($autre->id, (int) RubriqueComptable::pour(RubriqueComptable::TVA_COLLECTEE)->compte_comptable_id, 'Une rubrique déjà réglée ne change pas.');
        $this->assertSame($autre->id, (int) $caisse->fresh()->compte_comptable_id, 'Un journal déjà réglé ne change pas.');
    }

    public function test_rejouee_elle_ne_fait_rien_de_plus(): void
    {
        $this->artisan('comptabilite:plan-de-comptes-initial')->assertExitCode(0);
        $comptes = CompteComptable::count();
        $rubriques = RubriqueComptable::whereNotNull('compte_comptable_id')->count();

        $this->artisan('comptabilite:plan-de-comptes-initial')
            ->expectsOutputToContain('0 compte(s) général(aux) créé(s)')
            ->assertExitCode(0);

        $this->assertSame($comptes, CompteComptable::count());
        $this->assertSame($rubriques, RubriqueComptable::whereNotNull('compte_comptable_id')->count());
    }

    public function test_elle_suit_la_longueur_choisie_dans_les_reglages(): void
    {
        DB::table('configuration')->update(['longueur_compte_comptable' => 8]);

        $this->artisan('comptabilite:plan-de-comptes-initial')->assertExitCode(0);

        // Ces trois-là figurent tels quels dans la balance Sage du comptable.
        $this->assertTrue(CompteComptable::generaux()->where('numero', '41100000')->exists());
        $this->assertTrue(CompteComptable::generaux()->where('numero', '40110000')->exists());
        $this->assertTrue(CompteComptable::generaux()->where('numero', '52110000')->exists());
        $this->assertTrue(CompteComptable::generaux()->where('numero', '57110000')->exists());
        $this->assertTrue(CompteComptable::generaux()->where('numero', '44310000')->exists());
        $this->assertFalse(CompteComptable::generaux()->where('numero', '411000')->exists());
    }

    public function test_le_parametrage_reste_incomplet_tant_que_le_catalogue_n_est_pas_regle(): void
    {
        $this->artisan('comptabilite:plan-de-comptes-initial')->assertExitCode(0);

        // La commande ne touche ni aux familles, ni aux produits, ni aux tiers : le contrôle doit le dire.
        $categories = collect(\App\Services\Comptabilite\ParametrageComptable::anomalies())->pluck('categorie')->unique();
        $this->assertTrue($categories->contains('Grande famille') || $categories->contains('Produit') || $categories->contains('Client'),
            'Le contrôle doit encore signaler le catalogue et les tiers.');
        $this->assertFalse($categories->contains('Rubrique de facture'), 'Les rubriques, elles, sont réglées.');
    }
}
