<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LE BON DE COMMANDE D'UNE ENTREPRISE EST EXIGÉ PAR LE SERVEUR.
 *
 * Point 16 du 07/09/2026. L'application l'exigeait déjà à l'écran ; le serveur
 * acceptait pourtant une commande d'entreprise sans numéro de bon. Il fait
 * foi désormais, et la facture reporte ce numéro.
 */
class BonDeCommandeEntrepriseMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function commander(Client $client, array $supplement = [])
    {
        $produit = Produit::where('type_affaire', \Help::$VENTE)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit de vente actif.');
        }

        $lignes = [[
            'produit_id' => $produit->id,
            'qte'        => 1,
            'prix'       => (float) $produit->prix_moyen,
            'livraison'  => 0,
        ]];

        return $this->postJson('/mon_gravier/enregistrer-commande', array_merge([
            'access'         => Crypt::encryptString((string) $client->user_id),
            'type'           => 'mobile',
            'mode_paiement'  => 3,
            'moyen_paiement' => 0,
            'lignes'         => $lignes,
            'total'          => (float) $produit->prix_moyen,
            'meFaireLivre'   => 0,
            'date_livraison' => now()->addDay()->toDateString(),
        ], $supplement));
    }

    public function test_une_entreprise_sans_numero_de_bon_est_refusee(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)->whereNotNull('user_id')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise avec un compte.');
        }

        $avant = Commande::count();

        $reponse = $this->commander($client, ['numero_bc' => '', 'bc_file' => null]);

        $reponse->assertOk();
        $this->assertSame(400, $reponse->json('code'), $reponse->json('message'));
        $this->assertStringContainsString('bon de commande', strtolower((string) $reponse->json('message')));
        $this->assertSame($avant, Commande::count(), 'Une commande a été créée sans bon de commande.');
    }

    public function test_un_particulier_n_a_pas_de_bon_de_commande_a_fournir(): void
    {
        $client = Client::where('type_client', \Help::$PARTICULIER)
            ->whereNotNull('user_id')->whereHas('user')
            ->where(function ($q) { $q->where('client_a_terme', 0)->orWhereNull('client_a_terme'); })
            ->first();
        if (!$client) {
            $this->markTestSkipped('Aucun particulier avec un compte.');
        }

        $reponse = $this->commander($client, ['numero_bc' => '', 'bc_file' => null]);

        $reponse->assertOk();
        // Tout sauf le refus « bon de commande » : d'autres garde-fous
        // peuvent jouer (plafond, doublon…), ce n'est pas le sujet ici.
        $this->assertStringNotContainsString('bon de commande', strtolower((string) $reponse->json('message')));
    }
}
