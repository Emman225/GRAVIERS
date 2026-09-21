<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailDevis;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * SUPPRESSION D'UN DEVIS DEPUIS L'APPLICATION (10/09/2026) : depuis la liste
 * comme depuis le détail, le devis en attente s'archive ; un devis transformé
 * ou rattaché à une commande vivante est refusé, avec le motif.
 */
class SuppressionDevisMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): Client
    {
        $client = Client::where('statut', 1)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client avec un compte.');
        }

        return $client;
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
            'statut'     => 1,
        ]);
        $produit = Produit::first();
        if ($produit) {
            DetailDevis::create([
                'devis_id' => $devis->id, 'produit_id' => $produit->id,
                'qte' => 1, 'prix' => 100000, 'statut' => 1,
            ]);
        }

        return $devis;
    }

    private function supprimer(Client $client, Devis $devis)
    {
        return $this->postJson('/mon_gravier/supprimer-devis/' . $devis->id, [
            'access' => Crypt::encryptString((string) $client->user_id),
            'type'   => 'mobile',
        ])->assertOk()->json();
    }

    public function test_un_devis_en_attente_s_archive(): void
    {
        $client = $this->client();
        $devis  = $this->devisEnAttente($client);

        $retour = $this->supprimer($client, $devis);

        $this->assertSame(200, $retour['code'], $retour['message']);
        $this->assertNull(Devis::find($devis->id), 'Le devis doit être archivé (soft delete).');
        $this->assertSame((int) \Help::$STATUT_INACTIF, (int) Devis::withTrashed()->find($devis->id)->statut);
        $this->assertSame(0, DetailDevis::where('devis_id', $devis->id)->where('statut', 1)->count());
        // Il ne figure plus dans aucune des deux listes de l'application.
        foreach ([1, 2] as $statut) {
            $liste = $this->postJson('/mon_gravier/liste-devis', ['access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile', 'statut' => $statut])->assertOk();
            $this->assertStringNotContainsString($devis->numero, $liste->getContent());
        }
    }

    public function test_un_devis_transforme_ou_rattache_a_une_commande_est_refuse(): void
    {
        $client = $this->client();

        $passe = $this->devisEnAttente($client);
        $passe->update(['statut' => 2]);
        $retour = $this->supprimer($client, $passe);
        $this->assertSame(400, $retour['code']);
        $this->assertStringContainsString('transformé', $retour['message']);
        $this->assertSame(2, (int) Devis::find($passe->id)->statut);

        $rattache = $this->devisEnAttente($client);
        $commande = new Commande();
        $commande->numero = (string) random_int(100000, 999999);
        $commande->client_id = $client->id;
        $commande->devis_id = $rattache->id;
        $commande->etat_commande = 'EN ATTENTE';
        $commande->montant_total = 100000;
        $commande->statut = 1;
        $commande->save();
        $retour = $this->supprimer($client, $rattache);
        $this->assertSame(400, $retour['code']);
        $this->assertStringContainsString($commande->numero, $retour['message']);
        $this->assertSame(1, (int) Devis::find($rattache->id)->statut);

        // Une commande abandonnée sur la passerelle ne retient pas le devis.
        $commande->etat_commande = \Help::$COMMANDE_EN_ATTENTE_PAIEMENT;
        $commande->save();
        $retour = $this->supprimer($client, $rattache);
        $this->assertSame(200, $retour['code'], $retour['message']);
    }
}
