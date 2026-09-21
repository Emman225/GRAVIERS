<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * L'APPLICATION MONTRE TOUTE LIGNE DE LA COMMANDE, SANS EXCEPTION.
 *
 * Constaté en production le 01/09/2026 : l'écran « Détails commande à imprimer
 * ou retourner » de la commande 286453 (495 000 F) s'affichait entièrement
 * BLANC — « 0 article(s) ».
 *
 * La commande portait pourtant sa ligne : 5 « Mini-pelle de chantier » à
 * 99 000 F. Mais la lecture filtrait sur `produit.type_affaire = 'VENTE'` et
 * l'écartait SANS UN MOT, parce que ce matériel est typé LOCATION.
 *
 * Le défaut d'origine est en amont — un matériel de location entré dans une
 * commande de vente, corrigé côté site. Le filtre, lui, le rendait invisible :
 * le client ne pouvait ni voir ni imprimer ce pour quoi il était engagé.
 */
class LigneDeLocationDansUneCommandeTest extends TestCase
{
    use DatabaseTransactions;

    private function unClient(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    private function laCommandeDeLaMiniPelle(Client $client): Commande
    {
        $materiel = Produit::where('type_affaire', \Help::$LOCATION)->first();

        if (!$materiel) {
            $this->markTestSkipped('Aucun produit de location.');
        }

        $commande = Commande::create([
            'numero'        => 'TST' . substr(uniqid(), -7),
            'client_id'     => $client->id,
            'montant_total' => 495000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        DetailCommande::create([
            'commande_id'    => $commande->id,
            'produit_id'     => $materiel->id,
            'qte'            => 5,
            'prix'           => 99000,
            'etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $commande;
    }

    private function lignesVues(Client $client, Commande $commande): array
    {
        $reponse = $this->postJson("/mon_gravier/details-commande/{$commande->id}", [
            'access' => Crypt::encryptString((string) $client->user_id),
            'type'   => 'mobile',
        ]);

        $reponse->assertOk();

        return $reponse->json()['data']['lignes'] ?? [];
    }

    /** L'ÉCRAN N'EST PLUS BLANC. */
    public function test_l_ecran_montre_la_ligne_de_materiel(): void
    {
        $client   = $this->unClient();
        $commande = $this->laCommandeDeLaMiniPelle($client);

        $this->assertCount(1, $this->lignesVues($client, $commande),
            'L’écran de détail ne reçoit aucun article alors que la commande en '
            . 'porte un : il s’affiche blanc et son bon s’imprime vide.');
    }

    /** ET CE QU'IL MONTRE FAIT BIEN LE MONTANT ANNONCÉ. */
    public function test_ce_qui_est_montre_fait_le_montant_annonce(): void
    {
        $client   = $this->unClient();
        $commande = $this->laCommandeDeLaMiniPelle($client);

        $total = collect($this->lignesVues($client, $commande))
            ->sum(fn ($l) => (float) $l['prix'] * (float) $l['qte']);

        $this->assertEqualsWithDelta(495000, $total, 0.01,
            'Le client voit un montant que les articles affichés ne justifient '
            . 'pas.');
    }

    /** AUCUN FILTRE SUR LE TYPE DE PRODUIT DANS LES DEUX LECTURES. */
    public function test_aucune_lecture_n_ecarte_une_ligne_sur_le_type_du_produit(): void
    {
        foreach (['DetailCommande', 'DetailLocation'] as $modele) {
            // Le motif vise le CODE, pas le commentaire qui explique le retrait
            // du filtre — celui-ci cite forcément la condition supprimée.
            $this->assertStringNotContainsString("->where('produit.type_affaire'",
                file_get_contents(app_path("Models/$modele.php")),
                "$modele écarte des lignes selon le type de leur produit : une "
                . 'ligne mal typée disparaît de l’écran sans qu’un mot ne le '
                . 'signale.');
        }
    }
}
