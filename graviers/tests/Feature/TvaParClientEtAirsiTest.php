<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Models\TvaCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TVA PAR CLIENT ET AIRSI (10/09/2026).
 *
 *  - La TVA (marchandise, transport) est appliquée à tous par défaut ; un client
 *    donné peut en être dispensé, l'une indépendamment de l'autre.
 *  - L'AIRSI (5 % du HT + TVA) s'applique au client qui n'a pas déclaré un
 *    régime réel (RNI / RSI) ; le net à payer devient HT + TVA + AIRSI.
 */
class TvaParClientEtAirsiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_base_porte_les_nouvelles_colonnes(): void
    {
        $this->assertTrue(Schema::hasColumn('client', 'applique_tva_transport'));
        $this->assertTrue(Schema::hasColumn('configuration', 'taux_airsi'));
        foreach (['commande', 'location', 'demande_livraison', 'devis'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'airsi'), "{$table}.airsi manque : migration non passée.");
        }
        $this->assertTrue(Schema::hasColumn('facture', 'airsi_applique'));
    }

    public function test_la_tva_est_appliquee_par_defaut_et_se_retire_par_client(): void
    {
        Configuration::first()->update(['tva' => 18, 'tva_transport' => 1]);
        $client = Client::where('statut', 1)->first();
        $client->update(['applique_tva' => 1, 'applique_tva_transport' => 1]);
        $this->assertEqualsWithDelta(0.18, Client::tva($client->fresh()), 0.0001);
        $this->assertEqualsWithDelta(0.18, Client::tvaTransport($client->fresh()), 0.0001);
        $this->assertEqualsWithDelta(1800, \Help::tvaTransportPour($client->fresh(), 10000), 0.01);

        // Dispense sur la marchandise seule : le transport reste taxé.
        $client->update(['applique_tva' => 0]);
        $this->assertSame(0.0, Client::tva($client->fresh()));
        $this->assertEqualsWithDelta(0.18, Client::tvaTransport($client->fresh()), 0.0001);

        // Dispense sur le transport seul.
        $client->update(['applique_tva' => 1, 'applique_tva_transport' => 0]);
        $this->assertEqualsWithDelta(0.18, Client::tva($client->fresh()), 0.0001);
        $this->assertSame(0.0, Client::tvaTransport($client->fresh()));
        $this->assertSame(0.0, \Help::tvaTransportPour($client->fresh(), 10000));

        // Transport non taxé dans la configuration : personne ne le paie.
        Configuration::first()->update(['tva_transport' => 0]);
        $client->update(['applique_tva_transport' => 1]);
        $this->assertSame(0.0, Client::tvaTransport($client->fresh()));

        // Sans client (visiteur) : le taux de la configuration.
        $this->assertEqualsWithDelta(0.18, Client::tva(null), 0.0001);
    }

    public function test_l_airsi_s_applique_hors_regime_reel_a_5_pour_cent_du_ttc(): void
    {
        Configuration::first()->update(['taux_airsi' => 5]);
        $client = Client::where('statut', 1)->first();

        foreach (['', null, 'RME', 'RE', 'inconnu'] as $regime) {
            $client->update(['regime_imposition' => $regime]);
            $this->assertTrue(Client::soumisAirsi($client->fresh()), "Régime « {$regime} » : l'AIRSI s'applique.");
        }
        foreach (['RNI', 'RSI', "RNI — Réel normal d'imposition"] as $regime) {
            $client->update(['regime_imposition' => $regime]);
            $this->assertFalse(Client::soumisAirsi($client->fresh()), "Régime « {$regime} » : pas d'AIRSI.");
        }

        $client->update(['regime_imposition' => null]);
        // HT 1 210 000 + TVA 217 800 = 1 427 800 ; AIRSI 5 % = 71 390.
        $this->assertEqualsWithDelta(71390, \Help::airsiPour($client->fresh(), 1427800), 0.01);
        $client->update(['regime_imposition' => 'RNI']);
        $this->assertSame(0.0, \Help::airsiPour($client->fresh(), 1427800));
        $this->assertFalse(Client::soumisAirsi(null));
    }

    /** L'arrondi de la DGI (lot 94, 16/09/2026) : 11 480 HT → net 14 224, l'AIRSI porte l'écart. */
    public function test_l_airsi_fige_donne_le_net_de_la_dgi(): void
    {
        $client = Client::where('statut', 1)->first();
        $client->update(['regime_imposition' => null]);
        $client = $client->fresh();
        \App\Models\Configuration::query()->update(['taux_airsi' => 5]);

        // Bati Azo, facture 1339220N26000000022 : HT 11 480, TVA 2 066, la DGI certifie 14 224.
        $this->assertSame(678.0, \Help::airsiPour($client, 11480 + 2066, 11480, 0.18));
        $this->assertSame(14224.0, 11480 + 2066 + \Help::airsiPour($client, 13546, 11480, 0.18));
        // Une TVA passée non arrondie ne casse pas l'entier.
        $this->assertSame(678.0, \Help::airsiPour($client, 11480 + 2066.4, 11480, 0.18));
        // Quand la ligne tombe juste, rien ne change : 1 210 000 → 71 390 comme avant.
        $this->assertSame(71390.0, \Help::airsiPour($client, 1427800, 1210000, 0.18));
        // Client dispensé de TVA : même valeur que l'ancienne règle.
        $this->assertSame(574.0, \Help::airsiPour($client, 11480, 11480, 0.0));
        // Sans HT ni taux, l'ancienne règle demeure (affaires déjà figées, engagements).
        $this->assertSame(677.0, \Help::airsiPour($client, 13546));
    }

    /** Le transport entre dans l'assiette, comme la DGI (lot 97, 16/09/2026) : 5 280 + 4 000 livrés → 548, net 11 498. */
    public function test_l_airsi_porte_aussi_sur_le_transport(): void
    {
        $client = Client::where('statut', 1)->first();
        $client->update(['regime_imposition' => null]);
        $client = $client->fresh();
        \App\Models\Configuration::query()->update(['taux_airsi' => 5]);

        // Facture 1339220N26000000025 : marchandise 5 280 + TVA 950, transport 4 000 + TVA 720.
        $airsi = \Help::airsiPour($client, 5280 + 950, 5280, 0.18, 4000, 0.18);
        $this->assertSame(548.0, $airsi);
        $this->assertSame(11498.0, 5280 + 950 + 4000 + 720 + $airsi);
        // Transport non taxé : il entre quand même dans l'assiette.
        $this->assertSame(round((5280 * 1.18 + 4000) * 1.05) - (5280 + 950 + 4000), \Help::airsiPour($client, 6230, 5280, 0.18, 4000, 0.0));
        // Sans transport : la règle du lot 94 demeure.
        $this->assertSame(678.0, \Help::airsiPour($client, 13546, 11480, 0.18, 0, 0));
    }

    public function test_le_du_de_la_commande_inclut_l_airsi_fige(): void
    {
        $client  = Client::where('statut', 1)->first();
        $produit = Produit::first();
        $commande = Commande::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'date_commande' => now(),
            'montant_total' => 100000, 'etat_commande' => \Help::$COMMANDE_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF,
            'remise' => 0, 'cout_livraison_client' => 0, 'airsi' => 5900,
        ]);
        DetailCommande::create(['produit_id' => $produit->id, 'commande_id' => $commande->id, 'qte' => 1, 'prix' => 100000, 'statut' => \Help::$STATUT_ACTIF]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $commande->id, 'montant' => 18000, 'type_affaire' => 2]);

        // HT 100 000 + TVA 18 000 + AIRSI 5 900 = 123 900.
        $this->assertEqualsWithDelta(123900, Commande::find($commande->id)->montantAPayer(), 0.01);
    }
}
