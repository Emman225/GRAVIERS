<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\AdresseLivraison;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Un bon servi PARTIELLEMENT se facture pour ce qu'il a réellement livré,
 * ligne par ligne, sans attendre le reliquat ni les autres lignes.
 *
 * Éligibilité retenue avec le client : retrait sur place → la validation du
 * fournisseur suffit ; commande livrée → on attend la clôture de la livraison.
 */
class FactureBonPartielTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_CLIENT, 'Client'],
            [\Help::$USER_FOURNISSEUR, 'Fournisseur'],
            [\Help::$USER_LIVREUR, 'Livreur'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18, 'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100, 'cout_livraison_min' => 5000,
        ]);
    }

    private function compte(int $type): User
    {
        return User::factory()->create([
            'type_user_id' => $type,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /**
     * Une commande, sa ligne, sa livraison et son bon d'enlèvement.
     *
     * @return array{0: Commande, 1: Enlevement}
     */
    private function bon(
        float $qte,
        ?float $qteServi,
        string $etatLivraison,
        bool $livrable,
        bool $valideParFournisseur = true
    ): array {
        $client      = Client::factory()->create(['user_id' => $this->compte(\Help::$USER_CLIENT)->id]);
        $fournisseur = Fournisseur::factory()->create(['user_id' => $this->compte(\Help::$USER_FOURNISSEUR)->id]);
        $livreur     = Livreur::factory()->create(['user_id' => $this->compte(\Help::$USER_LIVREUR)->id]);
        // DetailCommande::liste() ne retient que les produits de type VENTE et
        // les lignes actives : sans cela, la commande apparait vide a l'ecran.
        $produit     = Produit::factory()->create(['type_affaire' => \Help::$VENTE]);

        $commande = Commande::factory()->create([
            'client_id'             => $client->id,
            'devis_id'              => null,
            'adresse_livraison_id'  => null,
            'mode_paiement_id'      => null,
            'type_livraison_id'     => null,
            'date_commande'         => now(),
            'montant_total'         => 100000,
            'remise'                => 0,
            'est_livrable'          => $livrable ? 1 : 0,
            'cout_livraison_client' => 0,
        ]);

        $ligne = DetailCommande::factory()->create([
            'commande_id' => $commande->id,
            'produit_id'  => $produit->id,
            'qte'         => $qte,
            'prix'        => 10000,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        // Livraison::liste() joint adresse_livraison et type_livraison en INNER
        // JOIN : sans elles, la ligne n'apparaîtrait pas du tout sur l'écran.
        // On réutilise une adresse existante — la fabrique tire un pays au hasard
        // et viole la clé étrangère.
        $adresse = AdresseLivraison::first();
        if (!$adresse) {
            $this->markTestSkipped("Aucune adresse de livraison en base.");
        }

        $livraison = Livraison::factory()->create([
            'client_id'            => $client->id,
            'livreur_id'           => $livreur->id,
            'detail_commande_id'   => $ligne->id,
            'detail_livraison_id'  => 0,
            'adresse_livraison_id' => $adresse->id,
            'vehicule_id'          => null,
            'type_livraison_id'    => 1,
            'provenance'           => \Help::$COMMANDE,
            'date_livraison'       => now(),
            'qte'                  => $qte,
            'etat_livraison'       => $etatLivraison,
            // Livraison::liste() ne retient que les livraisons acceptees et actives.
            'accepte'              => 1,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        $enlevement = Enlevement::factory()->create([
            'fournisseur_id'         => $fournisseur->id,
            'livraison_id'           => $livraison->id,
            'produit_id'             => $produit->id,
            'livreur_id'             => $livreur->id,
            'qte'                    => $qte,
            'qte_servi'              => $qteServi,
            'prix_fournisseur'       => 4000,
            'facture_id'             => null,
            'fournisseur_validation' => $valideParFournisseur ? now() : null,
        ]);

        return [$commande, $enlevement];
    }

    // ------------------------------------------------------------ éligibilité

    public function test_retrait_sur_place_valide_est_facturable_sans_attendre_la_livraison(): void
    {
        [, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_EN_TRAITEMENT, livrable: false);

        $this->assertTrue($bon->estFacturable());
        $this->assertTrue($bon->estServiPartiellement());
    }

    public function test_commande_livree_attend_la_cloture_de_la_livraison(): void
    {
        [, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_EN_COURS, livrable: true);

        $this->assertFalse($bon->estFacturable());
    }

    public function test_commande_livree_devient_facturable_une_fois_livree(): void
    {
        [, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);

        $this->assertTrue($bon->estFacturable());
    }

    public function test_retrait_non_valide_par_le_fournisseur_n_est_pas_facturable(): void
    {
        [, $bon] = $this->bon(
            qte: 10, qteServi: null, etatLivraison: \Help::$LIVRAISON_EN_TRAITEMENT,
            livrable: false, valideParFournisseur: false
        );

        $this->assertFalse($bon->estFacturable());
    }

    public function test_un_bon_deja_facture_ne_se_refacture_pas(): void
    {
        [$commande, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);

        $facture = Facture::create([
            'numero'     => \Help::genererNumeroUnique('facture'),
            'user_id'    => $this->compte(\Help::$USER_ADMIN)->id,
            'statut'     => 2,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'client_id'  => $commande->client_id,
        ]);
        $bon->update(['facture_id' => $facture->id]);

        $this->assertFalse($bon->fresh()->estFacturable());
    }

    // ------------------------------------------------------ génération réelle

    public function test_la_facture_porte_sur_la_quantite_servie_et_non_commandee(): void
    {
        [$commande, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureEnlevement', $bon->id))
            ->assertRedirect(route('orders.BECommande', ['numero' => $commande->numero]));

        $bon->refresh();
        $this->assertNotNull($bon->facture_id, 'Le bon doit être rattaché à la facture créée.');

        // 8 servies × 10 000 = 80 000 HT, + 18 % = 94 400.
        $facture = Facture::find($bon->facture_id);
        $this->assertEqualsWithDelta(94400, (float) $facture->montant, 1);

        // Créée en attente : la certification DGI reste une action distincte.
        $this->assertSame('pending', $facture->fne_status);
    }

    public function test_la_remise_n_est_pas_taxee(): void
    {
        [$commande, $bon] = $this->bon(qte: 10, qteServi: 10, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);

        // 100 000 HT, 10 000 de remise. La TVA de la commande s'assied sur le HT
        // remise déduite : (100 000 − 10 000) × 18 % = 16 200.
        $commande->update(['remise' => 10000, 'cout_livraison_client' => 0]);
        \App\Models\TvaCommande::create([
            'client_id'    => $commande->client_id,
            'commande_id'  => $commande->id,
            'montant'      => 16200,
            'type_affaire' => \Help::$VENTE,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureEnlevement', $bon->id));

        $bon->refresh();
        $facture = Facture::find($bon->facture_id);

        // 100 000 + 16 200 − 10 000 = 106 200, exactement le dû de la commande.
        //
        // La TVA était calculée sur le HT BRUT : 18 000 au lieu de 16 200, soit
        // 108 000 facturés pour 106 200 dus. L'écart valait 18 % de la remise et
        // faisait apparaître au client un « versé en trop » qui n'existait pas.
        $this->assertEqualsWithDelta(106200, (float) $facture->montant, 1);
        $this->assertEqualsWithDelta((float) $commande->fresh()->montantAPayer(),
                                     (float) $facture->montant, 1);
    }

    public function test_une_remise_superieure_au_premier_bon_reste_juste(): void
    {
        // Le cas de la commande 107 : la remise (114) dépassait le HT du premier
        // bon (100). Ici, mêmes proportions à l'échelle de la fabrique.
        [$commande, $premier] = $this->bon(qte: 1, qteServi: 1, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);
        $second = $this->bonSupplementaire($commande, qte: 1, qteServi: 1, etat: \Help::$LIVRAISON_LIVREE);

        // 20 000 HT en deux lignes, 11 400 de remise, 6 000 de livraison.
        $commande->update(['remise' => 11400, 'cout_livraison_client' => 6000]);
        \App\Models\TvaCommande::create([
            'client_id'    => $commande->client_id,
            'commande_id'  => $commande->id,
            'montant'      => (20000 - 11400) * 0.18,   // 1 548
            'type_affaire' => \Help::$VENTE,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $admin = $this->compte(\Help::$USER_ADMIN);
        $this->actingAs($admin)->post(route('orders.genererFactureEnlevement', $premier->id));
        $this->actingAs($admin)->post(route('orders.genererFactureEnlevement', $second->id));

        $total = (float) Facture::where('service', \Help::$COMMANDE)
            ->where('service_id', $commande->id)->sum('montant');

        // Deux factures, dont le total doit retomber sur le dû : 16 148.
        //
        // Retrancher la remise ENTIÈRE de la première tranche donnait 16 400 :
        // la part de remise qui dépassait le HT du premier bon était perdue, et
        // la TVA du second bon repartait sur une base pleine.
        $this->assertEqualsWithDelta((float) $commande->fresh()->montantAPayer(), $total, 1);
        $this->assertEqualsWithDelta(16148, $total, 1);
    }

    public function test_un_bon_non_eligible_est_refuse_meme_par_l_url(): void
    {
        [, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_EN_COURS, livrable: true);

        // Flasher intercepte la cle « error » pour la rejouer en toast : elle
        // n'est plus lisible dans la session. Ce qui compte se verifie en base.
        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureEnlevement', $bon->id))
            ->assertRedirect();

        $this->assertNull(
            $bon->fresh()->facture_id,
            "Un bon non eligible ne doit etre rattache a aucune facture."
        );
    }

    // -------------------------------------------- facturer toute la commande

    /** Ajoute un second bon à une commande déjà créée. */
    private function bonSupplementaire(Commande $commande, float $qte, ?float $qteServi, string $etat): Enlevement
    {
        $fournisseur = Fournisseur::factory()->create(['user_id' => $this->compte(\Help::$USER_FOURNISSEUR)->id]);
        $livreur     = Livreur::factory()->create(['user_id' => $this->compte(\Help::$USER_LIVREUR)->id]);
        $produit     = Produit::factory()->create(['type_affaire' => \Help::$VENTE]);

        $ligne = DetailCommande::factory()->create([
            'commande_id' => $commande->id,
            'produit_id'  => $produit->id,
            'qte'         => $qte,
            'prix'        => 10000,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $livraison = Livraison::factory()->create([
            'client_id'            => $commande->client_id,
            'livreur_id'           => $livreur->id,
            'detail_commande_id'   => $ligne->id,
            'detail_livraison_id'  => 0,
            'adresse_livraison_id' => AdresseLivraison::first()->id,
            'vehicule_id'          => null,
            'type_livraison_id'    => 1,
            'provenance'           => \Help::$COMMANDE,
            'date_livraison'       => now(),
            'qte'                  => $qte,
            'etat_livraison'       => $etat,
            'accepte'              => 1,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        return Enlevement::factory()->create([
            'fournisseur_id'         => $fournisseur->id,
            'livraison_id'           => $livraison->id,
            'produit_id'             => $produit->id,
            'livreur_id'             => $livreur->id,
            'qte'                    => $qte,
            'qte_servi'              => $qteServi,
            'prix_fournisseur'       => 4000,
            'facture_id'             => null,
            'fournisseur_validation' => now(),
        ]);
    }

    public function test_facturer_la_totalite_reunit_tous_les_bons_facturables(): void
    {
        [$commande, $premier] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);
        $second = $this->bonSupplementaire($commande, qte: 5, qteServi: 5, etat: \Help::$LIVRAISON_LIVREE);

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureTotale', $commande->id))
            ->assertRedirect(route('orders.BECommande', ['numero' => $commande->numero]));

        $premier->refresh();
        $second->refresh();

        $this->assertNotNull($premier->facture_id);
        $this->assertNotNull($second->facture_id);
        // Une seule facture, pas une par bon.
        $this->assertSame($premier->facture_id, $second->facture_id);

        // (8 + 5) × 10 000 = 130 000 HT, + 18 % = 153 400.
        $this->assertEqualsWithDelta(153400, (float) Facture::find($premier->facture_id)->montant, 1);
    }

    public function test_facturer_la_totalite_laisse_de_cote_les_bons_non_eligibles(): void
    {
        [$commande, $facturable] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);
        $enCours = $this->bonSupplementaire($commande, qte: 5, qteServi: 5, etat: \Help::$LIVRAISON_EN_COURS);

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureTotale', $commande->id));

        $this->assertNotNull($facturable->refresh()->facture_id);
        $this->assertNull(
            $enCours->refresh()->facture_id,
            "Un bon dont la livraison n'est pas close ne doit pas être emporté."
        );
    }

    public function test_facturer_la_totalite_sans_bon_eligible_ne_cree_rien(): void
    {
        [$commande] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_EN_COURS, livrable: true);

        $avant = Facture::count();

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->post(route('orders.genererFactureTotale', $commande->id))
            ->assertRedirect();

        $this->assertSame($avant, Facture::count(), 'Aucune facture ne doit être créée à vide.');
    }

    public function test_l_ecran_propose_le_bouton_sur_un_bon_partiel(): void
    {
        [$commande, $bon] = $this->bon(qte: 10, qteServi: 8, etatLivraison: \Help::$LIVRAISON_LIVREE, livrable: true);

        $this->actingAs($this->compte(\Help::$USER_ADMIN))
            ->get('/orders-be/' . $commande->numero)
            ->assertOk()
            ->assertSee('Facture DGI')
            ->assertSee('Partiel')
            ->assertSee('factureBon' . $bon->id, false)
            ->assertSee('Facturer la totalit', false);
    }
}
