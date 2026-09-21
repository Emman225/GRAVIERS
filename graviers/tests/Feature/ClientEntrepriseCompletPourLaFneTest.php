<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\User;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Le client entreprise complet pour la FNE : NCC sans espace, gabarit B2B, régime sur Mes informations (lot 100). */
class ClientEntrepriseCompletPourLaFneTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_ncc_part_sans_espace_et_impose_le_gabarit_b2b(): void
    {
        $commande = Commande::whereNotNull('client_id')->orderByDesc('id')->get()->first(fn ($c) => $c->client);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec client.');
        }
        Client::where('id', $commande->client_id)->update(['ncc_clt' => '2507546 J', 'type_client' => 'ENTREPRISE']);
        $facture = Facture::create([
            'numero' => \Help::genererNumeroUnique('facture'), 'numero_fne' => 'REF-' . strtoupper(uniqid()), 'user_id' => User::first()?->id,
            'statut' => 2, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id, 'client_id' => $commande->client_id, 'montant' => 1000,
        ]);
        $payload = FneService::buildSalePayload($facture->fresh());
        $this->assertSame('B2B', $payload['template']);
        $this->assertSame('2507546J', $payload['clientNcc']);

        // La nature déclarée impose le gabarit (lot 100 bis) : une administration reste B2G même avec un NCC.
        Client::where('id', $commande->client_id)->update(['nature_fne' => 'B2G']);
        $this->assertSame('B2G', FneService::buildSalePayload($facture->fresh())['template']);
        Client::where('id', $commande->client_id)->update(['nature_fne' => 'B2F']);
        $this->assertSame('B2F', FneService::buildSalePayload($facture->fresh())['template']);
        Client::where('id', $commande->client_id)->update(['nature_fne' => null, 'ncc_clt' => null, 'type_client' => 'PARTICULIER']);
        $this->assertSame('B2C', FneService::buildSalePayload($facture->fresh())['template']);
    }

    public function test_mes_informations_porte_le_regime_d_imposition_de_l_entreprise(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        Client::where('id', $client->id)->update(['type_client' => 'ENTREPRISE', 'regime_imposition' => 'RSI']);
        Auth::guard('web')->login($client->user);

        $html = $this->get('/mon-compte')->assertOk()->getContent();
        $this->assertStringContainsString('name="regime_imposition"', $html);
        $this->assertStringContainsString('value="RSI" selected', $html);
        $this->assertStringContainsString('compte contribuable (NCC)', $html);
        $this->assertStringContainsString('name="nature_fne"', $html);
    }
}
