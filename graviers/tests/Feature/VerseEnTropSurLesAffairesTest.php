<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * « VERSÉ EN TROP » SE JUGE SUR CE QUE LE CLIENT DOIT, PAS SUR SES FACTURES
 * (10/09/2026) : un client qui avait tout réglé et tout enlevé lisait
 * « Versé en trop 720 FCFA », sa facture étant courte de la TVA du transport.
 */
class VerseEnTropSurLesAffairesTest extends TestCase
{
    use DatabaseTransactions;

    /** Commande de 128 620 F TTC (100 000 HT + 18 000 TVA + 4 000 transport + 720 TVA transport + 5 900 AIRSI). */
    private function commande(float $paye, float $facture): array
    {
        TypeUser::firstOrCreate(['id' => \Help::$USER_CLIENT], ['nom' => 'Client', 'statut' => 1]);
        $compte  = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();

        $commande = Commande::factory()->create([
            'client_id' => $client->id, 'devis_id' => null, 'adresse_livraison_id' => null,
            'mode_paiement_id' => null, 'type_livraison_id' => null, 'etat_commande' => \Help::$COMMANDE_TERMINE,
            'montant_total' => 100000, 'remise' => 0, 'cout_livraison_client' => 4000, 'tva_transport' => 720, 'airsi' => 5900,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $commande->id, 'montant' => 18000, 'type_affaire' => \Help::$VENTE]);
        DetailCommande::factory()->create(['commande_id' => $commande->id, 'produit_id' => $produit->id, 'qte' => 1, 'prix' => 100000]);

        if ($paye > 0) {
            $mode = ModePaiement::firstOrCreate(['libelle' => 'Espèces (test)'], ['statut' => \Help::$STATUT_ACTIF]);
            $p = Paiement::factory()->create(['client_id' => $client->id, 'devis_id' => null, 'service' => 'COMMANDE', 'service_id' => $commande->id, 'montant_total' => $paye, 'statut' => 1]);
            LignePaiement::factory()->create(['paiement_id' => $p->id, 'mode_paiement_id' => $mode->id, 'service' => 'COMMANDE', 'service_id' => $commande->id, 'montant' => $paye, 'statut' => 1]);
        }
        if ($facture > 0) {
            $f = new Facture();
            $f->forceFill(['client_id' => $client->id, 'user_id' => $compte->id, 'service' => 'COMMANDE', 'service_id' => $commande->id, 'montant' => $facture, 'numero' => 'F' . random_int(100000, 999999), 'statut' => 1])->save();
        }

        return [$client, $compte, $commande];
    }

    public function test_tout_regle_et_facture_court_de_la_tva_transport_est_a_jour(): void
    {
        [$client, $compte, $commande] = $this->commande(128620, 127900);

        $this->assertSame(128620.0, $commande->montantAPayer());
        $this->assertSame(720.0, \Help::soldeClientBrut($client, false), 'La facture est bien courte de 720 : c\'est le cas signalé.');
        $this->assertSame(0.0, \Help::soldeClientSurAffaires($client), 'Le client a payé ce qu\'il doit : rien en trop.');

        $html = $this->actingAs($compte)->get('/mon-compte')->assertOk()->getContent();
        $this->assertStringNotContainsString('Versé en trop', $html);
        $this->assertStringContainsString('À jour', $html);
    }

    public function test_un_vrai_trop_percu_et_un_reste_a_payer_se_lisent(): void
    {
        [$client, $compte] = $this->commande(129620, 128620);
        $this->assertSame(1000.0, \Help::soldeClientSurAffaires($client));
        $this->actingAs($compte)->get('/mon-compte')->assertOk()->assertSee('Versé en trop')->assertSee('1 000');

        [$client, $compte] = $this->commande(100000, 0);
        $this->assertSame(-28620.0, \Help::soldeClientSurAffaires($client));
        $this->actingAs($compte)->get('/mon-compte')->assertOk()->assertSee('Reste à payer')->assertSee('28 620');
    }

    public function test_le_du_des_aides_comptables_suit_le_net_a_payer(): void
    {
        // Payée en entier, pas encore facturée : tout l'excédent attend sa facture,
        // TVA du transport et AIRSI compris.
        [$client] = $this->commande(128620, 0);
        $this->assertSame(128620.0, \Help::montantEnAttenteDeFacturation($client));
    }
}
