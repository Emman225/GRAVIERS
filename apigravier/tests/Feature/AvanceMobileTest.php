<?php

namespace Tests\Feature;

use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\LignePaiement;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * L'AVANCE D'UN CLIENT S'IMPUTE SUR SA COMMANDE MOBILE « EN AGENCE »
 * (point 19, 07/09/2026) — même règle que le site.
 */
class AvanceMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_une_commande_en_agence_consomme_l_avance_et_laisse_le_reliquat(): void
    {
        if (!Schema::hasTable('avance_client')) {
            $this->markTestSkipped('Table avance_client absente : migration du site non passée.');
        }
        $client  = Client::where('statut', 1)->whereNotNull('user_id')->first();
        $produit = Produit::first();
        if (!$client || !$produit) {
            $this->markTestSkipped('Il manque un client ou un produit.');
        }

        $avance = AvanceClient::create([
            'client_id'        => $client->id,
            'montant'          => 3000,
            'montant_consomme' => 0,
            'statut'           => AvanceClient::DISPONIBLE,
            'numero_recu'      => 'RA-TEST-001',
            'date_depot'       => now(),
        ]);
        $this->assertSame(3000.0, Avances::soldeDisponible($client));

        $commande = new Commande();
        $commande->numero        = 'T' . random_int(10000, 99999);
        $commande->client_id     = $client->id;
        $commande->date_commande = now();
        $commande->montant_total = 5000;
        $commande->etat_commande = \Help::$COMMANDE_EN_ATTENTE;
        $commande->statut        = \Help::$STATUT_ACTIF;
        $commande->remise        = 0;
        $commande->cout_livraison_client = 0;
        $commande->save();

        $ligne = new DetailCommande();
        $ligne->produit_id  = $produit->id;
        $ligne->commande_id = $commande->id;
        $ligne->qte         = 1;
        $ligne->prix        = 5000;
        $ligne->statut      = \Help::$STATUT_ACTIF;
        $ligne->save();

        $tva = new TvaCommande();
        $tva->client_id    = $client->id;
        $tva->commande_id  = $commande->id;
        $tva->montant      = 0;
        $tva->type_affaire = \Help::$VENTE;
        $tva->statut       = \Help::$STATUT_ACTIF;
        $tva->save();

        $resultat = Avances::imputerSurCommande($commande, $client);

        $this->assertEqualsWithDelta(3000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(2000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(2000, Commande::find($commande->id)->montantRestantDu(), 0.01);
        $this->assertSame(0.0, Avances::soldeDisponible($client));
        $this->assertEqualsWithDelta(3000, (float) $avance->fresh()->montant_consomme, 0.01);

        $reglement = Paiement::where('service', 'COMMANDE')->where('service_id', $commande->id)->first();
        $this->assertNotNull($reglement);
        $this->assertSame(1, (int) $reglement->statut);
        $this->assertStringStartsWith('AV-', $reglement->numero_recu);
        $this->assertSame(1, LignePaiement::where('paiement_id', $reglement->id)->where('statut', 1)->count());
        $this->assertSame(1, MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->count());
        $this->assertStringContainsString('3 000 FCFA', Avances::messageImputation($resultat));
    }
}
