<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PaiementApiTest extends TestCase
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
            'nom_prenoms' => 'Client Paiement',
            'email' => 'paiement_' . uniqid() . '@example.com',
            'contact' => '0700000004',
            'login' => 'paiement_' . uniqid(),
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
    // POST /mon_gravier/callBackPaiement
    // -------------------------------------------------------

    public function test_callback_paiement_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/callBackPaiement', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_callback_paiement_with_invalid_code_paiement(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/callBackPaiement', [
            'codePaiement' => 'INVALID_CODE_12345',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_callback_paiement_with_valid_structure(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/callBackPaiement', [
            'codePaiement' => 'PAY_' . uniqid(),
            'statut' => 'SUCCESS',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/obtenir-lien-paiement
    // -------------------------------------------------------

    public function test_obtenir_lien_paiement_with_missing_data(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/obtenir-lien-paiement', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_obtenir_lien_paiement_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson(
            '/mon_gravier/obtenir-lien-paiement',
            [
                'montant' => 10000,
                'commande_id' => 1,
            ],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    // -------------------------------------------------------
    // POST /mon_gravier/liste-paiement
    // -------------------------------------------------------

    public function test_liste_paiement_without_auth(): void
    {
        $this->seedTypeUsers();

        $response = $this->postJson('/mon_gravier/liste-paiement', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_liste_paiement_with_auth(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson(
            '/mon_gravier/liste-paiement',
            [],
            $this->authHeaders($user)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['code', 'message']);
    }

    public function test_liste_paiement_returns_json_response(): void
    {
        $this->seedTypeUsers();
        $user = $this->createClientUser();

        $response = $this->postJson(
            '/mon_gravier/liste-paiement',
            [],
            $this->authHeaders($user)
        );

        $response->assertHeader('Content-Type', 'application/json');
    }
}
