<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\Produit;
use App\Models\User;
use App\Services\CalculMontant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * TVA PAR CLIENT ET AIRSI SUR LE MOBILE (10/09/2026) : mêmes règles que le site.
 */
class TvaParClientEtAirsiMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_calcul_serveur_suit_le_client(): void
    {
        Configuration::first()->update(['tva' => 18, 'tva_transport' => 1, 'taux_airsi' => 5]);
        $client  = Client::where('statut', 1)->whereNotNull('user_id')->first();
        $produit = Produit::where('statut', 1)->first() ?: Produit::first();
        $user    = $client ? User::find($client->user_id) : null;
        if (!$client || !$produit || !$user) {
            $this->markTestSkipped('Il manque un client, un produit ou un utilisateur.');
        }
        $produit = Produit::where('statut', 1)->where('prix_moyen', '>', 0)->first() ?: Produit::where('prix_moyen', '>', 0)->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit avec un prix.');
        }
        // Le prix envoyé ne fait pas foi : le serveur lit le catalogue.
        $donnees = ['lignes' => [['produit_id' => $produit->id, 'qte' => 2, 'prix' => 0]], 'meFaireLivre' => 0];

        // Client au réel, TVA appliquée : HT + TVA 18 %, pas d'AIRSI.
        $client->update(['applique_tva' => 1, 'applique_tva_transport' => 1, 'regime_imposition' => 'RNI']);
        $calcul = CalculMontant::pour($user, $client->fresh(), $donnees);
        $ht = (float) $calcul['ht'];
        $this->assertGreaterThan(0, $ht);
        $this->assertEqualsWithDelta(round($ht * 0.18), $calcul['tva'], 1);
        $this->assertSame(0.0, (float) $calcul['airsi']);

        // Client sans régime : AIRSI 5 % du HT + TVA, compris dans le total.
        $client->update(['regime_imposition' => null]);
        $calcul = CalculMontant::pour($user, $client->fresh(), $donnees);
        $this->assertEqualsWithDelta(round(($ht + $calcul['tva']) * 0.05), $calcul['airsi'], 1);
        $this->assertEqualsWithDelta($ht + $calcul['tva'] + $calcul['airsi'], $calcul['total'], 1);

        // Client dispensé de TVA marchandise : TVA 0, AIRSI sur le HT seul.
        $client->update(['applique_tva' => 0]);
        $calcul = CalculMontant::pour($user, $client->fresh(), $donnees);
        $this->assertSame(0.0, (float) $calcul['tva']);
        $this->assertEqualsWithDelta(round($ht * 0.05), $calcul['airsi'], 1);

        // L'accueil dit au mobile ce qui vaut pour ce client.
        $acces = ['access' => Crypt::encryptString((string) $client->user_id), 'type' => 'client'];
        $retour = $this->postJson('/mon_gravier/recuperer-montant-point', $acces)->assertOk()->json();
        $this->assertSame(0.0, (float) $retour['tva']);
        $this->assertSame(1, (int) $retour['tvaTransport']);
        $this->assertEqualsWithDelta(5, (float) $retour['tauxAirsi'], 0.01);

        // Dispensé de TVA transport, au réel : rien.
        $client->update(['applique_tva' => 1, 'applique_tva_transport' => 0, 'regime_imposition' => 'RSI']);
        $retour = $this->postJson('/mon_gravier/recuperer-montant-point', $acces)->assertOk()->json();
        $this->assertSame(0, (int) $retour['tvaTransport']);
        $this->assertSame(0.0, (float) $retour['tauxAirsi']);
        $this->assertSame(0.0, \Help::tvaTransportPour($client->fresh(), 10000));
    }
}
