<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** La liste « factures » de l'application est « Mes paiements » du site (lot 89, 16/09/2026). */
class ListeDesPaiementsDepuisLeSiteTest extends TestCase
{
    use DatabaseTransactions;

    private function unClient(): array
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => User::find($c->user_id));
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }

        return [$client, User::find($client->user_id)];
    }

    public function test_la_liste_du_site_est_relayee(): void
    {
        [$client, $user] = $this->unClient();
        $lignes = [
            ['id' => 12, 'paiement_id' => 12, 'numero' => '123456', 'num_commande' => '123456', 'numero_recu' => 'RC-2026-001', 'code_paiement' => 'PA-2026-001',
             'mode_paiement' => 'Wave', 'etat' => 'Payé', 'montant' => 5000, 'montant_a_payer' => 5000, 'statut' => 1, 'service' => 'COMMANDE',
             'service_id' => 7, 'client_id' => $client->id, 'date_paiement' => '10/09/2026 12:00:00', 'date_commande' => '10/09/2026 11:00:00'],
            ['id' => 3, 'paiement_id' => null, 'numero' => '654321', 'num_commande' => '654321', 'etat' => 'En attente', 'montant' => 2000,
             'montant_a_payer' => 9000, 'statut' => 2, 'service' => 'COMMANDE', 'service_id' => 8, 'client_id' => $client->id,
             'date_paiement' => '11/09/2026 09:00:00', 'date_commande' => '11/09/2026 09:00:00'],
        ];
        Http::fake(['*/api/interne/client/*/paiements' => Http::response(['code' => 200, 'data' => $lignes], 200)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $r = $this->postJson('/mon_gravier/liste-facture', ['access' => Crypt::encryptString((string) $user->id), 'type' => 4, 'statut' => [1, 2]]);
        $r->assertOk()->assertJson(['code' => 200, 'source' => 'site']);
        $this->assertCount(2, $r->json('data'));
        $this->assertSame(12, $r->json('data.0.paiement_id'));
        $this->assertSame(2, $r->json('data.1.statut'));
        Http::assertSent(fn ($req) => $req->url() === 'http://site.recette/api/interne/client/' . $client->id . '/paiements' && $req['jeton'] === 'jeton-de-recette');
    }

    public function test_sans_site_l_ancienne_liste_sert_de_repli(): void
    {
        [$client, $user] = $this->unClient();
        Http::fake(['*/api/interne/client/*/paiements' => Http::response('Erreur', 500)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $r = $this->postJson('/mon_gravier/liste-facture', ['access' => Crypt::encryptString((string) $user->id), 'type' => 4, 'statut' => [1, 2]]);
        $r->assertOk()->assertJson(['code' => 200, 'source' => 'repli']);
        $this->assertStringContainsString('HTTP 500', $r->json('message_repli'));
    }
}
