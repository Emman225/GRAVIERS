<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Un transport payé n'est pas un trop-perçu.
 *
 * Le solde du client compare TOUS ses règlements à TOUTES ses factures. Un
 * second calcul absorbe l'écart normal — ce qui est réglé mais pas encore
 * facturé — en reprenant chaque affaire une par une. Il couvrait les commandes,
 * puis les locations, mais PAS les demandes de livraison.
 *
 * Un client ayant réglé 8 000 FCFA de transport voyait donc « 8 000 FCFA versés
 * en trop, à votre crédit », alors qu'il avait simplement payé un service rendu
 * dont la facture n'était pas encore émise.
 */
class SoldeClientLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    /** Un client sans aucun mouvement, pour isoler le cas testé. */
    private function unClientNeuf(): Client
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        // On écarte l'historique : ce test mesure UN règlement, pas un compte.
        // Les lignes d'abord : elles portent une cle etrangere vers le paiement.
        LignePaiement::whereIn('paiement_id',
            Paiement::where('client_id', $client->id)->pluck('id'))->forceDelete();
        Paiement::where('client_id', $client->id)->forceDelete();
        Facture::where('client_id', $client->id)->forceDelete();
        DemandeLivraison::where('client_id', $client->id)->forceDelete();

        return $client->fresh();
    }

    private function uneDemandeDeLivraison(Client $client, float $montant): DemandeLivraison
    {
        return DemandeLivraison::create([
            'numero'        => 'DL-' . substr(uniqid(), -8),
            'client_id'     => $client->id,
            'montantTotal'  => $montant,
            'etat_commande' => 'EN ATTENTE',
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
    }

    private function unReglement(Client $client, string $service, int $serviceId, float $montant): void
    {
        $paiement = Paiement::create([
            'client_id'       => $client->id,
            'code'            => 'TST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'libelle'         => 'Reglement de recette',
            'montant_total'   => $montant,
            'montant_restant' => 0,
            'statut'          => \Help::$STATUT_ACTIF,
            'service'         => $service,
            'service_id'      => $serviceId,
        ]);

        LignePaiement::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement_id' => ModePaiement::first()?->id,
            'montant'          => $montant,
            'statut'           => \Help::$STATUT_ACTIF,
            'service'          => $service,
            'service_id'       => $serviceId,
        ]);
    }

    public function test_un_transport_paye_n_est_pas_un_trop_percu(): void
    {
        $client  = $this->unClientNeuf();
        $demande = $this->uneDemandeDeLivraison($client, 8000);

        $this->unReglement($client, \Help::$LIVRAISON, $demande->id, 8000);

        $solde     = \Help::soldeClientBrut($client, false);
        $enAttente = \Help::montantEnAttenteDeFacturation($client);

        $this->assertSame(8000.0, round($solde, 2),
            'Le reglement compte bien dans le solde.');

        $this->assertSame(8000.0, round($enAttente, 2),
            'Il doit etre reconnu comme en attente de facturation.');

        $this->assertSame(0.0, round(max(0, $solde - $enAttente), 2),
            'Rien ne doit ressortir en verse en trop.');
    }

    public function test_un_vrai_trop_percu_reste_visible(): void
    {
        // Le correctif ne doit pas masquer les excedents reels : payer 10 000
        // pour un transport de 8 000, c'est 2 000 de trop.
        $client  = $this->unClientNeuf();
        $demande = $this->uneDemandeDeLivraison($client, 8000);

        $this->unReglement($client, \Help::$LIVRAISON, $demande->id, 10000);

        $solde     = \Help::soldeClientBrut($client, false);
        $enAttente = \Help::montantEnAttenteDeFacturation($client);

        $this->assertSame(2000.0, round(max(0, $solde - $enAttente), 2));
    }

    public function test_une_livraison_deja_facturee_ne_compte_plus(): void
    {
        $client  = $this->unClientNeuf();
        $demande = $this->uneDemandeDeLivraison($client, 8000);

        $this->unReglement($client, \Help::$LIVRAISON, $demande->id, 8000);

        Facture::create([
            'numero'     => 'FAC-' . uniqid(),
            'user_id'    => \App\Models\User::first()->id,
            'client_id'  => $client->id,
            'montant'    => 8000,
            'service'    => \Help::$LIVRAISON,
            'service_id' => $demande->id,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        // Facture emise : le solde retombe a zero, il n y a plus rien en attente.
        $this->assertSame(0.0, round(\Help::soldeClientBrut($client, false), 2));
        $this->assertSame(0.0, round(\Help::montantEnAttenteDeFacturation($client), 2));
    }

    public function test_un_reglement_orphelin_reste_signale(): void
    {
        // Un versement rattache a rien ne doit PAS etre absorbe : c'est
        // precisement le cas qu'il faut voir.
        $client = $this->unClientNeuf();

        $this->unReglement($client, \Help::$LIVRAISON, 0, 5000);

        $solde     = \Help::soldeClientBrut($client, false);
        $enAttente = \Help::montantEnAttenteDeFacturation($client);

        $this->assertSame(5000.0, round(max(0, $solde - $enAttente), 2),
            'Un reglement sans affaire rattachee doit rester visible.');
    }
}
