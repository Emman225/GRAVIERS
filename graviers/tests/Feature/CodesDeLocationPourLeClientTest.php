<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Location;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LES CODES D'UNE LOCATION POUR LE CLIENT (10/09/2026), comme pour les ventes :
 * code de livraison quand un livreur vient, bon d'enlèvement quand le client
 * retire lui-même — sur Mon compte et sur le détail de la location.
 */
class CodesDeLocationPourLeClientTest extends TestCase
{
    use DatabaseTransactions;

    /** Une location avec une course acceptée et son bon. Retourne [location, livraison, compte]. */
    private function locationAvecCourse(bool $retrait): array
    {
        TypeUser::firstOrCreate(['id' => \Help::$USER_CLIENT], ['nom' => 'Client', 'statut' => 1]);
        $compte  = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();
        $fournisseur = Fournisseur::first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur en base.');
        }

        $location = Location::create([
            'numero'                => 'L' . random_int(100000, 999999),
            'client_id'             => $client->id,
            'montant_total'         => 50000,
            'etat_location'         => \Help::$LOCATION_EN_COURS,
            'statut'                => 1,
            'remise'                => 0,
            'cout_livraison_client' => 0,
            'est_livrable'          => $retrait ? 0 : 1,
        ]);
        $detail = DetailLocation::create([
            'produit_id' => $produit->id, 'location_id' => $location->id, 'qte' => 1,
            'debut' => now()->toDateString(), 'fin' => now()->addDay()->toDateString(),
            'prix' => 50000, 'nombre_jour' => 1, 'etat_location' => \Help::$LOCATION_EN_COURS,
        ]);

        $livraison = new Livraison();
        $livraison->forceFill([
            'numero'              => 'CL' . random_int(100000, 999999),
            'client_id'           => $client->id,
            // Comme la validation d'une location : la course porte l'id de la LIGNE.
            'detail_commande_id'  => $detail->id,
            'provenance'          => \Help::$LOCATION,
            'date_livraison'      => now()->toDateString(),
            'qte'                 => 1,
            'etat_livraison'      => \Help::$LIVRAISON_EN_ATTENTE,
            'accepte'             => Livraison::ACCEPTEE,
            'statut'              => \Help::$STATUT_ACTIF,
        ])->save();
        $bon = new Enlevement();
        $bon->forceFill([
            'fournisseur_id' => $fournisseur->id, 'livraison_id' => $livraison->id, 'produit_id' => $produit->id,
            'qte' => 1, 'code_enleve' => 'ENL' . random_int(10000, 99999), 'statut' => \Help::$STATUT_ACTIF,
        ])->save();

        return [$location->fresh(), $livraison->fresh(), $compte];
    }

    public function test_une_location_livree_montre_le_code_de_livraison_et_pas_le_bon(): void
    {
        [$location, $livraison, $compte] = $this->locationAvecCourse(false);
        $this->assertCount(1, $location->coursesAcceptees());

        foreach (['/mon-compte', '/detail-de-location-' . $location->id] as $url) {
            $html = $this->actingAs($compte)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString($livraison->numero, $html, $url);
            $this->assertStringContainsString('Code de livraison', $html, $url);
            $this->assertStringNotContainsString($livraison->enlevement->code_enleve, $html, $url . ' montre le bon alors qu\'un livreur vient.');
        }
    }

    public function test_un_retrait_par_le_client_montre_le_bon_et_pas_le_code(): void
    {
        [$location, $livraison, $compte] = $this->locationAvecCourse(true);
        $this->assertTrue($location->estRetraitSurPlace());

        foreach (['/mon-compte', '/detail-de-location-' . $location->id] as $url) {
            $html = $this->actingAs($compte)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString($livraison->enlevement->code_enleve, $html, $url);
            $this->assertStringContainsString('Bon d&#039;enl', $html, $url);
            $this->assertStringContainsString('location n° ' . $location->numero, urldecode($html), $url . ' : le message WhatsApp doit nommer la location.');
        }
    }
}
