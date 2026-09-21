<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * BACK-OFFICE (10/09/2026) : la TVA sur le transport se retire par client depuis
 * la liste des clients, comme la TVA marchandise ; le taux de l'AIRSI se règle
 * dans Paramètres.
 */
class TvaTransportParClientBackOfficeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_bascule_et_la_liste(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        $client = Client::where('statut', 1)->first();
        if (!$admin || !$client) {
            $this->markTestSkipped('Il manque un administrateur ou un client.');
        }
        Auth::guard('web')->login($admin);
        $client->update(['applique_tva' => 1, 'applique_tva_transport' => 1]);

        $this->get(route('show.appliqueTvaTransport', $client))->assertRedirect();
        $this->assertSame(0, (int) $client->fresh()->applique_tva_transport, 'La bascule retire la TVA transport.');
        $this->get(route('show.appliqueTvaTransport', $client))->assertRedirect();
        $this->assertSame(1, (int) $client->fresh()->applique_tva_transport, 'La bascule la rétablit.');

        $client->update(['applique_tva_transport' => 0]);
        $html = $this->get('/list-client')->assertOk()->getContent();
        $this->assertStringContainsString('Appliquer la TVA transport', $html);
        $this->assertStringContainsString('Retirer la TVA marchandise', $html);
        $this->assertStringContainsString('id="tvaTransportModal-' . $client->id . '"', $html);
    }

    public function test_le_taux_airsi_se_regle_dans_les_parametres(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        Auth::guard('web')->login($admin);
        $r = $this->get('/parametre');
        $this->assertSame(200, $r->status(), 'statut ' . $r->status() . ' : ' . substr(strip_tags($r->getContent()), 0, 400));
        $html = $r->getContent();
        $this->assertStringContainsString('name="taux_airsi"', $html, 'Le champ du taux AIRSI manque dans Paramètres.');

        $config = Configuration::first();
        $this->post('/parametre', array_merge($config->only(['tva', 'montant_point', 'montant_pour_un_point', 'devise']), ['taux_airsi' => 7.5, 'tva_transport' => 1]))
            ->assertRedirect();
        $this->assertEqualsWithDelta(7.5, (float) Configuration::first()->taux_airsi, 0.001);
        $this->assertEqualsWithDelta(7.5, \Help::tauxAirsi(), 0.001);
    }
}
