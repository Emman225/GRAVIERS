<?php

namespace Tests\Feature;

use Help;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AuthMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seed the type_user table required by foreign key constraint.
     */
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

    protected function createTestUser(int $typeUserId = 4): User
    {
        return User::create([
            'nom_prenoms' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'contact' => '0700000000',
            'login' => 'testuser_' . uniqid(),
            'password' => Help::HashPassword('password123'),
            'type_user_id' => $typeUserId,
            'statut' => Help::$STATUT_ACTIF,
        ]);
    }

    public function test_request_without_access_header_returns_403(): void
    {
        $this->seedTypeUsers();

        // Use a route that uses the AuthUserMiddleware.
        // We register a temporary test route with the middleware.
        \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\AuthUserMiddleware::class)
            ->post('/test-auth-middleware', function () {
                return response()->json(['code' => 200, 'message' => 'OK']);
            });

        $response = $this->postJson('/test-auth-middleware');

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 503,
            'message' => 'Requete non autorisée',
        ]);
    }

    public function test_request_with_invalid_encrypted_token_returns_403(): void
    {
        $this->seedTypeUsers();

        \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\AuthUserMiddleware::class)
            ->post('/test-auth-middleware-invalid', function () {
                return response()->json(['code' => 200, 'message' => 'OK']);
            });

        $response = $this->postJson('/test-auth-middleware-invalid', [], [
            'access' => 'invalid-encrypted-token',
            'type' => '4',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 503,
            'message' => 'Requete non autorisée',
        ]);
    }

    public function test_request_with_valid_token_for_existing_user_passes(): void
    {
        $this->seedTypeUsers();
        $user = $this->createTestUser(Help::$USER_CLIENT);

        \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\AuthUserMiddleware::class)
            ->post('/test-auth-middleware-valid', function () {
                return response()->json(['code' => 200, 'message' => 'OK']);
            });

        $encryptedToken = Crypt::encrypt($user->id);

        $response = $this->postJson('/test-auth-middleware-valid', [], [
            'access' => $encryptedToken,
            'type' => (string) Help::$USER_CLIENT,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'code' => 200,
            'message' => 'OK',
        ]);
    }

    public function test_request_with_wrong_type_returns_403(): void
    {
        $this->seedTypeUsers();
        $user = $this->createTestUser(Help::$USER_CLIENT);

        \Illuminate\Support\Facades\Route::middleware(\App\Http\Middleware\AuthUserMiddleware::class)
            ->post('/test-auth-middleware-wrong-type', function () {
                return response()->json(['code' => 200, 'message' => 'OK']);
            });

        $encryptedToken = Crypt::encrypt($user->id);

        $response = $this->postJson('/test-auth-middleware-wrong-type', [], [
            'access' => $encryptedToken,
            'type' => (string) Help::$USER_LIVREUR, // wrong type
        ]);

        $response->assertStatus(403);
    }
}
