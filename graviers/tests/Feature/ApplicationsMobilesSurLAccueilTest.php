<?php

namespace Tests\Feature;

use App\Models\Slide;
use Database\Seeders\ApplicationsMobilesSlideSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Lot 110 (17/09/2026) : les trois applications mobiles sur l'accueil, leur téléchargement, la diapositive. */
class ApplicationsMobilesSurLAccueilTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_accueil_presente_les_trois_applications_avec_leurs_boutons(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="applications"', $html);
        $this->assertStringContainsString('Mon Gravier dans votre poche', $html);
        foreach (['client', 'livreur', 'apporteur'] as $app) {
            $this->assertStringContainsString(route('telechargerApplication', $app), $html, "Bouton de téléchargement $app.");
        }
        $this->assertStringContainsString("Apporteur d'affaires", $html);
        $this->assertSame(3, substr_count($html, 'Télécharger pour Android'));
        foreach (['client', 'livreur', 'apporteur'] as $app) {
            $this->assertStringContainsString("frontend/assets/imgs/applications/$app.png", $html, "Écran réel de l'application $app dans sa carte.");
            $this->assertFileExists(public_path("frontend/assets/imgs/applications/$app.png"));
        }
        // Les badges du pied de page mènent à la section.
        $this->assertStringContainsString('href="' . route('client.index') . '#applications"', $html);
    }

    public function test_sans_fichier_le_telechargement_renvoie_a_la_section_avec_un_message(): void
    {
        $fichier = public_path('telechargements/mon-gravier-livreur.apk');
        $existait = is_file($fichier);
        if ($existait) {
            $this->markTestSkipped('Un APK livreur est posé localement.');
        }
        $this->get('/telecharger-application/livreur')
            ->assertRedirect(route('client.index') . '#applications')
            ->assertSessionHas('application_indisponible');
        $this->get('/telecharger-application/autre')->assertNotFound();
    }

    public function test_avec_le_fichier_le_telechargement_sert_l_apk(): void
    {
        $fichier = public_path('telechargements/mon-gravier-apporteur.apk');
        if (is_file($fichier)) {
            $this->markTestSkipped('Un APK apporteur est posé localement.');
        }
        file_put_contents($fichier, 'APK de test');
        try {
            $reponse = $this->get('/telecharger-application/apporteur');
            $reponse->assertOk();
            $reponse->assertHeader('content-type', 'application/vnd.android.package-archive');
            $this->assertStringContainsString('mon-gravier-apporteur.apk', $reponse->headers->get('content-disposition'));

            $html = $this->get('/')->assertOk()->getContent();
            $this->assertStringContainsString('mis à jour le ' . date('d/m/Y'), $html, 'La carte dit la date du fichier.');
        } finally {
            @unlink($fichier);
        }
    }

    public function test_le_seeder_ajoute_la_diapositive_une_seule_fois(): void
    {
        Slide::withTrashed()->where('titre', ApplicationsMobilesSlideSeeder::TITRE)->forceDelete();
        $avant = Slide::count();

        (new ApplicationsMobilesSlideSeeder())->run();
        (new ApplicationsMobilesSlideSeeder())->run();

        $this->assertSame($avant + 1, Slide::count(), 'Relançable sans doublon.');
        $slide = Slide::where('titre', ApplicationsMobilesSlideSeeder::TITRE)->first();
        $this->assertSame('#applications', $slide->bouton1_lien);
        $this->assertSame(1, (int) $slide->statut);
        $this->assertFileExists(public_path($slide->image), 'Le visuel est livré avec le site.');

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('trois applications mobiles', $html, 'La diapositive est dans le carrousel.');
        $this->assertStringContainsString('href="#applications" class="btn-slider-primary"', $html);
        $this->assertStringContainsString('fi-rs-download', $html);
    }
}
