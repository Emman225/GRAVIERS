<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\TypeUser;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RouteAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required type_user records
        foreach ([
            [\Help::$USER_SA, 'Super Admin'],
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_GESTIONNAIRE, 'Gestionnaire'],
            [\Help::$USER_CLIENT, 'Client'],
            [\Help::$USER_FOURNISSEUR, 'Fournisseur'],
            [\Help::$USER_APPORTEUR, 'Apporteur'],
            [\Help::$USER_AGENT_SAV, 'Agent SAV'],
            [\Help::$USER_LIVREUR, 'Livreur'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        // Ensure configuration exists
        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18,
            'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100,
            'cout_livraison_min' => 5000,
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    protected function createUserOfType(int $typeId): User
    {
        return User::factory()->create([
            'type_user_id' => $typeId,
            'statut' => \Help::$STATUT_ACTIF,
        ]);
    }

    protected function createClientUser(): User
    {
        $user = $this->createUserOfType(\Help::$USER_CLIENT);
        Client::create([
            'user_id' => $user->id,
            'nom' => 'Test',
            'prenom' => 'Client',
            'email' => $user->email,
            'contact1' => '0000000000',
            'type_client' => \Help::$PARTICULIER,
            'statut' => \Help::$STATUT_ACTIF,
            'applique_tva' => 1,
        ]);
        return $user;
    }

    protected function createAdminUser(): User
    {
        return $this->createUserOfType(\Help::$USER_ADMIN);
    }

    protected function createGestionnaireUser(): User
    {
        return $this->createUserOfType(\Help::$USER_GESTIONNAIRE);
    }

    // ---------------------------------------------------------------
    // Public routes
    // ---------------------------------------------------------------

    public function test_homepage_returns_200(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_login_page_returns_200(): void
    {
        $response = $this->get('/login-account');
        $response->assertStatus(200);
    }

    public function test_client_login_page_returns_200(): void
    {
        $response = $this->get('client/login');
        // Should return 200 for unauthenticated users (login page)
        $response->assertOk();
    }

    public function test_error_403_page(): void
    {
        $response = $this->get('/403');
        $response->assertOk();
    }

    public function test_error_404_page(): void
    {
        $response = $this->get('/404');
        $response->assertOk();
    }

    public function test_error_500_page(): void
    {
        $response = $this->get('/500');
        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // POST /login-account validates credentials
    // ---------------------------------------------------------------

    public function test_login_post_with_invalid_credentials_redirects_back(): void
    {
        $response = $this->post('/login-account', [
            'login' => 'nonexistent',
            'password' => 'wrongpassword',
        ]);

        // Should redirect back (to login) with errors or error message
        $response->assertRedirect();
    }

    public function test_login_post_with_empty_data_fails_validation(): void
    {
        $response = $this->post('/login-account', []);

        // Should redirect back due to validation failure
        $response->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Auth-protected client routes redirect when not authenticated
    // ---------------------------------------------------------------

    public function test_client_home_redirects_unauthenticated_to_login(): void
    {
        $response = $this->get('/client/home');

        // The auth.type:client middleware redirects unauthenticated users to client login
        $response->assertRedirect();
    }

    public function test_client_panier_redirects_unauthenticated(): void
    {
        $response = $this->get('/client/mon-panier');
        $response->assertRedirect();
    }

    public function test_client_commande_redirects_unauthenticated(): void
    {
        $response = $this->get('/client/commande');
        $response->assertRedirect();
    }

    public function test_client_devis_page_redirects_unauthenticated(): void
    {
        $response = $this->get('/client/devis');
        $response->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Admin/Gestionnaire routes require proper role
    // ---------------------------------------------------------------

    public function test_admin_home_redirects_unauthenticated(): void
    {
        $response = $this->get('/gestionnaire/home');
        $response->assertRedirect();
    }

    public function test_admin_orders_list_redirects_unauthenticated(): void
    {
        $response = $this->get('/orders-list');
        $response->assertRedirect();
    }

    public function test_admin_products_list_redirects_unauthenticated(): void
    {
        $response = $this->get('/products-list');
        $response->assertRedirect();
    }

    public function test_client_cannot_access_admin_home(): void
    {
        $user = $this->createClientUser();

        $response = $this->actingAs($user)->get('/gestionnaire/home');

        // Should get 403 because client type is not Admin or Gestionnaire
        $response->assertStatus(403);
    }

    public function test_client_cannot_access_orders_list(): void
    {
        $user = $this->createClientUser();

        $response = $this->actingAs($user)->get('/orders-list');

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Admin users can access admin routes
    // ---------------------------------------------------------------

    public function test_admin_can_access_admin_home(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->get('/gestionnaire/home');

        // Should return 200 or a view (not 403 or redirect)
        $response->assertOk();
    }

    public function test_gestionnaire_can_access_admin_home(): void
    {
        $user = $this->createGestionnaireUser();

        $response = $this->actingAs($user)->get('/gestionnaire/home');

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // Authenticated client can access client routes
    // ---------------------------------------------------------------

    public function test_authenticated_client_can_access_home(): void
    {
        $user = $this->createClientUser();

        $response = $this->actingAs($user)->get('/client/home');

        $response->assertOk();
    }

    public function test_authenticated_client_can_access_cart(): void
    {
        $user = $this->createClientUser();

        $response = $this->actingAs($user)->get('/cart');

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // Seller/Fournisseur routes
    // ---------------------------------------------------------------

    public function test_seller_login_page_returns_200(): void
    {
        $response = $this->get('/seller/login');
        $response->assertOk();
    }

    public function test_seller_home_redirects_unauthenticated(): void
    {
        $response = $this->get('/sellers-home');
        $response->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Apporteur routes
    // ---------------------------------------------------------------

    public function test_apporteur_login_page_returns_200(): void
    {
        $response = $this->get('/apporteur/login');
        $response->assertOk();
    }

    public function test_apporteur_home_redirects_unauthenticated(): void
    {
        $response = $this->get('/apporteur/home');
        $response->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Livreur routes
    // ---------------------------------------------------------------

    public function test_livreur_login_page_returns_200(): void
    {
        $response = $this->get('/livreur/login');
        $response->assertOk();
    }

    public function test_livreur_home_redirects_unauthenticated(): void
    {
        $response = $this->get('/livreur/home');
        $response->assertRedirect();
    }

    // ---------------------------------------------------------------
    // Password reset routes (public)
    // ---------------------------------------------------------------

    public function test_password_reset_email_page_returns_200(): void
    {
        $response = $this->get('/demandeEmail');
        $response->assertOk();
    }
}
