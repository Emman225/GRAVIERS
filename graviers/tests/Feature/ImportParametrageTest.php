<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\CompteComptable;
use App\Models\JournalComptable;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use App\Models\User;
use App\Services\Comptabilite\ImportParametrage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * L'import du paramétrage comptable (lot 129, 26/09/2026).
 *
 * Le responsable a jugé la saisie écran par écran trop longue : le paramétrage
 * se pose d'un classeur. Trois promesses sont vérifiées ici — l'aller-retour
 * ne change rien, l'import ne supprime jamais rien, et une ligne qu'on ne sait
 * pas rattacher est refusée SANS emporter le reste du fichier.
 */
class ImportParametrageTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private array $fichiers = [];

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }
        $this->admin = $admin;
    }

    protected function tearDown(): void
    {
        foreach ($this->fichiers as $fichier) {
            @unlink($fichier);
        }
        parent::tearDown();
    }

    /** Un classeur de circonstance : un tableau par feuille. */
    private function classeur(array $feuilles): string
    {
        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);
        foreach ($feuilles as $nom => $lignes) {
            $feuille = $classeur->createSheet();
            $feuille->setTitle($nom);
            foreach ($lignes as $i => $ligne) {
                foreach (array_values($ligne) as $j => $valeur) {
                    $feuille->setCellValueExplicit(
                        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1) . ($i + 1),
                        (string) $valeur,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }
            }
        }

        $chemin = storage_path('app/essai-import-' . substr((string) hrtime(true), -8) . '.xlsx');
        (new Xlsx($classeur))->save($chemin);
        $this->fichiers[] = $chemin;

        return $chemin;
    }

    private function enAdmin()
    {
        return $this->actingAs($this->admin);
    }

    // ------------------------------------------------------------------ les essais

    public function test_l_aller_retour_du_classeur_ne_change_rien(): void
    {
        // Le paramétrage tel qu'il est, exporté puis relu : il ne doit rien
        // proposer. C'est l'invariant qui rend le modèle utilisable comme
        // sauvegarde.
        $chemin = storage_path('app/essai-modele-' . substr((string) hrtime(true), -8) . '.xlsx');
        $this->fichiers[] = $chemin;
        (new \App\Exports\ModeleParametrageExport())->store(basename($chemin), 'local');

        $rapport = ImportParametrage::analyser($chemin);
        $resume = ImportParametrage::resume($rapport);

        $this->assertSame(0, $resume[ImportParametrage::CREATION], 'Un aller-retour ne crée rien.');
        $this->assertSame(0, $resume[ImportParametrage::CORRECTION], 'Un aller-retour ne corrige rien.');
        $this->assertSame(0, $resume[ImportParametrage::REFUS], 'Le module doit savoir relire son propre modèle.');
        $this->assertGreaterThan(0, $resume[ImportParametrage::INCHANGE], 'Le classeur n\'est pas vide.');
    }

    public function test_un_classeur_pose_le_parametrage_et_rejoue_ne_fait_rien_de_plus(): void
    {
        $famille = Categorie::where('statut', 1)->first();
        if (!$famille) {
            $this->markTestSkipped('Aucune catégorie active.');
        }
        $vt = JournalComptable::where('type', JournalComptable::TYPE_VENTES)->first();
        DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => null]);
        CompteComptable::withTrashed()->whereIn('numero', ['70990000', 'IMP-001'])->forceDelete();

        $chemin = $this->classeur([
            'Comptes' => [
                ['Numéro', 'Intitulé', 'Nature'],
                ['70990000', 'Ventes — import de recette', 'Général'],
                ['IMP-001', 'Analytique de recette', 'Analytique'],
            ],
            'Grandes familles' => [
                ['Grande famille', 'Compte général'],
                [$famille->nom, '70990000'],
            ],
            'Rubriques' => [
                ['Code', 'Rubrique', 'Compte général', 'Compte analytique'],
                [RubriqueComptable::TRANSPORT, 'Transport facturé', '70990000', ''],
            ],
        ]);

        // 1. L'analyse n'écrit rien.
        $analyse = ImportParametrage::analyser($chemin);
        $this->assertSame(2, ImportParametrage::resume($analyse)[ImportParametrage::CREATION]);
        $this->assertFalse(CompteComptable::where('numero', '70990000')->exists(), 'Analyser, c\'est lire.');

        // 2. L'application pose tout.
        ImportParametrage::appliquer($chemin);
        $compte = CompteComptable::generaux()->where('numero', '70990000')->first();
        $this->assertNotNull($compte);
        $this->assertSame('Ventes — import de recette', $compte->libelle);
        $this->assertTrue(CompteComptable::analytiques()->where('numero', 'IMP-001')->exists());
        $this->assertSame($compte->id, (int) Categorie::find($famille->id)->compte_comptable_id);
        $this->assertSame($compte->id, (int) RubriqueComptable::pour(RubriqueComptable::TRANSPORT)->compte_comptable_id);

        // 3. Rejoué, il ne fait plus rien : c'est ce qui permet de le relancer sans crainte.
        $second = ImportParametrage::resume(ImportParametrage::analyser($chemin));
        $this->assertSame(0, $second[ImportParametrage::CREATION]);
        $this->assertSame(0, $second[ImportParametrage::CORRECTION]);
        $this->assertSame(0, $second[ImportParametrage::REFUS]);

        // 4. Et le journal des ventes, que le classeur ne nommait pas, est intact.
        if ($vt) {
            $this->assertNotNull(JournalComptable::find($vt->id), 'L\'import ne supprime jamais rien.');
            $this->assertSame($vt->libelle, JournalComptable::find($vt->id)->libelle);
        }
    }

    public function test_une_ligne_refusee_n_emporte_pas_le_reste_du_classeur(): void
    {
        $produit = Produit::where('statut', 1)->whereNotNull('reference')->where('reference', '!=', '')->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit avec une référence.');
        }
        $famille = Categorie::where('statut', 1)->first();
        DB::table('produit')->where('id', $produit->id)->update(['categorie_comptable_id' => null]);

        $chemin = $this->classeur([
            'Produits' => [
                ['Référence', 'Produit', 'Grande famille', 'Compte analytique'],
                ['REFERENCE-QUI-N-EXISTE-PAS', 'Produit fantôme', $famille->nom, ''],
                [$produit->reference, $produit->nom, $famille->nom, ''],
                [$produit->reference, $produit->nom, 'Famille qui n\'existe pas', ''],
            ],
        ]);

        $rapport = ImportParametrage::appliquer($chemin);
        $lignes = $rapport['Produits']['lignes'];

        $this->assertCount(3, $lignes);
        $this->assertSame(ImportParametrage::REFUS, $lignes[0]['action'], 'Référence inconnue : refusée.');
        $this->assertStringContainsString('référence', mb_strtolower($lignes[0]['detail']));
        $this->assertNotSame(ImportParametrage::REFUS, $lignes[1]['action'], 'La ligne juste passe quand même.');
        $this->assertSame(ImportParametrage::REFUS, $lignes[2]['action'], 'Grande famille inconnue : refusée.');

        // Le produit valide a bien été réglé, malgré les deux refus autour de lui.
        $this->assertSame((int) $famille->id, (int) Produit::find($produit->id)->categorie_comptable_id);
    }

    public function test_une_feuille_absente_ne_touche_a_rien(): void
    {
        $famille = Categorie::where('statut', 1)->first();
        $compte = CompteComptable::firstOrCreate(
            ['nature' => 'GENERAL', 'numero' => '70980000'],
            ['libelle' => 'Compte de recette', 'statut' => 1]
        );
        DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => $compte->id]);

        // Un classeur qui ne porte QUE la feuille des comptes.
        $chemin = $this->classeur([
            'Comptes' => [['Numéro', 'Intitulé', 'Nature'], ['70980000', 'Compte de recette', 'Général']],
        ]);

        $rapport = ImportParametrage::appliquer($chemin);

        $this->assertTrue($rapport['Grandes familles']['absente'], 'La feuille manquante est signalée.');
        $this->assertSame($compte->id, (int) Categorie::find($famille->id)->compte_comptable_id,
            'Une feuille absente ne défait pas ce qui était réglé.');
    }

    public function test_l_ecran_d_import_analyse_puis_applique(): void
    {
        $famille = Categorie::where('statut', 1)->first();
        DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => null]);
        CompteComptable::withTrashed()->where('numero', '70970000')->forceDelete();

        $chemin = $this->classeur([
            'Comptes' => [['Numéro', 'Intitulé', 'Nature'], ['70970000', 'Ventes de recette', 'Général']],
            'Grandes familles' => [['Grande famille', 'Compte général'], [$famille->nom, '70970000']],
        ]);

        $envoi = new \Illuminate\Http\UploadedFile($chemin, 'parametrage.xlsx', null, null, true);
        $reponse = $this->enAdmin()->post('/comptabilite/parametrage/import/analyser', ['fichier' => $envoi]);
        $reponse->assertOk()->assertSee('70970000')->assertSee('Appliquer le paramétrage');
        $this->assertFalse(CompteComptable::where('numero', '70970000')->exists(), 'L\'écran d\'analyse n\'écrit rien.');

        $depose = $reponse->viewData('fichier');
        $this->assertStringStartsWith('imports-comptables/', $depose);

        $this->enAdmin()->post('/comptabilite/parametrage/import/appliquer', ['fichier' => $depose])->assertOk();
        $this->assertTrue(CompteComptable::generaux()->where('numero', '70970000')->exists());
        $this->assertNotNull(Categorie::find($famille->id)->compte_comptable_id);
        $this->assertFileDoesNotExist(storage_path('app/' . $depose), 'Le fichier déposé est retiré après usage.');
    }

    public function test_l_import_est_reserve_aux_administrateurs(): void
    {
        $autre = User::where('type_user_id', '!=', \Help::$USER_SA)
            ->where('type_user_id', '!=', \Help::$USER_ADMIN)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$autre) {
            $this->markTestSkipped('Aucun utilisateur non administrateur.');
        }

        $this->actingAs($autre)->get('/comptabilite/parametrage/modele-import')->assertForbidden();
        $this->actingAs($autre)->post('/comptabilite/parametrage/import/analyser', [])->assertForbidden();
        $this->actingAs($autre)->post('/comptabilite/parametrage/import/appliquer', ['fichier' => 'x'])->assertForbidden();
    }
}
