<?php

namespace Tests\Feature;

use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Facture;
use App\Models\JournalComptable;
use App\Models\User;
use App\Services\Comptabilite\Ecrivain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 5 : le rapprochement (lot 122,
 * 22/09/2026) — « total des écritures = total des factures normalisées »,
 * sur une période réelle, avant la mise en production.
 */
class RapprochementComptableTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('ecriture_comptable')->delete();
    }

    private function compte(string $numero): CompteComptable
    {
        return CompteComptable::firstOrCreate(['nature' => 'GENERAL', 'numero' => $numero], ['libelle' => 'Recette rapprochement ' . $numero, 'statut' => 1]);
    }

    /** Une facture certifiée, insérée directement (sans passer par le modèle, donc sans écriture automatique). */
    private function uneFactureSansEcriture(float $montant, string $date = '2026-09-12'): Facture
    {
        static $rang = 0;
        $rang++;
        $id = DB::table('facture')->insertGetId([
            'numero' => 'RAP-' . $rang . '-' . substr((string) hrtime(true), -6), 'user_id' => User::value('id'), 'montant' => $montant,
            'statut' => 2, 'fne_status' => 'certified', 'fne_certified_at' => $date . ' 10:00:00', 'fne_reference' => 'FNE-RAP-' . $rang,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return Facture::find($id);
    }

    private function ecriturePourLaFacture(Facture $facture, float $montant): EcritureComptable
    {
        $journal = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();

        return Ecrivain::enregistrer(null, 'RAPFAC-' . $facture->id, [
            'origine' => EcritureComptable::ORIGINE_FACTURE, 'source_type' => 'facture', 'source_id' => $facture->id, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => $facture->fne_certified_at->toDateString(), 'piece' => $facture->numero,
            'reference_fne' => $facture->fne_reference, 'libelle' => 'Vente rapprochement', 'service' => \Help::$COMMANDE,
        ], [
            ['rubrique' => 'CLIENT', 'compte' => $this->compte('411000'), 'tiers' => '411RAP', 'libelle' => 'Client', 'montant' => $montant, 'sens' => 'D'],
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'libelle' => 'Vente', 'montant' => $montant, 'sens' => 'C'],
        ], []);
    }

    public function test_aucune_facture_sur_la_periode_ne_signale_rien_de_faux(): void
    {
        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09'])
            ->expectsOutputToContain('Aucune facture normalisée certifiée sur cette période')
            ->assertExitCode(0);
    }

    public function test_un_rapprochement_conforme_reussit(): void
    {
        $facture = $this->uneFactureSansEcriture(11800);
        $this->ecriturePourLaFacture($facture, 11800);

        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09'])
            ->expectsOutputToContain('RAPPROCHEMENT CONFORME')
            ->assertExitCode(0);
    }

    public function test_une_facture_sans_ecriture_fait_echouer_le_rapprochement(): void
    {
        $this->uneFactureSansEcriture(9000);

        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09'])
            ->expectsOutputToContain('Sans écriture')
            ->expectsOutputToContain('RAPPROCHEMENT NON CONFORME')
            ->assertExitCode(1);
    }

    public function test_un_ecart_de_montant_fait_echouer_le_rapprochement(): void
    {
        $facture = $this->uneFactureSansEcriture(15000);
        $this->ecriturePourLaFacture($facture, 12000);   // écriture produite avec un montant différent

        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09'])
            ->expectsOutputToContain('Écarts de montant')
            ->assertExitCode(1);
    }

    public function test_du_au_et_mois_donnent_la_meme_periode(): void
    {
        $facture = $this->uneFactureSansEcriture(5000, '2026-09-20');
        $this->ecriturePourLaFacture($facture, 5000);

        $this->artisan('comptabilite:rapprocher', ['--du' => '2026-09-01', '--au' => '2026-09-30'])
            ->expectsOutputToContain('RAPPROCHEMENT CONFORME')->assertExitCode(0);
        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09'])
            ->expectsOutputToContain('RAPPROCHEMENT CONFORME')->assertExitCode(0);
        // Un mois qui ne couvre pas la facture ne la voit pas.
        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-08'])
            ->expectsOutputToContain('Aucune facture normalisée certifiée')->assertExitCode(0);
    }

    public function test_le_detail_peut_s_enregistrer_dans_un_fichier(): void
    {
        $facture = $this->uneFactureSansEcriture(7500);
        $this->ecriturePourLaFacture($facture, 7500);
        $chemin = storage_path('app/rapprochement-essai-' . uniqid() . '.txt');

        $this->artisan('comptabilite:rapprocher', ['--mois' => '2026-09', '--fichier' => $chemin])->assertExitCode(0);

        $this->assertFileExists($chemin);
        $this->assertStringContainsString('RAPPROCHEMENT', file_get_contents($chemin));
        @unlink($chemin);
    }
}
