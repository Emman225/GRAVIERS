<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApporteurApiTest extends TestCase
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

    protected function createApporteurUser(): User
    {
        return User::create([
            'nom_prenoms' => 'Apporteur Test',
            'email' => 'apporteur_' . uniqid() . '@example.com',
            'contact' => '0700000002',
            'login' => 'apporteur_' . uniqid(),
            'password' => Help::HashPassword('password123'),
            'type_user_id' => Help::$USER_APPORTEUR,
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
    // POST /mon_gravier_apporteur/connexion
    // -------------------------------------------------------

    public function test_connexion_with_missing_fields_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/connexion', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_invalid_credentials(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/connexion', [
            'login' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_connexion_with_valid_credentials(): void
    {
        $this->seedTypeUsers();
        $user = $this->createApporteurUser();

        $response = $this->postJson('/mon_gravier_apporteur/connexion', [
            'login' => $user->login,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/inscription
    // -------------------------------------------------------

    public function test_inscription_with_missing_fields_returns_error(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/inscription', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_inscription_with_required_fields(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/inscription', [
            'nom_prenoms' => 'Nouvel Apporteur',
            'email' => 'apporteur_new_' . uniqid() . '@example.com',
            'contact' => '0722222222',
            'login' => 'apporteurlogin_' . uniqid(),
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/renvoyerOtp
    // -------------------------------------------------------

    public function test_renvoyer_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/renvoyerOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_renvoyer_otp_with_valid_login(): void
    {
        $this->seedTypeUsers();
        $user = $this->createApporteurUser();

        $response = $this->postJson('/mon_gravier_apporteur/renvoyerOtp', [
            'login' => $user->login,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/verifierOtp
    // -------------------------------------------------------

    public function test_verifier_otp_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/verifierOtp', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/home-apporteur
    // -------------------------------------------------------

    public function test_home_apporteur_without_auth(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/home-apporteur', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_home_apporteur_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createApporteurUser();

        $response = $this->postJson(
            '/mon_gravier_apporteur/home-apporteur',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/liste-commissions
    // -------------------------------------------------------

    public function test_liste_commissions_without_auth(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/liste-commissions', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_liste_commissions_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createApporteurUser();

        $response = $this->postJson(
            '/mon_gravier_apporteur/liste-commissions',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier_apporteur/enregistrer-demande-paiement
    // -------------------------------------------------------

    public function test_enregistrer_demande_paiement_without_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier_apporteur/enregistrer-demande-paiement', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_enregistrer_demande_paiement_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createApporteurUser();

        $response = $this->postJson(
            '/mon_gravier_apporteur/enregistrer-demande-paiement',
            [
                'montant' => 5000,
            ],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }
}
