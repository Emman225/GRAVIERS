<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Revue FNE (lot 99, 16/09/2026) : remise en pour cent, mode de paiement, stickers. */
class MessageDgiRemiseEtModeDePaiementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_remise_part_en_pour_cent_sur_la_marchandise(): void
    {
        $payload = ['discount' => 5000, 'items' => [
            ['reference' => '01', 'quantity' => 10, 'amount' => 3000, 'discount' => 0],
            ['reference' => '02', 'quantity' => 4, 'amount' => 5000, 'discount' => 0],
            ['reference' => 'LIVRAISON', 'quantity' => 1, 'amount' => 4000, 'discount' => 0],
        ]];
        $r = FneService::avecRemiseEnPourcentage($payload);
        $this->assertSame(0, $r['discount'], 'Plus de remise globale en francs.');
        $this->assertSame(10.0, $r['items'][0]['discount'], '5 000 sur 50 000 de marchandise = 10 %.');
        $this->assertSame(10.0, $r['items'][1]['discount']);
        $this->assertSame(0, $r['items'][2]['discount'], 'Le transport reste hors remise.');
        // Ce que la DGI recalculera : 50 000 × 90 % = 45 000 = HT − remise.
        $net = 0;
        foreach ($r['items'] as $it) {
            if ($it['reference'] !== 'LIVRAISON') {
                $net += $it['quantity'] * $it['amount'] * (1 - $it['discount'] / 100);
            }
        }
        $this->assertEqualsWithDelta(45000, $net, 0.001);
        // Une remise qui ne tombe pas juste : 5 000 sur 52 000, huit décimales.
        $r2 = FneService::avecRemiseEnPourcentage(['discount' => 5000, 'items' => [['reference' => '01', 'quantity' => 1, 'amount' => 52000, 'discount' => 0]]]);
        $this->assertEqualsWithDelta(47000, 52000 * (1 - $r2['items'][0]['discount'] / 100), 0.01);
        // Sans remise : rien ne change.
        $r3 = FneService::avecRemiseEnPourcentage(['discount' => 0, 'items' => [['reference' => '01', 'quantity' => 1, 'amount' => 1000, 'discount' => 0]]]);
        $this->assertSame(0, $r3['items'][0]['discount']);
        // Transport seul (facture de transport) : la remise porte sur lui.
        $r4 = FneService::avecRemiseEnPourcentage(['discount' => 400, 'items' => [['reference' => 'LIVRAISON', 'quantity' => 1, 'amount' => 4000, 'discount' => 0]]]);
        $this->assertSame(10.0, $r4['items'][0]['discount']);
    }

    public function test_le_mode_de_paiement_suit_le_client_et_les_reglements(): void
    {
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('Moov Money'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('Wave'));
        $this->assertSame('transfer', FneService::mapPaymentMethod('Virement bancaire'));

        $client = Client::where('statut', 1)->first();
        $aTerme = clone $client;
        $aTerme->client_a_terme = 1;
        $comptant = clone $client;
        $comptant->client_a_terme = 0;
        // Aucune affaire (id 0) : aucun règlement validé.
        $this->assertSame('deferred', FneService::methodePaiement(\Help::$COMMANDE, 0, $aTerme, 'Espèces'), 'Client à terme sans règlement : à terme.');
        $this->assertSame('cash', FneService::methodePaiement(\Help::$COMMANDE, 0, $comptant, 'Espèces'));
        $this->assertSame('mobile-money', FneService::methodePaiement(\Help::$COMMANDE, 0, $comptant, 'Orange Money'));
    }

    public function test_le_solde_de_stickers_est_dit(): void
    {
        $this->assertSame(' Stickers restants : 17.', FneService::mentionStickers(['balance_sticker' => 17, 'warning' => false]));
        $this->assertStringContainsString('ATTENTION', FneService::mentionStickers(['balance_sticker' => 2, 'warning' => true]));
        $this->assertSame('', FneService::mentionStickers([]));
    }
}
