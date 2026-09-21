<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DEUX COLONNES VIDES AU BACK-OFFICE DES DEMANDES DE LIVRAISON (10/09/2026) :
 * « Traité par » sur le détail (relation sur une colonne inexistante) et
 * « Description » sur les demandes traitées (lue sur la demande, saisie sur
 * ses lignes).
 */
class ColonnesDesDemandesDeLivraisonRempliesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_traite_par_et_description_se_lisent(): void
    {
        foreach ([[\Help::$USER_ADMIN, 'Admin'], [\Help::$USER_CLIENT, 'Client']] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }
        $gestionnaire = User::factory()->create(['type_user_id' => \Help::$USER_ADMIN, 'statut' => \Help::$STATUT_ACTIF, 'nom_prenoms' => 'KOUADIO Gestion']);
        $compte = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client = Client::factory()->create(['user_id' => $compte->id]);

        $demande = DemandeLivraison::create([
            'numero' => 'DL' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 10000,
            'etat_commande' => \Help::$COMMANDE_TERMINE, 'statut' => \Help::$STATUT_ACTIF,
        ]);
        $detail = DetailLivraison::create([
            'nom_produit' => 'Sable', 'qte' => 5, 'unite' => 'Tonne',
            'unite_produit_id' => \App\Models\UniteProduit::value('id'), 'description' => 'Sable fin lavé pour dalle',
            'demande_livraison_id' => $demande->id, 'etat_livraison' => \Help::$LIVRAISON_LIVREE, 'statut' => \Help::$STATUT_ACTIF,
        ]);
        Livraison::create([
            'numero' => 'CD' . random_int(100000, 999999), 'client_id' => $client->id,
            'detail_livraison_id' => $detail->id, 'provenance' => \Help::$LIVRAISON,
            'date_livraison' => date('Y-m-d'), 'qte' => 5, 'accepte' => 1, 'gestionnaire_id' => $gestionnaire->id,
            'etat_livraison' => \Help::$LIVRAISON_LIVREE, 'statut' => \Help::$STATUT_ACTIF, 'cout_livraison' => 0,
        ]);

        $detailPage = $this->actingAs($gestionnaire)->get('/detail-demande-livraison-' . $demande->id)->assertOk();
        $detailPage->assertSee('KOUADIO Gestion');

        $liste = $this->actingAs($gestionnaire)->get('/liste-demande-de-livraison-traitee')->assertOk();
        $liste->assertSee('Sable fin lavé pour dalle');
    }

    public function test_les_vues_ne_lisent_plus_les_mauvaises_sources(): void
    {
        $detail = file_get_contents(resource_path('views/gestionnaire/detailDemandeLivraison.blade.php'));
        $this->assertStringNotContainsString('$livraison->user?->nom_prenoms', $detail);
        $this->assertStringContainsString('$livraison->gestionnaire?->nom_prenoms', $detail);

        $liste = file_get_contents(resource_path('views/gestionnaire/demandeDeLivraisonTraitee.blade.php'));
        $this->assertStringContainsString('$detail->description', $liste);
    }
}
