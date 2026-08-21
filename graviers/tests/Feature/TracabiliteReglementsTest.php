<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Les six journaux de règlement affichent qui a saisi et qui a validé.
 */
class TracabiliteReglementsTest extends TestCase
{
    use DatabaseTransactions;

    public static function ecrans(): array
    {
        return [
            'encaissements comptant'   => ['/comptant/encaissements'],
            'encaissements livraison'  => ['/comptant/livraisons/encaissements'],
            'encaissements location'   => ['/encaissements/locations'],
            'paiements clients terme'  => ['/clients-terme/paiements'],
            'paiements fournisseurs'   => ['/fournisseurs/paiements'],
            'paiements apporteurs'     => ['/apporteurs/paiements'],
        ];
    }

    /** @dataProvider ecrans */
    public function test_les_deux_colonnes_sont_presentes(string $url): void
    {
        URL::forceRootUrl('');

        $admin = User::where('type_user_id', \Help::$USER_ADMIN)
            ->where('statut', \Help::$STATUT_ACTIF)->orderBy('id')->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        $reponse = $this->actingAs($admin)->get($url);
        $reponse->assertOk();
        $reponse->assertSee('Initié par', false);
        $reponse->assertSee('Validé par', false);
    }
}
