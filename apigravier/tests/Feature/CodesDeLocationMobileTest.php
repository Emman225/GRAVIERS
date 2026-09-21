<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE DÉTAIL D'UNE LOCATION PORTE SES CODES (10/09/2026), comme celui d'une
 * commande : code de livraison et bon d'enlèvement des courses acceptées, et
 * le mode de récupération pour que l'application montre le bon code.
 */
class CodesDeLocationMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_detail_renvoie_les_codes_et_le_mode_de_recuperation(): void
    {
        $client = Client::where('statut', 1)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client avec un compte.');
        }
        $location = Location::create([
            'numero' => 'L' . random_int(100000, 999999), 'client_id' => $client->id,
            'montant_total' => 50000, 'etat_location' => 'EN COURS', 'statut' => 1,
            'remise' => 0, 'cout_livraison_client' => 0, 'est_livrable' => 0,
        ]);
        $produitId = DB::table('produit')->value('id');
        $detailId = DB::table('detail_location')->insertGetId([
            'produit_id' => $produitId, 'location_id' => $location->id, 'qte' => 1,
            'debut' => now()->toDateString(), 'fin' => now()->addDay()->toDateString(),
            'prix' => 50000, 'nombre_jour' => 1, 'etat_location' => 'EN COURS',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $livraisonId = DB::table('livraison')->insertGetId([
            'numero' => 'CL' . random_int(100000, 999999), 'client_id' => $client->id,
            // Comme la validation d'une location : la course porte l'id de la LIGNE.
            'detail_commande_id' => $detailId, 'provenance' => 'LOCATION',
            'date_livraison' => now()->toDateString(), 'qte' => 1, 'etat_livraison' => 'EN ATTENTE',
            'accepte' => 1, 'statut' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $fournisseurId = DB::table('fournisseur')->value('id');
        if (!$fournisseurId || !$produitId) {
            $this->markTestSkipped('Il manque un fournisseur ou un produit.');
        }
        DB::table('enlevement')->insert([
            'fournisseur_id' => $fournisseurId, 'livraison_id' => $livraisonId, 'produit_id' => $produitId,
            'qte' => 1, 'code_enleve' => 'ENL77777', 'statut' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $retour = $this->postJson('/mon_gravier/details-location/' . $location->id, [
            'access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile',
        ])->assertOk()->json();

        $this->assertSame(200, $retour['code'], $retour['message'] ?? '');
        $this->assertTrue($retour['data']['retrait_sur_place']);
        $this->assertCount(1, $retour['data']['codes']);
        $this->assertSame('ENL77777', $retour['data']['codes'][0]['code_enlevement']);
        $this->assertNotEmpty($retour['data']['codes'][0]['code_livraison']);

        // Où en est le matériel (10/09/2026) : à retirer, puis retiré une fois la course livrée.
        $this->assertSame('A_RETIRER', $retour['data']['etat_livraison']['code']);
        DB::table('livraison')->where('id', $livraisonId)->update(['etat_livraison' => 'LIVREE']);
        $retour = $this->postJson('/mon_gravier/details-location/' . $location->id, [
            'access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile',
        ])->assertOk()->json();
        $this->assertSame('RETIREE', $retour['data']['etat_livraison']['code']);
        $this->assertStringStartsWith('Retirée le ', $retour['data']['etat_livraison']['libelle']);

        // La liste porte le même libellé.
        $liste = $this->postJson('/mon_gravier/liste-commande', [
            'access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile',
        ])->assertOk()->json();
        $mienne = collect($liste['data']['location'])->firstWhere('id', $location->id);
        $this->assertNotNull($mienne);
        $this->assertSame('RETIREE', $mienne['etat_livraison_code']);
    }
}
