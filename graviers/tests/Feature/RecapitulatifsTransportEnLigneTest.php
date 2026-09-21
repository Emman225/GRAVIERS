<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\Devis;
use App\Models\Produit;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LES RÉCAPITULATIFS AVANT VALIDATION (proformas du site) suivent la même
 * règle que les factures (09/09/2026) : le transport est une ligne du tableau
 * des articles, avec sa mention de TVA quand le paramétrage l'a taxé ; le
 * TOTAL HT reprend la somme du tableau, une seule ligne TVA fond les deux TVA,
 * et le TOTAL TTC compte le transport — il s'affichait sans lui, sous un total
 * à payer qui le comptait.
 *
 * Le client l'a demandé sur /recapitulatif-commande (capture du 09/09/2026),
 * où le coût de livraison restait sous les totaux.
 */
class RecapitulatifsTransportEnLigneTest extends TestCase
{
    use DatabaseTransactions;

    private const VUES = [
        'orders/recapPanierVersCommande', 'orders/recapDevis', 'orders/recapDevisLocation',
        'orders/recapDevisVersCommande', 'orders/devisValide', 'orders/recapLocation',
    ];

    public function test_chaque_recapitulatif_a_la_ligne_de_transport_taxee_et_garde_l_ancienne_sous_les_totaux(): void
    {
        foreach (self::VUES as $vue) {
            $source = file_get_contents(resource_path("views/{$vue}.blade.php"));
            $this->assertTrue(
                str_contains($source, "_ligne_transport") || str_contains($source, 'Coût de livraison'),
                "{$vue} n'a pas la ligne de transport dans le tableau."
            );
            // Décision du client (09/09/2026) : transport non taxé, présentation d'avant.
            $this->assertStringContainsString('Coût livraison', $source, "{$vue} a perdu la présentation d'avant (transport non taxé).");
        }
    }

    public function test_le_recapitulatif_de_commande_porte_le_transport_en_ligne_taxee(): void
    {
        $client = Client::where('statut', 1)->whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        $produit = Produit::whereHas('uniteProduit')->first();
        if (!$client || !$produit) {
            $this->markTestSkipped('Il faut un client actif et un produit.');
        }
        $config = Configuration::first();
        // Le test porte sur le transport : un client au réel, sans AIRSI (10/09/2026).
        $client->update(['regime_imposition' => 'RNI']);
        Auth::guard('web')->login($client->user);

        $session = [
            '0' => ['cout_livraison' => 4000, 'tva_transport' => 720, 'tva' => 18, 'infoSup' => 'Rue L237, Angré', 'km' => 12],
            'remise' => 0,
        ];
        Cart::add($produit->id, $produit->nom, 1, 100, ['unite' => 'U'])->associate(Produit::class);

        $html = $this->withSession($session)->get('/recapitulatif-commande')->assertOk()->getContent();

        $p = strpos($html, 'Coût de livraison');
        $this->assertNotFalse($p, 'La ligne « Coût de livraison » manque au tableau.');
        $ligne = substr($html, $p, 700);
        $this->assertStringContainsString('Rue L237', $ligne);
        $this->assertStringContainsString('12 km', $ligne);
        $this->assertStringContainsString('Forfait', $ligne);
        $this->assertStringContainsString('TVA (' . $config->tva . '%)', $ligne, 'La ligne de transport ne porte pas la mention de TVA.');
        $this->assertStringNotContainsString('Coût livraison', $html);

        // TOTAL HT = 100 + 4 000 ; TVA = 18 + 720 ; TTC = 4 838 (le transport compris).
        $this->assertStringContainsString('4 100</td>', $html, 'Le TOTAL HT ne compte pas le transport.');
        $this->assertStringContainsString('738</td>', $html, 'La ligne TVA ne fond pas la TVA du transport.');
        $this->assertStringContainsString('4 838</td>', $html, 'Le TTC ne compte pas le transport.');

        // Sans taxation : présentation d'avant (décision du client) — pas de ligne
        // dans le tableau, « Coût livraison (12 km) » sous les totaux, TTC des
        // articles (118) et total à payer 4 118.
        $session['0']['tva_transport'] = 0;
        $html = $this->withSession($session)->get('/recapitulatif-commande')->assertOk()->getContent();
        $this->assertStringNotContainsString('Coût de livraison', $html);
        $this->assertStringContainsString('Coût livraison (12 km)', $html);
        $this->assertStringContainsString('>100</td>', $html);
        $this->assertStringContainsString('>118</td>', $html);
        $this->assertStringContainsString('4 118</td>', $html);
    }

    public function test_le_devis_valide_porte_le_transport_en_ligne_taxee(): void
    {
        $devis = Devis::whereHas('detailDevis')->whereHas('client.user')->first();
        if (!$devis) {
            $this->markTestSkipped('Aucun devis avec des lignes.');
        }
        $devis->cout_livraison = 4000;
        $devis->tva_transport = 720;
        $devis->save();

        Auth::guard('web')->login($devis->client->user);
        $html = $this->get(route('client.devisValide', $devis))->assertOk()->getContent();

        $p = strpos($html, 'Coût de livraison');
        $this->assertNotFalse($p, 'La ligne « Coût de livraison » manque au tableau.');
        $this->assertStringContainsString('TVA (' . Configuration::first()->tva . '%)', substr($html, $p, 700));
        $this->assertStringNotContainsString('Coût livraison', $html);
    }
}
