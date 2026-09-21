<?php

namespace Tests\Feature;

use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Configuration;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LA TVA SUR LE TRANSPORT D'UNE VENTE MOBILE SUIT LE PARAMÉTRAGE (point 5).
 *
 * Le montant que l'application affiche vient de « verifier-montant » : il
 * doit annoncer la TVA sur le transport quand la case est cochée, et rien
 * sinon — exactement ce que la commande enregistrera.
 */
class TvaTransportVenteMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_verifier_montant_annonce_la_tva_transport_selon_la_case(): void
    {
        $config = Configuration::find(1);
        $client = Client::whereNotNull('user_id')->whereHas('user')->where('applique_tva', 1)->first();
        $produit = Produit::where('type_affaire', \Help::$VENTE)->where('statut', \Help::$STATUT_ACTIF)->first();
        $adresse = $client ? AdresseLivraison::where('client_id', $client->id)->first() : null;

        if (!$config || !$client || !$produit || !$adresse) {
            $this->markTestSkipped('Il manque une configuration, un client assujetti, un produit ou une adresse.');
        }

        $envoyer = function () use ($client, $produit, $adresse) {
            return $this->postJson('/mon_gravier/verifier-montant', [
                'access'       => Crypt::encryptString((string) $client->user_id),
                'type'         => 'mobile',
                'lignes'       => [['produit_id' => $produit->id, 'qte' => 2, 'prix' => (float) $produit->prix_moyen, 'livraison' => 0]],
                'adresse'      => $adresse->id,
                'meFaireLivre' => 1,
                'long'         => $adresse->longitude,
                'lat'          => $adresse->latitude,
            ]);
        };

        $config->update(['tva_transport' => 0]);
        $sans = $envoyer();
        $sans->assertOk();
        $this->assertSame(200, $sans->json('code'), $sans->json('message'));
        $this->assertEquals(0, (float) $sans->json('data.montant_tva_transport'));
        $livraison = (float) $sans->json('data.livraison');
        if ($livraison <= 0) {
            $this->markTestSkipped("Le transport de cette adresse vaut 0 : rien à taxer.");
        }

        $config->update(['tva_transport' => 1]);
        $avec = $envoyer();
        $attendu = round($livraison * (float) $config->tva / 100);
        $this->assertEquals($attendu, (float) $avec->json('data.montant_tva_transport'));
        $this->assertEquals(
            (float) $sans->json('data.total') + $attendu,
            (float) $avec->json('data.total'),
            'Le total doit porter exactement la TVA sur le transport en plus.'
        );

        $config->update(['tva_transport' => 0]);
    }
}
