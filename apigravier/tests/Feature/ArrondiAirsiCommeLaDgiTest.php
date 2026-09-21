<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Services\CalculMontant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** L'arrondi du net suit la DGI (lot 94, 16/09/2026) : 11 480 HT → 14 224. */
class ArrondiAirsiCommeLaDgiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_airsi_porte_l_ecart_d_arrondi(): void
    {
        $client = Client::where('statut', 1)->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }
        $client->update(['regime_imposition' => null]);
        $client = $client->fresh();
        Configuration::query()->update(['taux_airsi' => 5]);

        $this->assertSame(678.0, \Help::airsiPour($client, 13546, 11480, 0.18));
        $this->assertSame(71390.0, \Help::airsiPour($client, 1427800, 1210000, 0.18));
        $this->assertSame(574.0, \Help::airsiPour($client, 11480, 11480, 0.0));
        $this->assertSame(677.0, \Help::airsiPour($client, 13546));
        // Le transport entre dans l'assiette (lot 97) : 5 280 + 4 000 livrés → 548, net 11 498.
        $this->assertSame(548.0, \Help::airsiPour($client, 6230, 5280, 0.18, 4000, 0.18));
    }
}
