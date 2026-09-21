<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Paiement;
use App\Services\PaiementsDuClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** « Mes paiements » : une règle, deux écrans (web et application) — lot 89, 16/09/2026. */
class PaiementsDuClientPourLApplicationTest extends TestCase
{
    use DatabaseTransactions;

    private function unClientAvecReglement(): Client
    {
        $p = Paiement::where('statut', 1)->whereNotNull('client_id')->whereHas('lignePaiements')->orderByDesc('id')->get()
            ->first(fn ($p) => Client::find($p->client_id)?->user);
        if (!$p) {
            $this->markTestSkipped('Aucun règlement validé avec lignes.');
        }

        return Client::find($p->client_id);
    }

    public function test_la_liste_pour_l_application_reprend_les_reglements_du_web(): void
    {
        $client = $this->unClientAvecReglement();
        $web = PaiementsDuClient::lignes($client, 'effectues');
        $app = PaiementsDuClient::pourLApplication($client);
        $effectues = array_values(array_filter($app, fn ($l) => $l['statut'] === 1));

        $this->assertCount(count($web), $effectues, 'Autant de règlements effectués que sur le web.');
        if ($effectues) {
            $this->assertGreaterThan(0, $effectues[0]['paiement_id'], 'Le reçu s\'ouvre sur le règlement.');
            $this->assertContains($effectues[0]['etat'], ['Payé', 'Effectuée', 'Validée — en cours', 'En attente de validation']);
            $this->assertNotEmpty($effectues[0]['date_paiement']);
        }
        foreach (array_filter($app, fn ($l) => $l['statut'] === 2) as $l) {
            $this->assertGreaterThan(0, $l['montant'], 'Un paiement en attente porte un reste à payer.');
        }

        // La page web n'a pas changé.
        Auth::guard('web')->login($client->user);
        $this->get('/client/liste-des-paiements-effectues')->assertOk();
        $this->get('/client/liste-des-paiements-en-attente')->assertOk();
    }

    public function test_la_route_interne_exige_le_jeton_et_rend_la_liste(): void
    {
        $client = $this->unClientAvecReglement();
        config(['constantes.jeton_interne' => 'jeton-de-recette']);

        $this->postJson('/api/interne/client/' . $client->id . '/paiements', ['jeton' => 'faux'])->assertStatus(403);
        $r = $this->postJson('/api/interne/client/' . $client->id . '/paiements', ['jeton' => 'jeton-de-recette'])->assertOk()->assertJson(['code' => 200]);
        $this->assertSame(count(PaiementsDuClient::pourLApplication($client)), count($r->json('data')));
    }
}
