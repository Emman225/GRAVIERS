<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE DÉTAIL D'UNE DEMANDE DE LIVRAISON PORTE LES CODES DE LIVRAISON (10/09/2026),
 * ligne par ligne, pour les courses acceptées — comme celui d'une commande.
 */
class CodeDeLivraisonDesDemandesMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chaque_ligne_porte_le_code_de_sa_course_acceptee(): void
    {
        $client = Client::where('statut', 1)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client avec un compte.');
        }
        // Les lignes ne sortent qu'avec leurs deux adresses jointes (DetailsLivraison::liste).
        $adresse = DB::table('adresse_livraison')->value('id');
        if (!$adresse) {
            $this->markTestSkipped('Aucune adresse de livraison en base.');
        }
        $demandeId = DB::table('demande_livraison')->insertGetId([
            'numero' => 'DL' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 10000,
            'adresse_livraison_pec_id' => $adresse, 'adresse_livraison_dest_id' => $adresse,
            'etat_commande' => 'EN TRAITEMENT', 'statut' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ligneId = DB::table('detail_livraison')->insertGetId([
            'nom_produit' => 'Sable', 'qte' => 5, 'unite' => 'Tonne', 'unite_produit_id' => DB::table('unite_produit')->value('id'),
            'description' => '', 'demande_livraison_id' => $demandeId, 'etat_livraison' => 'EN TRAITEMENT', 'statut' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $acceptee = 'CD' . random_int(100000, 999999);
        $enAttente = 'CX' . random_int(100000, 999999);
        foreach ([[$acceptee, 1], [$enAttente, 2]] as [$numero, $accepte]) {
            DB::table('livraison')->insert([
                'numero' => $numero, 'client_id' => $client->id, 'detail_livraison_id' => $ligneId, 'provenance' => 'LIVRAISON',
                'date_livraison' => now()->toDateString(), 'qte' => 5, 'accepte' => $accepte, 'etat_livraison' => 'EN ATTENTE',
                'statut' => 1, 'cout_livraison' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $retour = $this->postJson('/mon_gravier/details-demande-livraison/' . $demandeId, [
            'access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile',
        ])->assertOk()->json();

        $this->assertSame(200, $retour['code'], $retour['message'] ?? '');
        $ligne = collect($retour['data'])->firstWhere('id', $ligneId);
        $this->assertNotNull($ligne);
        $this->assertCount(1, $ligne['codes'], 'Seule la course acceptée porte un code.');
        $this->assertSame($acceptee, $ligne['codes'][0]['code_livraison']);
        $this->assertNull($ligne['codes'][0]['code_enlevement']);
    }
}
