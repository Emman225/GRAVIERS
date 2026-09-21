<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * OÙ EN EST LA LIVRAISON D'UNE LOCATION (10/09/2026).
 *
 * « La livraison de la location est terminée par le livreur mais dans le
 * compte client le statut est toujours en cours » : la location reste EN
 * COURS jusqu'au retour du matériel, c'est voulu ; ce qui manquait, c'est
 * l'état de la livraison elle-même, que le client voit désormais.
 */
class EtatDeLivraisonDeLaLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function location(bool $retrait, string $etatCourse, int $accepte = Livraison::ACCEPTEE): array
    {
        TypeUser::firstOrCreate(['id' => \Help::$USER_CLIENT], ['nom' => 'Client', 'statut' => 1]);
        $compte  = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();

        $location = Location::create([
            'numero' => 'L' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => 50000,
            'etat_location' => \Help::$LOCATION_EN_COURS, 'statut' => 1, 'remise' => 0,
            'cout_livraison_client' => 0, 'est_livrable' => $retrait ? 0 : 1,
        ]);
        $detail = DetailLocation::create([
            'produit_id' => $produit->id, 'location_id' => $location->id, 'qte' => 1,
            'debut' => now()->toDateString(), 'fin' => now()->addDay()->toDateString(),
            'prix' => 50000, 'nombre_jour' => 1, 'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);
        $livraison = new Livraison();
        $livraison->forceFill([
            'numero' => 'CL' . random_int(100000, 999999), 'client_id' => $client->id,
            'detail_commande_id' => $detail->id, 'provenance' => \Help::$LOCATION,
            'date_livraison' => now()->toDateString(), 'qte' => 1, 'etat_livraison' => $etatCourse,
            'accepte' => $accepte, 'livre_par' => $retrait ? 2 : 1, 'statut' => \Help::$STATUT_ACTIF,
            'cout_livraison' => 0,
        ])->save();

        return [$location->fresh(), $livraison, $compte, $detail];
    }

    public function test_une_location_livree_le_dit_au_client(): void
    {
        [$location, , $compte] = $this->location(false, \Help::$LIVRAISON_LIVREE);

        $etat = $location->etatLivraison();
        $this->assertSame('LIVREE', $etat['code']);
        $this->assertStringStartsWith('Livrée le ', $etat['libelle']);
        $this->assertSame('EN COURS', $location->etatLibelle(), 'La location reste EN COURS jusqu\'au retour.');

        foreach (['/mon-compte', '/detail-de-location-' . $location->id] as $url) {
            $this->actingAs($compte)->get($url)->assertOk()
                ->assertSee('js-etat-livraison-location', false)
                ->assertSee('Livrée le');
        }
    }

    public function test_un_retrait_servi_et_une_livraison_en_cours(): void
    {
        [$location] = $this->location(true, \Help::$LIVRAISON_LIVREE);
        $this->assertSame('RETIREE', $location->etatLivraison()['code']);

        [$location] = $this->location(false, \Help::$LIVRAISON_EN_ATTENTE);
        $this->assertSame('EN_LIVRAISON', $location->etatLivraison()['code']);

        [$location] = $this->location(false, \Help::$LIVRAISON_EN_ATTENTE, 2);
        $this->assertSame('A_CONFIRMER', $location->etatLivraison()['code']);
    }

    public function test_la_cloture_depuis_le_site_ne_tombe_plus_dans_les_demandes_de_livraison(): void
    {
        $livreur = Livreur::whereNotNull('user_id')->whereHas('user')->first();
        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur avec un compte.');
        }
        [$location, $livraison, , $detail] = $this->location(false, \Help::$LIVRAISON_EN_ATTENTE);
        $livraison->forceFill(['livreur_id' => $livreur->id])->save();

        URL::forceRootUrl('');
        $this->actingAs($livreur->user)->post('/validation-livraison', ['code' => $livraison->numero])
            ->assertRedirect();

        $this->assertSame(\Help::$LIVRAISON_LIVREE, $livraison->fresh()->etat_livraison);
        $this->assertSame(\Help::$LOCATION_EN_COURS, $location->fresh()->etatLibelle());
        $this->assertSame(\Help::$LOCATION_EN_COURS, $detail->fresh()->etat_location, 'La ligne livrée passe EN COURS.');
        $this->assertSame('LIVREE', $location->fresh()->etatLivraison()['code']);
    }
}
