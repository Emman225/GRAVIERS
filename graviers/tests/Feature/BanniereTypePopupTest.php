<?php

namespace Tests\Feature;

use App\Models\Banniere;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 13/09/2026 : une bannière de type POPUP (fenêtre publicitaire de l'accueil) se
 * modifie et se crée sans « Le type de bannière doit être Top, Flash ou Bottom ».
 */
class BanniereTypePopupTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur.');
        }

        return $u;
    }

    public function test_une_banniere_popup_se_modifie(): void
    {
        $b = Banniere::create(['titre' => 'Offre', 'sous_titre' => 'Essai', 'image' => 'productsBanniere/x.png',
            'num_ordre' => 1, 'type_banniere' => 'POPUP', 'statut' => 1]);

        $reponse = $this->actingAs($this->admin())->post(route('show.modificationDeBanniere', $b->id), [
            'titre' => 'Offre de bienvenue', 'sous_titre' => 'Profitez de nos meilleurs prix',
            'num_ordre' => 1, 'type_banniere' => 'POPUP',
        ]);

        $reponse->assertSessionDoesntHaveErrors(['type_banniere']);
        $this->assertSame('Offre de bienvenue', $b->fresh()->titre);
        $this->assertSame('POPUP', $b->fresh()->type_banniere);
    }

    public function test_un_type_inconnu_reste_refuse(): void
    {
        $b = Banniere::create(['titre' => 'Offre', 'sous_titre' => 'Essai', 'image' => 'productsBanniere/x.png',
            'num_ordre' => 1, 'type_banniere' => 'TOP', 'statut' => 1]);

        $this->actingAs($this->admin())->post(route('show.modificationDeBanniere', $b->id), [
            'titre' => 'Offre', 'num_ordre' => 1, 'type_banniere' => 'AUTRE',
        ])->assertSessionHasErrors(['type_banniere']);
    }
}
