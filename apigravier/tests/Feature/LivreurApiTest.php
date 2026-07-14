<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LivreurApiTest extends TestCase
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

    protected function createLivreurUser(): User
    {
        return User::create([
            'nom_prenoms' => 'Livreur Test',
            'email' => 'livreur_' . uniqid() . '@example.com',
            'contact' => '0700000003',
            'login' => 'livreur_' . uniqid(),
            'password' => Help::HashPassword('password123'),
            'type_user_id' => Help::$USER_LIVREUR,
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
    // POST /mon_gravier_livreur/connexion
    // -------------------------------------------------------

    public function test_connexion_with_missing_fields_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/connexion', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_invalid_credentials(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/connexion', [
            'login' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_valid_credentials(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson('/mon_gravier_livreur/connexion', [
            'login' => $user->login,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/renvoyerOtp
    // -------------------------------------------------------

    public function test_renvoyer_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/renvoyerOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_renvoyer_otp_with_valid_login(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson('/mon_gravier_livreur/renvoyerOtp', [
            'login' => $user->login,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/verifierOtp
    // -------------------------------------------------------

    public function test_verifier_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/verifierOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/home-livreur
    // -------------------------------------------------------

    public function test_home_livreur_without_auth(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/home-livreur', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_home_livreur_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson(
            '/mon_gravier_livreur/home-livreur',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/liste-vehicule
    // -------------------------------------------------------

    public function test_liste_vehicule_without_auth(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/liste-vehicule', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_liste_vehicule_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson(
            '/mon_gravier_livreur/liste-vehicule',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/enregistrer-vehicule
    // -------------------------------------------------------

    public function test_enregistrer_vehicule_without_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/enregistrer-vehicule', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_enregistrer_vehicule_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson(
            '/mon_gravier_livreur/enregistrer-vehicule',
            [
                'immatriculation' => 'AB-1234-CD',
                'marque' => 'Toyota',
            ],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_livreur/accepter-livraison
    // -------------------------------------------------------

    public function test_accepter_livraison_without_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_livreur/accepter-livraison', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_accepter_livraison_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createLivreurUser();

        $response = $this->postJson(
            '/mon_gravier_livreur/accepter-livraison',
            [
                'livraison_id' => 999,
            ],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }
}
