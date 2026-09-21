<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailDevis;
use App\Models\Devis;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE CLIENT SUPPRIME UN DEVIS EN ATTENTE (10/09/2026), depuis « Mon compte →
 * Mes devis » et « Mes devis en attente ». Le devis est archivé, jamais
 * effacé ; un devis transformé en commande ne se supprime plus ; le devis
 * d'un autre client est hors d'atteinte.
 */
class ClientSupprimeSonDevisTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): Client
    {
        $user = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        return Client::factory()->create(['user_id' => $user->id]);
    }

    private function devisEnAttente(Client $client): Devis
    {
        $devis = Devis::create([
            'numero'     => (string) random_int(100000, 999999),
            'client_id'  => $client->id,
            'libelle'    => 'Devis de recette',
            'montant'    => 100000,
            'montant_ht' => 100000,
            'tva'        => 18000,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);
        DetailDevis::create([
            'devis_id'   => $devis->id,
            'produit_id' => Produit::factory()->create()->id,
            'qte'        => 1,
            'prix'       => 100000,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        return $devis;
    }

    public function test_le_bouton_est_propose_sur_mon_compte_et_sur_mes_devis(): void
    {
        $client = $this->client();
        $devis  = $this->devisEnAttente($client);
        $route  = route('devis.supprimerDevis', $devis);

        $this->actingAs(User::find($client->user_id))->get(route('client.monCompte'))
            ->assertOk()->assertSee($route, false)->assertSee('js-delete-form', false);
        $this->actingAs(User::find($client->user_id))->get(route('client.listeDevis'))
            ->assertOk()->assertSee($route, false)->assertSee('Supprimer le devis');
    }

    public function test_le_devis_est_archive_avec_ses_lignes(): void
    {
        $client = $this->client();
        $devis  = $this->devisEnAttente($client);

        $this->actingAs(User::find($client->user_id))
            ->delete(route('devis.supprimerDevis', $devis))
            ->assertRedirect();

        $this->assertSoftDeleted('devis', ['id' => $devis->id]);
        $this->assertSame((int) \Help::$STATUT_INACTIF, (int) Devis::withTrashed()->find($devis->id)->statut);
        $this->assertSame(0, DetailDevis::where('devis_id', $devis->id)->where('statut', \Help::$STATUT_ACTIF)->count());

        // Il ne figure plus dans « Mes devis en attente ».
        $this->actingAs(User::find($client->user_id))->get(route('client.monCompte'))
            ->assertOk()->assertDontSee(route('devis.supprimerDevis', $devis), false);
    }

    public function test_un_devis_transforme_en_commande_ne_se_supprime_plus(): void
    {
        $client = $this->client();
        $devis  = $this->devisEnAttente($client);
        $devis->update(['statut' => 2]);

        $this->actingAs(User::find($client->user_id))
            ->delete(route('devis.supprimerDevis', $devis))
            ->assertRedirect();

        $this->assertNull(Devis::find($devis->id)->deleted_at);
        $this->assertSame(2, (int) Devis::find($devis->id)->statut);
    }

    public function test_un_devis_rattache_a_une_commande_vivante_ne_se_supprime_plus(): void
    {
        $client = $this->client();
        $devis  = $this->devisEnAttente($client);
        Commande::factory()->create([
            'client_id'            => $client->id,
            'devis_id'             => $devis->id,
            'adresse_livraison_id' => null,
            'mode_paiement_id'     => null,
            'type_livraison_id'    => null,
            'etat_commande'        => \Help::$COMMANDE_EN_ATTENTE,
        ]);

        $this->assertFalse($devis->fresh()->supprimable());
        $this->actingAs(User::find($client->user_id))
            ->delete(route('devis.supprimerDevis', $devis))
            ->assertRedirect();
        $this->assertNull(Devis::find($devis->id)->deleted_at);

        // Une commande abandonnée sur la passerelle ne retient pas le devis.
        Commande::where('devis_id', $devis->id)->update(['etat_commande' => \Help::$COMMANDE_EN_ATTENTE_PAIEMENT]);
        $this->assertTrue($devis->fresh()->supprimable());
    }

    public function test_le_devis_d_un_autre_client_est_hors_d_atteinte(): void
    {
        $proprietaire = $this->client();
        $devis        = $this->devisEnAttente($proprietaire);
        $intrus       = $this->client();

        $this->actingAs(User::find($intrus->user_id))
            ->delete(route('devis.supprimerDevis', $devis))
            ->assertNotFound();

        $this->assertNull(Devis::find($devis->id)->deleted_at);
    }
}
