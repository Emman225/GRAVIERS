<?php

namespace Tests\Feature;

use App\Http\Controllers\AvanceClientController;
use App\Models\Client;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE CLIENT DU DÉPÔT D'AVANCE SE CHERCHE (10/09/2026) : la liste de la fenêtre
 * « Dépôt d'une avance » porte, pour chaque client, le n° de compte, le nom,
 * le prénom, le courriel et le téléphone, et s'ouvre en liste avec recherche.
 */
class ChoixDuClientDuDepotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chaque_option_porte_de_quoi_retrouver_le_client(): void
    {
        foreach ([[\Help::$USER_ADMIN, 'Admin'], [\Help::$USER_CLIENT, 'Client']] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }
        $compte = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client = Client::factory()->create([
            'user_id' => $compte->id, 'nom' => 'Kouassi', 'prenom' => 'Aya',
            'type_client' => 'PARTICULIER', 'contact1' => '0701020304', 'statut' => 1,
        ]);

        $entree = AvanceClientController::clientsPourDepot()->firstWhere('id', $client->id);
        $this->assertNotNull($entree);
        $this->assertSame($compte->id, $entree->compte);
        $this->assertSame('Aya', $entree->prenom);
        $this->assertSame($compte->email, $entree->email);

        $admin = User::factory()->create(['type_user_id' => \Help::$USER_ADMIN, 'statut' => \Help::$STATUT_ACTIF]);
        $page  = $this->actingAs($admin)->get('/avances')->assertOk();
        $page->assertSee('N° ' . $compte->id . ' — ', false)
             ->assertSee('Kouassi', false)
             ->assertSee($compte->email, false)
             ->assertSee('0701020304', false)
             ->assertSee('data-placeholder="Tapez un n° de compte', false)
             ->assertSee("dropdownParent: $('#modalAvance')", false)
             ->assertSee('select2.min.js', false);
    }
}
