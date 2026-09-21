<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\ModePaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE DEMANDE NON PAYÉE N'EST PAS UNE DEMANDE.
 *
 * La demande de livraison est enregistrée AVANT l'ouverture de la passerelle de
 * paiement. Le client qui referme celle-ci — pour changer de mode, ou parce
 * qu'il renonce — laisse derrière lui une demande complète, active, et proposée
 * à l'affectation comme les autres.
 *
 * Constaté le 25/08/2026 : un client ouvre la passerelle, revient en arrière
 * pour choisir le règlement en agence, recommence — et DEUX demandes
 * apparaissent au gestionnaire pour un seul transport.
 *
 * RÈGLE : un règlement EN LIGNE n'engage à rien tant qu'il n'est pas encaissé.
 * Le règlement AU GUICHET, lui, est un engagement pris en agence : la demande
 * est exploitable d'emblée, c'est tout son intérêt.
 *
 * On ne SUPPRIME pas la demande impayée : le client peut revenir régler, et une
 * trace vaut mieux qu'un trou. On la tient hors des écrans d'affectation.
 */
class DemandeNonPayeeTest extends TestCase
{
    use DatabaseTransactions;

    private function uneDemande(?ModePaiement $mode): DemandeLivraison
    {
        $client = Client::first();
        $this->assertNotNull($client, 'La base de test doit comporter un client.');

        return DemandeLivraison::create([
            'numero'           => 'T' . substr((string) microtime(true), -8),
            'client_id'        => $client->id,
            'mode_paiement_id' => $mode?->id,
            'montantTotal'     => 4000,
            'etat_commande'    => \Help::$COMMANDE_EN_ATTENTE,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);
    }

    /** Le cas signalé : passerelle ouverte puis refermée, rien n'est encaissé. */
    public function test_une_demande_en_ligne_non_payee_est_masquee(): void
    {
        $enLigne = ModePaiement::where('en_ligne', 1)
            ->where('libelle', 'not like', '%agence%')
            ->first();
        $this->assertNotNull($enLigne, 'La base de test doit comporter un mode en ligne.');

        $demande = $this->uneDemande($enLigne);

        $this->assertSame(0.0, $demande->montantPayeComptant(),
            'La fixture doit bien être impayée, sinon le test ne prouve rien.');
        $this->assertFalse($demande->estExploitable(),
            "Une demande dont le paiement en ligne n'a jamais abouti ne doit pas "
            . "être proposée à l'affectation.");
    }

    /** Le règlement au guichet engage : la demande reste exploitable. */
    public function test_une_demande_a_regler_en_agence_reste_exploitable(): void
    {
        $agence = ModePaiement::where('libelle', 'like', '%agence%')->first();
        $this->assertNotNull($agence, 'La base de test doit comporter le mode « en agence ».');

        $this->assertTrue($this->uneDemande($agence)->estExploitable(),
            "Le règlement au guichet est un engagement : la demande doit être traitable "
            . "avant même d'être encaissée, c'est tout son intérêt.");
    }

    /**
     * NON-RÉGRESSION : sans mode de paiement renseigné, la demande reste
     * visible. Mieux vaut une demande de trop qu'une demande perdue — le
     * gestionnaire peut toujours l'écarter, il ne peut pas deviner une absence.
     */
    public function test_une_demande_sans_mode_reste_visible(): void
    {
        $this->assertTrue($this->uneDemande(null)->estExploitable());
    }

    /** L'écran d'affectation applique bien la règle. */
    public function test_l_ecran_d_affectation_applique_la_regle(): void
    {
        $this->assertStringContainsString(
            '->filter->estExploitable()',
            file_get_contents(app_path('Http/Controllers/UserController.php')),
            "La liste des demandes en attente doit écarter les demandes non payées."
        );
    }
}
