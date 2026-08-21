<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DetailCommande;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ce qu'on facture au client et ce qu'on verse au livreur sont deux réglages
 * indépendants. Cet état les confronte : il doit voir la course vendue à perte,
 * répartir le transport d'une commande livrée en plusieurs fois, et laisser de
 * côté le retrait sur place.
 */
class EtatBeneficesLivraisonsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_CLIENT, 'Client'],
            [\Help::$USER_LIVREUR, 'Livreur'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18, 'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100, 'cout_livraison_min' => 5000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    private function livreur(): Livreur
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_LIVREUR,
            'statut'       => \Help::$STATUT_ACTIF,
            'nom_prenoms'  => 'Livreur de test',
        ]);

        return Livreur::factory()->create(['user_id' => $compte->id]);
    }

    /**
     * Une commande livrable, son transport facturé, et ses livraisons.
     *
     * @param  array<array{qte: float, verse: float}>  $courses
     * @return array{0: Commande, 1: \Illuminate\Support\Collection<Livraison>}
     */
    private function commandeLivree(float $transport, array $courses, bool $livrable = true): array
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();
        $livreur = $this->livreur();

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
            'cout_livraison_client' => $transport,
        ]);

        $ligne = DetailCommande::factory()->create([
            'commande_id' => $commande->id,
            'produit_id'  => $produit->id,
            'qte'         => collect($courses)->sum('qte'),
            'prix'        => 100000,
        ]);

        $livraisons = collect($courses)->map(fn ($c) => Livraison::factory()->create([
            'client_id'          => $client->id,
            'livreur_id'         => $livreur->id,
            'detail_commande_id' => $ligne->id,
            'detail_livraison_id'=> 0,
            'adresse_livraison_id' => null,
            'vehicule_id'        => null,
            'type_livraison_id'  => null,
            'provenance'         => \Help::$COMMANDE,
            'date_livraison'     => now(),
            'qte'                => $c['qte'],
            'etat_livraison'     => 'LIVREE',
            'forfait_base'       => $c['verse'],
            'frais_km'           => 0,
            'cout_livraison'     => 0,
        ]));

        return [$commande, $livraisons];
    }

    /** Les lignes de l'état, sur une période large. */
    private function etat(): \Illuminate\Support\Collection
    {
        $reponse = $this->actingAs($this->admin())
            ->get(route('show.comptabilite.beneficesLivraisons', [
                'du' => now()->subYear()->format('Y-m-d'),
                'au' => now()->addYear()->format('Y-m-d'),
            ]))
            ->assertOk();

        return collect($reponse->viewData('lignes'));
    }

    public function test_l_ecran_repond(): void
    {
        $this->actingAs($this->admin())
            ->get(route('show.comptabilite.beneficesLivraisons'))
            ->assertOk()
            ->assertSee('Transport facturé')
            ->assertSee('Versé aux livreurs');
    }

    public function test_la_marge_est_le_facture_moins_le_verse(): void
    {
        [, $livraisons] = $this->commandeLivree(10000, [['qte' => 5, 'verse' => 4000]]);

        $ligne = $this->etat()->firstWhere('numero', $livraisons[0]->numero);

        $this->assertNotNull($ligne);
        $this->assertSame(10000.0, $ligne->facture);
        $this->assertSame(4000.0, $ligne->verse);
        $this->assertSame(6000.0, $ligne->marge);
        $this->assertFalse($ligne->perte);
    }

    public function test_une_course_vendue_a_perte_est_signalee(): void
    {
        // 2 000 F facturés au client, 5 000 F versés au livreur.
        [, $livraisons] = $this->commandeLivree(2000, [['qte' => 1, 'verse' => 5000]]);

        $ligne = $this->etat()->firstWhere('numero', $livraisons[0]->numero);

        $this->assertSame(-3000.0, $ligne->marge);
        $this->assertTrue($ligne->perte);
    }

    public function test_le_transport_se_repartit_au_prorata_des_quantites(): void
    {
        // 9 000 F de transport pour une commande livrée en deux fois, 1 et 2.
        [, $livraisons] = $this->commandeLivree(9000, [
            ['qte' => 1, 'verse' => 0],
            ['qte' => 2, 'verse' => 0],
        ]);

        $etat = $this->etat();
        $petite = $etat->firstWhere('numero', $livraisons[0]->numero);
        $grande = $etat->firstWhere('numero', $livraisons[1]->numero);

        $this->assertSame(3000.0, $petite->facture);
        $this->assertSame(6000.0, $grande->facture);
        // Le total réparti reste exactement ce que le client a payé.
        $this->assertSame(9000.0, $petite->facture + $grande->facture);
    }

    public function test_le_retrait_sur_place_est_exclu(): void
    {
        [, $livraisons] = $this->commandeLivree(5000, [['qte' => 1, 'verse' => 1000]], livrable: false);

        $numeros = $this->etat()->pluck('numero');

        $this->assertNotContains($livraisons[0]->numero, $numeros);
    }
}
