<?php

namespace Tests\Feature;

use App\Models\AdresseLivraison;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use App\Services\Avances;
use App\Services\FacturationCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UN ENLÈVEMENT, UNE FACTURE DGI (10/09/2026).
 *
 * Commande 692741 puis 150579 : l'avance imputée à la commande émettait une
 * facture « sur règlement », et l'enlèvement une seconde — deux factures DGI
 * pour un seul enlèvement. Désormais aucune facture ne naît au règlement ; la
 * facture d'enlèvement porte toute la quantité servie et les règlements déjà
 * validés (avance comprise) s'y rattachent : son reste est le reliquat.
 */
class FactureEnlevementApresAvanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([[\Help::$USER_ADMIN, 'Admin'], [\Help::$USER_CLIENT, 'Client'], [\Help::$USER_FOURNISSEUR, 'Fournisseur'], [\Help::$USER_LIVREUR, 'Livreur']] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }
        Configuration::firstOrCreate(['id' => 1], ['tva' => 18, 'tonne_moyenne' => 25, 'cout_liv_fixe' => 100, 'cout_livraison_min' => 5000]);
        Mail::fake();
    }

    private function compte(int $type): User
    {
        return User::factory()->create(['type_user_id' => $type, 'statut' => \Help::$STATUT_ACTIF]);
    }

    /** Une commande de 100 t à 12 100 F (1 210 000 HT, 1 427 800 TTC), livrée en un ou deux bons. */
    private function commandeLivree(array $quantites): array
    {
        $client      = Client::factory()->create(['user_id' => $this->compte(\Help::$USER_CLIENT)->id, 'client_a_terme' => 1]);
        $fournisseur = Fournisseur::factory()->create(['user_id' => $this->compte(\Help::$USER_FOURNISSEUR)->id]);
        $livreur     = Livreur::factory()->create(['user_id' => $this->compte(\Help::$USER_LIVREUR)->id]);
        $produit     = Produit::factory()->create(['type_affaire' => \Help::$VENTE]);
        $adresse     = AdresseLivraison::first();
        if (!$adresse) {
            $this->markTestSkipped('Aucune adresse de livraison en base.');
        }

        $commande = Commande::factory()->create([
            'client_id' => $client->id, 'devis_id' => null, 'adresse_livraison_id' => null, 'mode_paiement_id' => null,
            'type_livraison_id' => null, 'date_commande' => now(), 'montant_total' => 1210000, 'remise' => 0,
            'est_livrable' => 1, 'cout_livraison_client' => 0,
        ]);
        $ligne = DetailCommande::factory()->create([
            'commande_id' => $commande->id, 'produit_id' => $produit->id, 'qte' => 100, 'prix' => 12100, 'statut' => \Help::$STATUT_ACTIF,
        ]);
        \App\Models\TvaCommande::create(['client_id' => $client->id, 'commande_id' => $commande->id, 'montant' => 217800, 'type_affaire' => 2]);

        $bons = [];
        foreach ($quantites as $qte) {
            $livraison = Livraison::factory()->create([
                'client_id' => $client->id, 'livreur_id' => $livreur->id, 'detail_commande_id' => $ligne->id, 'detail_livraison_id' => 0,
                'adresse_livraison_id' => $adresse->id, 'vehicule_id' => null, 'type_livraison_id' => 1, 'provenance' => \Help::$COMMANDE,
                'date_livraison' => now(), 'qte' => $qte, 'etat_livraison' => \Help::$LIVRAISON_LIVREE, 'accepte' => 1, 'statut' => \Help::$STATUT_ACTIF,
            ]);
            $bons[] = Enlevement::factory()->create([
                'fournisseur_id' => $fournisseur->id, 'livraison_id' => $livraison->id, 'produit_id' => $produit->id, 'livreur_id' => $livreur->id,
                'qte' => $qte, 'qte_servi' => $qte, 'prix_fournisseur' => 4000, 'facture_id' => null, 'fournisseur_validation' => now(),
            ]);
        }

        return [Commande::find($commande->id), $client, $bons];
    }

    private function avance(Client $client, float $montant): AvanceClient
    {
        return AvanceClient::create([
            'client_id' => $client->id, 'montant' => $montant, 'montant_consomme' => 0, 'statut' => AvanceClient::DISPONIBLE,
            'numero_recu' => 'RA-TEST-' . random_int(100, 999), 'date_depot' => now(),
        ]);
    }

    private function facturesDe(Commande $commande)
    {
        return Facture::where('service', 'COMMANDE')->where('service_id', $commande->id)->get();
    }

    public function test_l_avance_n_emet_aucune_facture_et_l_enlevement_en_emet_une_seule(): void
    {
        [$commande, $client, $bons] = $this->commandeLivree([100]);
        $this->assertEqualsWithDelta(1427800, $commande->montantAPayer(), 1);

        // L'avance de 912 385 s'impute : un règlement AV validé, AUCUNE facture.
        $this->avance($client, 912385);
        $resultat = Avances::imputerSurCommande($commande, null);
        $this->assertEqualsWithDelta(912385, $resultat['impute'], 0.01);
        $this->assertCount(0, $this->facturesDe($commande), "Un règlement n'émet plus de facture : elle naît à l'enlèvement.");

        // L'enlèvement : UNE facture, pour toute la quantité servie, déjà payée à hauteur de l'avance.
        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureEnlevement', $bons[0]->id))
            ->assertRedirect(route('orders.BECommande', ['numero' => $commande->numero]));

        $factures = $this->facturesDe($commande);
        $this->assertCount(1, $factures, 'Un enlèvement, une facture DGI.');
        $facture = $factures->first();
        $this->assertSame((int) $facture->id, (int) $bons[0]->fresh()->facture_id);
        $this->assertEqualsWithDelta(1427800, (float) $facture->montant, 1, 'La facture porte toute la quantité servie.');

        $av = Paiement::where('service', 'COMMANDE')->where('service_id', $commande->id)->where('numero_recu', 'like', 'AV-%')->first();
        $this->assertNotNull($av);
        $this->assertSame((int) $facture->id, (int) $av->fresh()->facture_id, "Le règlement de l'avance est rattaché à la facture d'enlèvement.");
        $this->assertEqualsWithDelta(912385, $facture->montantDejaRegle(), 1);
        $this->assertEqualsWithDelta(515415, $facture->resteAEncaisser(), 1, 'Le reste de la facture est le reliquat après avance.');
        $this->assertEqualsWithDelta(1427800, FacturationCommande::montantDejaFacture($commande), 1);
        $this->assertEqualsWithDelta(515415, $commande->fresh()->montantRestantDu(), 1);
    }

    /** Le guichet des créances : Total = la facture (toute la commande), Reste = le reliquat, et la mention de l'avance. */
    public function test_le_guichet_des_creances_montre_le_reliquat_et_l_avance(): void
    {
        [$commande, $client, $bons] = $this->commandeLivree([100]);
        $this->avance($client, 912385);
        Avances::imputerSurCommande($commande, null);
        $admin = $this->compte(\Help::$USER_ADMIN);
        $this->actingAs($admin)->post(route('orders.genererFactureEnlevement', $bons[0]->id))->assertRedirect();
        $facture = $this->facturesDe($commande)->first();

        $admin->agence_id = \App\Models\Agence::value('id');
        $admin->save();
        $html = $this->actingAs($admin)->get('/clients-terme/paiements')->assertOk()->getContent();
        $debut = strpos($html, 'value="' . $facture->numero . '"');
        $this->assertNotFalse($debut, 'La facture doit être proposée au guichet.');
        $ligne = substr($html, $debut, 4000);
        $this->assertStringContainsString('data-reste="515415"', $ligne, 'Le reste proposé au caissier est le reliquat.');
        $this->assertStringContainsString('1 427 800', $ligne, 'Le total de la facture est celui de la commande.');
        $this->assertStringContainsString('515 415', $ligne);
        $this->assertStringContainsString('js-avance-imputee', $ligne, "La mention de l'avance manque sous la facture.");
        $this->assertStringContainsString('912 385', $ligne);
    }

    public function test_une_commande_entierement_reglee_d_avance_recoit_une_facture_soldee(): void
    {
        [$commande, $client, $bons] = $this->commandeLivree([60, 40]);
        $this->avance($client, 1427800);
        Avances::imputerSurCommande($commande, null);
        $this->assertCount(0, $this->facturesDe($commande));

        $admin = $this->compte(\Help::$USER_ADMIN);
        foreach ($bons as $bon) {
            $this->actingAs($admin)->post(route('orders.genererFactureEnlevement', $bon->id))->assertRedirect();
        }
        // Deux bons facturés séparément : deux factures d'enlèvement (60 % et 40 %), toutes deux couvertes par l'avance.
        $factures = $this->facturesDe($commande);
        $this->assertEqualsWithDelta(1427800, (float) $factures->sum('montant'), 1, 'La commande est facturée une seule fois au total.');
        foreach ($factures as $f) {
            $this->assertEqualsWithDelta(0, $f->resteAEncaisser(), 1, 'Chaque facture est soldée par l\'avance.');
        }
        $this->assertEqualsWithDelta(0, $commande->fresh()->montantRestantDu(), 1);
    }

    public function test_sans_avance_la_facture_d_enlevement_ne_change_pas(): void
    {
        [$commande, , $bons] = $this->commandeLivree([100]);
        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureEnlevement', $bons[0]->id))->assertRedirect();
        $this->assertEqualsWithDelta(1427800, (float) Facture::find($bons[0]->fresh()->facture_id)->montant, 1);
    }

    public function test_un_encaissement_en_agence_n_emet_plus_de_facture(): void
    {
        [$commande, $client] = $this->commandeLivree([100]);
        $p = Paiement::create([
            'client_id' => $client->id, 'code' => 'T-' . random_int(100000, 999999), 'libelle' => 'Acompte', 'montant_total' => 100000,
            'montant_restant' => 0, 'statut' => 1, 'service' => 'COMMANDE', 'service_id' => $commande->id, 'numero_recu' => 'RC-T-' . random_int(100, 999),
        ]);
        \App\Models\LignePaiement::create([
            'paiement_id' => $p->id, 'mode_paiement_id' => \App\Models\ModePaiement::first()->id, 'montant' => 100000, 'statut' => 1,
            'code_paiement' => $p->code, 'service' => 'COMMANDE', 'service_id' => $commande->id, 'date_paiement' => now(),
        ]);
        $this->assertCount(0, $this->facturesDe($commande), "Un encaissement validé n'émet plus de facture sur règlement.");
    }
}
