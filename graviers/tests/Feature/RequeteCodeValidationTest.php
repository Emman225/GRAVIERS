<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LA REQUÊTE DE SECOURS QUI RETROUVE UN CODE DE VALIDATION.
 *
 * Le fichier retrouver-code-validation.sql est destiné à phpMyAdmin, sur une
 * demande dont le courriel s'est perdu. Une requête de dépannage se vérifie
 * comme le reste : livrée fausse, elle ferait perdre du temps au moment où l'on
 * en a le moins.
 *
 * Elle doit rendre le code de la course ACTIVE et signaler comme caduc celui
 * d'une course refusée — c'est toute la difficulté d'une demande réaffectée,
 * qui en porte plusieurs.
 */
class RequeteCodeValidationTest extends TestCase
{
    use DatabaseTransactions;

    /** La requête du fichier SQL, réduite à ce qu'elle affirme. */
    private function interroger(string $numeroDemande): array
    {
        return DB::select("
            SELECT
                liv.numero AS code_de_validation,
                CASE liv.accepte
                    WHEN 1 THEN 'En attente d''acceptation'
                    WHEN 2 THEN 'Acceptée par le livreur'
                    WHEN 3 THEN 'REFUSÉE (code caduc)'
                    ELSE CONCAT('État ', liv.accepte)
                END AS etat_course
            FROM livraison        liv
            JOIN detail_livraison det ON det.id = liv.detail_livraison_id
            JOIN demande_livraison dl ON dl.id  = det.demande_livraison_id
            LEFT JOIN livreur     l   ON l.id   = liv.livreur_id
            LEFT JOIN users       u   ON u.id   = l.user_id
            LEFT JOIN client      c   ON c.id   = liv.client_id
            LEFT JOIN users       cu  ON cu.id  = c.user_id
            WHERE dl.numero = ?
              AND liv.deleted_at IS NULL
            ORDER BY liv.id DESC
        ", [$numeroDemande]);
    }

    public function test_elle_distingue_la_course_active_de_la_refusee(): void
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $demande = DemandeLivraison::create([
            'numero'        => 'DL-TEST-' . substr(uniqid(), -6),
            'client_id'     => $client->id,
            'montantTotal'  => 10000,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        $detail = DetailLivraison::create([
            'nom_produit'          => 'Sable',
            'qte'                  => 1,
            'unite'                => 'Tonne',
            'unite_produit_id'     => \App\Models\UniteProduit::value('id'),
            'description'          => '',
            'demande_livraison_id' => $demande->id,
            'etat_livraison'       => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        // Une demande réaffectée porte les deux : la course refusée, puis la
        // nouvelle. Une requête qui rendrait la première ferait donner au client
        // un code que le livreur ne pourra jamais valider.
        $refusee = Livraison::create([
            'numero'               => 'ANCIEN-' . substr(uniqid(), -6),
            'client_id'            => $client->id,
            'detail_livraison_id'  => $detail->id,
            'provenance'           => \Help::$LIVRAISON,
            'date_livraison'       => date('Y-m-d'),
            'qte'                  => 1,
            'accepte'              => 3,
        ]);

        $active = Livraison::create([
            'numero'               => 'ACTIF-' . substr(uniqid(), -6),
            'client_id'            => $client->id,
            'detail_livraison_id'  => $detail->id,
            'provenance'           => \Help::$LIVRAISON,
            'date_livraison'       => date('Y-m-d'),
            'qte'                  => 1,
            'accepte'              => 2,
        ]);

        $lignes = $this->interroger($demande->numero);

        $this->assertCount(2, $lignes, 'Les deux courses doivent apparaître.');

        $codes = collect($lignes)->pluck('code_de_validation')->all();
        $this->assertContains($active->numero, $codes);
        $this->assertContains($refusee->numero, $codes);

        // La plus récente d'abord : c'est celle que le gestionnaire lit.
        $this->assertEquals($active->numero, $lignes[0]->code_de_validation);
        $this->assertEquals('Acceptée par le livreur', $lignes[0]->etat_course);

        $refuseeLigne = collect($lignes)
            ->firstWhere('code_de_validation', $refusee->numero);

        $this->assertStringContainsString('REFUS', $refuseeLigne->etat_course,
            "La course refusée doit être signalée : son code ne vaut plus rien.");
    }

    public function test_une_demande_sans_camion_ne_rend_rien(): void
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $demande = DemandeLivraison::create([
            'numero'        => 'DL-VIDE-' . substr(uniqid(), -6),
            'client_id'     => $client->id,
            'montantTotal'  => 10000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        // Aucun code n'existe tant qu'aucun camion n'est affecté : c'est
        // l'affectation qu'il faut refaire, pas un code qu'il faut chercher.
        $this->assertCount(0, $this->interroger($demande->numero));
    }
}
