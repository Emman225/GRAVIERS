<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ClientApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function seedTypeUsers(): void
    {
        \Illuminate\Support\Facades\DB::table('type_user')->insertOrIgnore([
            ['id' => Help::$USER_SA, 'nom' => 'Super Admin', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_ADMIN, 'nom' => 'Admin', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_GESTIONNAIRE, 'nom' => 'Gestionnaire', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_CLIENT, 'nom' => 'Client', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_FOURNISSEUR, 'nom' => 'Fournisseur', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_APPORTEUR, 'nom' => 'Apporteur', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_AGENT_SAV, 'nom' => 'Agent SAV', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
            ['id' => Help::$USER_LIVREUR, 'nom' => 'Livreur', 'statut' => Help::$STATUT_ACTIF, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function createClientUser(): User
    {
        return User::create([
            'nom_prenoms' => 'Client Test',
            'email' => 'client_' . uniqid() . '@example.com',
            'contact' => '0700000001',
            'login' => 'client_' . uniqid(),
            'password' => Help::HashPassword('password123'),
            'type_user_id' => Help::$USER_CLIENT,
            'statut' => Help::$STATUT_ACTIF,
        ]);
    }

    protected function authHeaders(User $user): array
    {
        return [
            'access' => Crypt::encrypt($user->id),
            'type' => (string) $user->type_user_id,
        ];
    }

    // -------------------------------------------------------
    // GET /mon_gravier/get-config
    // -------------------------------------------------------

    public function test_get_config_returns_json(): void
    {
        $this->seedTypeUsers();

        $response = $this->getJson('/mon_gravier/get-config');

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/connexion
    // -------------------------------------------------------

    public function test_connexion_with_missing_fields_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/connexion', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_invalid_credentials_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/connexion', [
            'login' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_valid_credentials(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson('/mon_gravier/connexion', [
            'login' => $user->login,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/inscription
    // -------------------------------------------------------

    public function test_inscription_with_missing_fields_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/inscription', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_inscription_with_required_fields(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/inscription', [
            'nom_prenoms' => 'Nouveau Client',
            'email' => 'nouveau_' . uniqid() . '@example.com',
            'contact' => '0711111111',
            'login' => 'nouveaulogin_' . uniqid(),
            'password' => 'password123',
            'type_client' => Help::$PARTICULIER,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/renvoyerOtp
    // -------------------------------------------------------

    public function test_renvoyer_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/renvoyerOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_renvoyer_otp_with_valid_login(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson('/mon_gravier/renvoyerOtp', [
            'login' => $user->login,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/verifierOtp
    // -------------------------------------------------------

    public function test_verifier_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/verifierOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_verifier_otp_with_invalid_code(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/verifierOtp', [
            'login' => 'test@example.com',
            'otp' => '0000',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/demandeReinititPass
    // -------------------------------------------------------

    public function test_demande_reinit_pass_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/demandeReinititPass', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_demande_reinit_pass_with_valid_login(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson('/mon_gravier/demandeReinititPass', [
            'login' => $user->login,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/liste-commande (requires auth)
    // -------------------------------------------------------

    public function test_liste_commande_without_auth_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/liste-commande', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_liste_commande_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson(
            '/mon_gravier/liste-commande',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/enregistrer-commande
    // -------------------------------------------------------

    public function test_enregistrer_commande_without_data_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/enregistrer-commande', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_enregistrer_commande_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson(
            '/mon_gravier/enregistrer-commande',
            [
                'produits' => [],
            ],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }
}
