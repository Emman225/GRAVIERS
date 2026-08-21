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
 * L'état de TVA collectée distingue deux chiffres que la déclaration ne
 * confond pas : la taxe FACTURÉE et la taxe réellement ENCAISSÉE.
 */
class EtatTvaCollecteeTest extends TestCase
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
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /**
     * Une vente de 100 000 F HT, taxée à 18 %, réglée pour moitié.
     * Retourne [commande, tva facturée].
     */
    private function venteReglee(float $partReglee): array
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();

        // La fabrique tire des clés étrangères au hasard : on les annule, seul
        // le rattachement au client compte ici.
        $commande = Commande::factory()->create([
            'client_id'             => $client->id,
            'devis_id'              => null,
            'adresse_livraison_id'  => null,
            'mode_paiement_id'      => null,
            'type_livraison_id'     => null,
            'date_commande'         => now(),
            'montant_total'         => 100000,
            'remise'                => 0,
            'cout_livraison_client' => 0,
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

        // Net à payer = 100 000 + 18 000 = 118 000.
        $encaisse = 118000 * $partReglee;
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

        return [$commande, 18000.0];
    }

    public function test_l_ecran_repond(): void
    {
        $this->actingAs($this->admin())
            ->get(route('show.comptabilite.tvaCollectee'))
            ->assertOk()
            ->assertSee('TVA facturée')
            ->assertSee('TVA encaissée');
    }

    public function test_une_facture_soldee_a_sa_tva_entierement_encaissee(): void
    {
        [$commande] = $this->venteReglee(1.0);

        $ligne = $this->ligneDeLEtat($commande->numero);

        $this->assertSame(18000.0, $ligne->tva_facturee);
        $this->assertSame(18000.0, $ligne->tva_encaissee);
        $this->assertSame('Soldée', $ligne->solde);
    }

    public function test_une_facture_reglee_a_moitie_n_a_encaisse_que_la_moitie_de_sa_tva(): void
    {
        [$commande] = $this->venteReglee(0.5);

        $ligne = $this->ligneDeLEtat($commande->numero);

        $this->assertSame(18000.0, $ligne->tva_facturee);
        $this->assertSame(9000.0, $ligne->tva_encaissee);
        $this->assertSame('Partielle', $ligne->solde);
    }

    public function test_une_facture_impayee_ne_fait_encaisser_aucune_tva(): void
    {
        [$commande] = $this->venteReglee(0.0);

        $ligne = $this->ligneDeLEtat($commande->numero);

        $this->assertSame(18000.0, $ligne->tva_facturee);
        $this->assertSame(0.0, $ligne->tva_encaissee);
        $this->assertSame('Impayée', $ligne->solde);
    }

    public function test_la_periode_ecarte_les_factures_hors_bornes(): void
    {
        [$commande] = $this->venteReglee(1.0);

        $reponse = $this->actingAs($this->admin())->get(route('show.comptabilite.tvaCollectee', [
            'du' => now()->subMonths(3)->format('Y-m-d'),
            'au' => now()->subMonths(2)->format('Y-m-d'),
        ]));

        $numeros = collect($reponse->viewData('lignes'))->pluck('numero');
        $this->assertNotContains($commande->numero, $numeros);
    }

    /** La ligne de l'état correspondant à une facture donnée. */
    private function ligneDeLEtat(string $numero): object
    {
        $reponse = $this->actingAs($this->admin())
            ->get(route('show.comptabilite.tvaCollectee'))
            ->assertOk();

        $ligne = collect($reponse->viewData('lignes'))->firstWhere('numero', $numero);

        $this->assertNotNull($ligne, "La facture {$numero} est absente de l'état.");

        return $ligne;
    }
}
