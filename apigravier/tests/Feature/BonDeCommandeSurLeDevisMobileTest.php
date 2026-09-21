<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LE NUMÉRO DE BON DE COMMANDE INTERNE SUR LE DEVIS (09/09/2026).
 *
 * L'application le demande à l'entreprise sur le même écran que pour la
 * commande ; le serveur l'exige pour un devis de VENTE, le fige sur le devis
 * (colonne devis.numero_bon_commande) et le renvoie avec le détail, pour qu'il
 * figure devant chaque désignation du document.
 */
class BonDeCommandeSurLeDevisMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function demanderDevis(Client $client, array $supplement = [])
    {
        $produit = Produit::where('type_affaire', \Help::$VENTE)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit de vente actif.');
        }

        return $this->postJson('/mon_gravier/enregistrer-devis', array_merge([
            'access'          => Crypt::encryptString((string) $client->user_id),
            'type'            => 'mobile',
            'libelle'         => 'Devis de recette',
            'montantHt'       => (float) $produit->prix_moyen,
            'coutReduction'   => 0,
            'montantTva'      => 0,
            'coutLivraison'   => 0,
            'meFaireLivre'    => 0,
            'lignes'          => [[
                'produit_id' => $produit->id,
                'qte'        => 1,
                'prix'       => (float) $produit->prix_moyen,
                'nbreJours'  => 1,
                'dateDebut'  => null,
                'dateDeFin'  => null,
            ]],
            'total'           => (float) $produit->prix_moyen,
            'modePaiement'    => 3,
            'moyenPaiement'   => 0,
            'typeLivraison'   => 1,
            'adresseLivraison' => null,
            'dateLivraison'   => now()->addDay()->toDateString(),
            'note'            => '',
            'service'         => 'VENTE',
            'long'            => -3.99,
            'lat'             => 5.35,
        ], $supplement));
    }

    public function test_une_entreprise_sans_numero_de_bon_est_refusee(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise avec un compte.');
        }
        $avant = Devis::count();

        $reponse = $this->demanderDevis($client, ['numero_bc' => '  ']);

        $reponse->assertOk();
        $this->assertSame(400, $reponse->json('code'), $reponse->json('message'));
        $this->assertStringContainsString('bon de commande', strtolower((string) $reponse->json('message')));
        $this->assertSame($avant, Devis::count(), 'Un devis a été créé sans numéro de bon.');
    }

    public function test_le_numero_est_fige_sur_le_devis_et_renvoye_avec_le_detail(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise avec un compte.');
        }

        $reponse = $this->demanderDevis($client, ['numero_bc' => 'BC-2026-077']);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));

        $devis = Devis::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($devis);
        $this->assertSame('BC-2026-077', $devis->numero_bon_commande);

        // L'application imprime le devis depuis la LISTE (Devis::liste, devis.*) :
        // c'est elle qui doit porter le numéro.
        $liste = $this->postJson('/mon_gravier/liste-devis', [
            'access' => Crypt::encryptString((string) $client->user_id),
            'type'   => 'mobile',
            'statut' => $devis->statut,
        ]);
        $liste->assertOk();
        $this->assertStringContainsString('BC-2026-077', $liste->getContent(), 'La liste des devis ne renvoie pas le numéro de bon.');
    }
}
