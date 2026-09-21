<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DetailCommande;
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
 * L'ÉTAT D'AIRSI COLLECTÉ (10/09/2026), jumeau de l'état de TVA collectée :
 * l'acompte FACTURÉ (figé sur l'affaire) et l'acompte réellement ENCAISSÉ.
 */
class EtatAirsiCollecteTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_CLIENT, 'Client'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18, 'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100, 'cout_livraison_min' => 5000,
        ]);
        Configuration::first()->update(['taux_airsi' => 5]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /**
     * Une vente de 100 000 F HT, TVA 18 000, AIRSI 5 900 figé sur la commande
     * (5 % de 118 000) ; net à payer 123 900, réglé pour la part demandée.
     */
    private function venteReglee(float $partReglee, string $etat = 'EN ATTENTE'): Commande
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();

        $commande = Commande::factory()->create([
            'client_id'             => $client->id,
            'devis_id'              => null,
            'adresse_livraison_id'  => null,
            'mode_paiement_id'      => null,
            'type_livraison_id'     => null,
            'date_commande'         => now(),
            'etat_commande'         => $etat,
            'montant_total'         => 100000,
            'remise'                => 0,
            'cout_livraison_client' => 0,
            'tva_transport'         => 0,
            'airsi'                 => 5900,
        ]);

        DetailCommande::factory()->create([
            'commande_id' => $commande->id,
            'produit_id'  => $produit->id,
            'qte'         => 1,
            'prix'        => 100000,
        ]);

        TvaCommande::create([
            'client_id'    => $client->id,
            'commande_id'  => $commande->id,
            'montant'      => 18000,
            'type_affaire' => \Help::$VENTE,
        ]);

        $encaisse = 123900 * $partReglee;
        if ($encaisse > 0) {
            $mode = ModePaiement::firstOrCreate(
                ['libelle' => 'Espèces (test)'],
                ['statut' => \Help::$STATUT_ACTIF]
            );
            $paiement = Paiement::factory()->create([
                'client_id'     => $client->id,
                'devis_id'      => null,
                'service'       => 'COMMANDE',
                'service_id'    => $commande->id,
                'montant_total' => $encaisse,
                'statut'        => 1,
            ]);
            LignePaiement::factory()->create([
                'paiement_id'      => $paiement->id,
                'mode_paiement_id' => $mode->id,
                'service'          => 'COMMANDE',
                'service_id'       => $commande->id,
                'montant'          => $encaisse,
                'statut'           => 1,
            ]);
        }

        return $commande;
    }

    public function test_l_ecran_repond_et_figure_au_menu(): void
    {
        $this->actingAs($this->admin())
            ->get(route('show.comptabilite.airsiCollectee'))
            ->assertOk()
            ->assertSee('AIRSI facturé')
            ->assertSee('AIRSI encaissé')
            ->assertSee(route('show.comptabilite.airsiCollectee'));
    }

    public function test_une_facture_soldee_a_son_airsi_entierement_encaisse(): void
    {
        $ligne = $this->ligneDeLEtat($this->venteReglee(1.0)->numero);

        $this->assertSame(118000.0, $ligne->base);
        $this->assertSame(5900.0, $ligne->airsi_facture);
        $this->assertSame(123900.0, $ligne->net_a_payer);
        $this->assertSame(5900.0, $ligne->airsi_encaisse);
        $this->assertSame('Soldée', $ligne->solde);
        $this->assertFalse($ligne->anomalie);
    }

    public function test_une_facture_reglee_a_moitie_n_a_encaisse_que_la_moitie_de_son_airsi(): void
    {
        $ligne = $this->ligneDeLEtat($this->venteReglee(0.5)->numero);

        $this->assertSame(5900.0, $ligne->airsi_facture);
        $this->assertSame(2950.0, $ligne->airsi_encaisse);
        $this->assertSame('Partielle', $ligne->solde);
    }

    public function test_une_facture_impayee_ne_fait_encaisser_aucun_airsi(): void
    {
        $ligne = $this->ligneDeLEtat($this->venteReglee(0.0)->numero);

        $this->assertSame(5900.0, $ligne->airsi_facture);
        $this->assertSame(0.0, $ligne->airsi_encaisse);
        $this->assertSame('Impayée', $ligne->solde);
    }

    public function test_une_affaire_annulee_est_ecartee(): void
    {
        $annulee = $this->venteReglee(0.0, \Help::$AFFAIRE_ANNULEE);
        $abandonnee = $this->venteReglee(0.0, \Help::$COMMANDE_EN_ATTENTE_PAIEMENT);

        $numeros = collect($this->actingAs($this->admin())
            ->get(route('show.comptabilite.airsiCollectee'))
            ->viewData('lignes'))->pluck('numero');

        $this->assertNotContains($annulee->numero, $numeros);
        $this->assertNotContains($abandonnee->numero, $numeros);
    }

    public function test_la_periode_et_l_activite_filtrent(): void
    {
        $commande = $this->venteReglee(1.0);

        $horsPeriode = $this->actingAs($this->admin())->get(route('show.comptabilite.airsiCollectee', [
            'du' => now()->subMonths(3)->format('Y-m-d'),
            'au' => now()->subMonths(2)->format('Y-m-d'),
        ]));
        $this->assertNotContains($commande->numero, collect($horsPeriode->viewData('lignes'))->pluck('numero'));

        $locations = $this->actingAs($this->admin())
            ->get(route('show.comptabilite.airsiCollectee', ['service' => 'LOCATION']));
        $this->assertNotContains($commande->numero, collect($locations->viewData('lignes'))->pluck('numero'));
    }

    /** La ligne de l'état correspondant à une facture donnée. */
    private function ligneDeLEtat(string $numero): object
    {
        $reponse = $this->actingAs($this->admin())
            ->get(route('show.comptabilite.airsiCollectee'))
            ->assertOk();

        $ligne = collect($reponse->viewData('lignes'))->firstWhere('numero', $numero);

        $this->assertNotNull($ligne, "La facture {$numero} est absente de l'état.");

        return $ligne;
    }
}
