<?php

namespace Tests\Feature;

use App\Models\TypeUser;
use App\Models\User;
use Tests\TestCase;

/**
 * LA FAVICON SUR TOUTES LES PAGES (10/09/2026).
 *
 * Signalé par le client : l'icône de l'onglet manquait sur certaines pages
 * (commande d'un client à terme, et d'autres). Un seul partiel la déclare
 * désormais, avec des icônes aux tailles standard, et le /favicon.ico de
 * secours n'est plus le fichier vide de Laravel.
 */
class FaviconSurToutesLesPagesTest extends TestCase
{
    public function test_les_fichiers_existent_et_ne_sont_pas_vides(): void
    {
        foreach (['favicon.ico', 'favicon-32x32.png', 'favicon-16x16.png', 'apple-touch-icon.png'] as $f) {
            $this->assertFileExists(public_path($f));
            $this->assertGreaterThan(500, filesize(public_path($f)), $f . ' est vide ou presque.');
        }
        $this->assertSame("\x89PNG", substr(file_get_contents(public_path('favicon-32x32.png')), 0, 4));
    }

    public function test_les_gabarits_passent_par_le_partiel(): void
    {
        $gabarits = [
            'layout/head', 'client/head', 'client/about', 'error', 'errorCatch',
            'layout/errorCatchBack', 'produit/products-grid', 'siteEnConstruction',
            'errors/403', 'errors/404',
        ];
        foreach ($gabarits as $g) {
            $source = file_get_contents(resource_path('views/' . $g . '.blade.php'));
            $this->assertStringContainsString("@include('layout._favicon')", $source, $g);
            $this->assertStringNotContainsString('favicon.svg', $source, $g . ' pointe encore vers le fichier inexistant.');
            // Le logo reste affiché dans les pages ; il ne sert plus d'icône.
            $this->assertStringNotContainsString('rel="shortcut icon" type="image/x-icon"', $source, $g . ' déclare encore le logo entier en icône.');
        }
    }

    public function test_les_pages_servies_portent_les_liens(): void
    {
        TypeUser::firstOrCreate(['id' => \Help::$USER_ADMIN], ['nom' => 'Admin', 'statut' => 1]);
        $admin = User::factory()->create(['type_user_id' => \Help::$USER_ADMIN, 'statut' => \Help::$STATUT_ACTIF]);

        // Page de connexion (publique), page du back-office, page du site.
        $pages = [
            $this->get(route('show.login')),
            $this->actingAs($admin)->get('/list-client'),
            $this->get('/'),
        ];
        foreach ($pages as $reponse) {
            $reponse->assertOk()
                ->assertSee('rel="icon" type="image/png" sizes="32x32"', false)
                ->assertSee('favicon.ico', false)
                ->assertDontSee('rel="shortcut icon" type="image/x-icon"', false);
        }
    }
}
