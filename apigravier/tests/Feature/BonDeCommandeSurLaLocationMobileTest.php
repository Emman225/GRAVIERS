<?php

namespace Tests\Feature;

use App\Models\BlClient;
use App\Models\Client;
use App\Models\Location;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LE BON DE COMMANDE INTERNE SUR LA LOCATION (09/09/2026).
 *
 * Ce qui vaut pour la vente vaut pour la location : une ENTREPRISE ne loue pas
 * sans numéro de bon (le serveur fait foi), le numéro est figé sur la location
 * (location.numero_bon_commande) et renvoyé avec elle (location.*), la pièce
 * jointe est rangée dans bl_client, rattachée par location_id.
 */
class BonDeCommandeSurLaLocationMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function louer(Client $client, array $supplement = [])
    {
        $produit = Produit::where('type_affaire', \Help::$LOCATION)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit de location actif.');
        }
        $debut = now()->addDay()->toDateString();

        return $this->postJson('/mon_gravier/enregistrer-location', array_merge([
            'access'         => Crypt::encryptString((string) $client->user_id),
            'type'           => 'mobile',
            'mode_paiement'  => 3,
            'moyen_paiement' => 0,
            'lignes'         => [[
                'produit_id' => $produit->id,
                'qte'        => 1,
                'prix'       => (float) $produit->prix_moyen,
                'nbreJours'  => 1,
                'debut'      => $debut,
                'fin'        => $debut,
                'livraison'  => 0,
            ]],
            'total'          => (float) $produit->prix_moyen,
            'meFaireLivre'   => 0,
            'adresse'        => null,
            'long'           => -3.99,
            'lat'            => 5.35,
            'note'           => '',
        ], $supplement));
    }

    public function test_une_entreprise_sans_numero_de_bon_est_refusee(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise avec un compte.');
        }
        $avant = Location::count();

        $reponse = $this->louer($client, ['numero_bc' => '', 'bc_file' => null]);

        $reponse->assertOk();
        $this->assertSame(400, $reponse->json('code'), $reponse->json('message'));
        $this->assertStringContainsString('bon de commande', strtolower((string) $reponse->json('message')));
        $this->assertSame($avant, Location::count(), 'Une location a été créée sans bon de commande.');
    }

    public function test_le_numero_est_fige_sur_la_location_et_la_piece_rangee(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise avec un compte.');
        }
        $image = base64_encode("\x89PNG\r\n\x1a\nrecette");

        $reponse = $this->louer($client, ['numero_bc' => 'BC-LOC-31', 'bc_file' => $image]);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));

        $location = Location::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($location);
        $this->assertSame('BC-LOC-31', $location->numero_bon_commande, 'Le numéro n\'est pas figé sur la location.');

        $bon = BlClient::where('location_id', $location->id)->first();
        $this->assertNotNull($bon, 'La pièce du bon n\'est pas rangée dans bl_client (location_id).');
        $this->assertSame('BC-LOC-31', $bon->numero);
        $this->assertStringContainsString('lesBons/', (string) $bon->fichier);
    }

    public function test_un_particulier_loue_sans_bon(): void
    {
        $client = Client::where('type_client', \Help::$PARTICULIER)
            ->whereNotNull('user_id')->whereHas('user')
            ->where(function ($q) { $q->where('client_a_terme', 0)->orWhereNull('client_a_terme'); })
            ->first();
        if (!$client) {
            $this->markTestSkipped('Aucun particulier avec un compte.');
        }

        $reponse = $this->louer($client, ['numero_bc' => '', 'bc_file' => null]);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));
    }
}
