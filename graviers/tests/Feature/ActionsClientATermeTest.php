<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Les trois actions de la liste des clients à terme : appliquer ou retirer la
 * TVA, bloquer ou débloquer le compte, supprimer le client.
 *
 * Toutes passent par un simple lien : on vérifie ici qu'elles produisent bien
 * l'effet annoncé en base, et qu'elles ne laissent pas l'écran sans réponse.
 */
class ActionsClientATermeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([[\Help::$USER_ADMIN, 'Admin'], [\Help::$USER_CLIENT, 'Client']] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /** @return array{0: Client, 1: User} */
    private function clientATerme(int $statutCompte = 1, int $appliqueTva = 1): array
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => $statutCompte,
        ]);

        $client = Client::factory()->create([
            'user_id'        => $compte->id,
            'client_a_terme' => 1,
            'statut'         => 1,
            'applique_tva'   => $appliqueTva,
            'type_client'    => 'ENTREPRISE',
            'nom'            => 'TEST',
            'prenom'         => 'TEST',
        ]);

        return [$client, $compte];
    }

    // ------------------------------------------------------------------- TVA

    public function test_retirer_la_tva_puis_la_reappliquer(): void
    {
        [$client] = $this->clientATerme(appliqueTva: 1);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('show.appliqueTva', $client))->assertRedirect();
        $this->assertSame(0, (int) $client->fresh()->applique_tva, 'La TVA doit être retirée.');

        $this->actingAs($admin)->get(route('show.appliqueTva', $client))->assertRedirect();
        $this->assertSame(1, (int) $client->fresh()->applique_tva, 'La TVA doit être réappliquée.');
    }

    // --------------------------------------------------------------- Blocage

    public function test_bloquer_puis_debloquer_le_compte(): void
    {
        [, $compte] = $this->clientATerme(statutCompte: 1);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('show.bloquerCompte', ['id' => $compte->id, 'type' => 'blok']))
            ->assertRedirect();
        $this->assertSame(2, (int) $compte->fresh()->statut, 'Le compte doit être bloqué.');

        $this->actingAs($admin)
            ->get(route('show.bloquerCompte', ['id' => $compte->id, 'type' => 'blok']))
            ->assertRedirect();
        $this->assertSame(1, (int) $compte->fresh()->statut, 'Le compte doit être débloqué.');
    }

    /**
     * Un compte ni actif ni bloqué — une inscription encore en attente, par
     * exemple. Le bouton n'apparaît pas à l'écran, mais l'adresse reste
     * atteignable : elle ne doit pas laisser la page sans réponse.
     */
    public function test_bloquer_un_compte_dans_un_autre_etat_ne_casse_pas_la_page(): void
    {
        [, $compte] = $this->clientATerme(statutCompte: 3);

        $this->actingAs($this->admin())
            ->get(route('show.bloquerCompte', ['id' => $compte->id, 'type' => 'blok']))
            ->assertRedirect();
    }

    // ------------------------------------------------------------ Suppression

    public function test_supprimer_retire_le_compte_et_le_client(): void
    {
        [$client, $compte] = $this->clientATerme();

        $this->actingAs($this->admin())
            ->get(route('show.bloquerCompte', ['id' => $compte->id, 'type' => 'sup']))
            ->assertRedirect();

        $this->assertNotNull(
            DB::table('users')->where('id', $compte->id)->value('deleted_at'),
            'Le compte utilisateur doit être supprimé.'
        );
        $this->assertNotNull(
            DB::table('client')->where('id', $client->id)->value('deleted_at'),
            'La fiche client doit être supprimée elle aussi.'
        );
    }

    public function test_supprimer_un_compte_deja_supprime_ne_casse_pas_la_page(): void
    {
        [, $compte] = $this->clientATerme();
        DB::table('users')->where('id', $compte->id)->update(['deleted_at' => now()]);

        $this->actingAs($this->admin())
            ->get(route('show.bloquerCompte', ['id' => $compte->id, 'type' => 'sup']))
            ->assertRedirect();
    }

    // ------------------------------------------------------------------ Écran

    public function test_l_ecran_liste_bien_le_client_et_ses_actions(): void
    {
        [$client] = $this->clientATerme();

        $this->actingAs($this->admin())
            ->get(route('show.listClientATerme'))
            ->assertOk()
            ->assertSee('Retirer la TVA')
            ->assertSee('Bloquer')
            ->assertSee('Supprimer')
            // Le nom doit apparaître une seule fois, pas dédoublé.
            ->assertSee('TEST')
            ->assertDontSee('TEST TEST');
    }
}
