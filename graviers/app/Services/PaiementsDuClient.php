<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DemandePaiement;
use Help;
use Illuminate\Support\Facades\DB;

/**
 * « MES PAIEMENTS » DU CLIENT (lot 89, 16/09/2026) — une seule règle pour la
 * page web (client/liste-des-paiements-…) et pour l'application, qui obtient
 * la même liste par la route interne (jeton) relayée par l'API. L'application
 * montrait ses « factures » (table facture) là où le web montre les RÈGLEMENTS :
 * les deux écrans ne disaient pas la même chose.
 *
 * lignes() est le code qui vivait dans ClientController::listePaiementCommandeClientBE,
 * déplacé tel quel ; pourLApplication() le met en forme pour le mobile.
 */
class PaiementsDuClient
{
    /** Les lignes de la page web : 'effectues', sinon les paiements en attente. */
    public static function lignes(Client $client, string $etat): array
    {
        if($etat =='effectues'){

            // Point 20 (09/09/2026) : l'état du circuit « À payer → Effectuée » de
            // l'encaissement, quand la colonne existe (migration).
            $colonneEtat = \Illuminate\Support\Facades\Schema::hasColumn('paiement', 'etat_reglement')
                ? 'p.etat_reglement AS etat_reglement,'
                : 'NULL AS etat_reglement,';
            // TROIS AFFAIRES, UNE LISTE (10/09/2026). Les règlements en agence
            // d'une LOCATION et d'une DEMANDE DE LIVRAISON suivent le même circuit
            // (point 20) que ceux d'une commande : le client les voit ici, avec
            // leur État. La jointure sur `commande` est désormais filtrée par le
            // service de la ligne : sans cela, un règlement de location dont le
            // service_id vaut l'identifiant d'une commande s'affichait comme
            // cette commande.
            $colonnesLigne = "li.id AS ligne_id, p.id AS paiement_id, p.numero_recu AS numero_recu,
                                        li.reference AS code_paiement,
                                        li.montant,
                                        li.created_at AS date_paiement,
                                        p.statut AS statut_paiement,
                                        $colonneEtat
                                        mp.description AS mode_paiement";
            $req  ="SELECT 'COMMANDE' AS type_affaire,
                                        cde.id AS commande_id,
                                        cde.numero AS num_commande,
                                        cde.created_at AS date_commande,
                                        cde.est_livrable,
                                        $colonnesLigne
                                FROM ligne_paiement li
                                JOIN commande cde ON li.service_id = cde.id
                                JOIN paiement p ON p.id = li.paiement_id
                                JOIN mode_paiement mp ON li.mode_paiement_id = mp.id
                                WHERE p.client_id = $client->id AND p.statut <> 3
                                  AND (li.service = 'COMMANDE' OR li.service IS NULL OR li.service = '')
                    UNION ALL
                    SELECT 'LOCATION' AS type_affaire,
                                        loc.id AS commande_id,
                                        loc.numero AS num_commande,
                                        loc.created_at AS date_commande,
                                        loc.est_livrable,
                                        $colonnesLigne
                                FROM ligne_paiement li
                                JOIN location loc ON li.service_id = loc.id AND li.service = 'LOCATION'
                                JOIN paiement p ON p.id = li.paiement_id
                                JOIN mode_paiement mp ON li.mode_paiement_id = mp.id
                                WHERE p.client_id = $client->id AND p.statut <> 3
                    UNION ALL
                    SELECT 'LIVRAISON' AS type_affaire,
                                        dl.id AS commande_id,
                                        dl.numero AS num_commande,
                                        dl.created_at AS date_commande,
                                        1 AS est_livrable,
                                        $colonnesLigne
                                FROM ligne_paiement li
                                JOIN demande_livraison dl ON li.service_id = dl.id AND li.service = 'LIVRAISON'
                                JOIN paiement p ON p.id = li.paiement_id
                                JOIN mode_paiement mp ON li.mode_paiement_id = mp.id
                                WHERE p.client_id = $client->id AND p.statut <> 3
                                ORDER BY date_paiement
                                        ";

         }else{

            if($client->client_a_terme == 1){
            // liste de paiement en attente pour les clients à terme
                $req = "SELECT
                            DISTINCT(f.id) AS facture_id,
                            cde.id AS commande_id,
                            cde.numero AS num_commande,
                            cde.client_id,
                            cde.numero AS num_commande,
                            cde.created_at AS date_commande,
                            -- Chaque ligne EST une facture, pas une commande. Sans son
                            -- numéro ni sa date, une commande livrée en deux fois — donc
                            -- facturée deux fois — produisait deux lignes rigoureusement
                            -- identiques à l'écran : le client ne pouvait pas savoir
                            -- laquelle il réglait.
                            f.numero AS num_facture,
                            f.created_at AS date_facture,
                            f.montant AS montant_a_payer,
                            p.montant_total AS total_paye,
                            (f.montant - IFNULL((SELECT SUM(pf.montant_total) FROM paiement pf
                                                  WHERE pf.facture_id = f.id
                                                    AND pf.statut = " . Help::$STATUT_ACTIF . "
                                                    AND pf.deleted_at IS NULL), 0)) AS montant_restant
                        FROM facture f
                        JOIN commande cde ON f.service_id = cde.id
                        LEFT JOIN paiement p ON p.facture_id = f.id AND p.statut = 2
                        WHERE cde.client_id = $client->id
                        -- « Paiements en attente » listait TOUTES les factures du client,
                        -- soldées comprises : le filtre était désactivé et le client voyait
                        -- des lignes à « reste à payer : 0 ».
                        --
                        -- Le reste se calcule PAR FACTURE, en sommant les paiements qui lui
                        -- sont rattachés — la formule qui fait déjà foi à l'encaissement
                        -- (CreanceClientTermeController::enregistrerPaiement). L'ancienne
                        -- colonne lisait le dernier paiement de la COMMANDE : avec une
                        -- facturation fractionnée, un règlement soldant la première facture
                        -- faisait passer les suivantes pour payées.
                        HAVING montant_restant > 0
                        ORDER BY cde.created_at
                        ";
            }else{
                // liste de paiement en attente pour les clients ordinaire
                // Le HT est recalculé depuis les lignes (detail_commande) et non lu dans
                // cde.montant_total : cette colonne contient le HT pour une commande créée
                // sur le site mais le NET pour une commande créée depuis l'application
                // mobile. Y ajouter TVA et livraison double-comptait donc pour le mobile,
                // et la commande restait affichée « en attente de paiement » alors qu'elle
                // était soldée.
                $req = "SELECT IFNULL(SUM(li.montant), 0) AS paye,
                                (COALESCE(NULLIF(ht.montant_ht, 0), cde.montant_total, 0) + cde.cout_livraison_client + tva.montant - cde.remise) AS montant_a_payer,
                                ((COALESCE(NULLIF(ht.montant_ht, 0), cde.montant_total, 0) + cde.cout_livraison_client + tva.montant - cde.remise) - IFNULL(SUM(li.montant), 0) ) AS montant_restant,
                                cde.numero AS num_commande,
                                cde.created_at AS date_commande,
                                cde.id as commande_id

                        FROM commande cde
                        LEFT JOIN ligne_paiement li ON li.service_id = cde.id
                        LEFT JOIN tva_commande tva ON tva.commande_id = cde.id
                        LEFT JOIN (SELECT d.commande_id, SUM(d.prix * d.qte) AS montant_ht
                                     FROM detail_commande d
                                    WHERE d.deleted_at IS NULL
                                 GROUP BY d.commande_id) ht ON ht.commande_id = cde.id
                        WHERE cde.client_id = $client->id
                        GROUP BY cde.id,
                                tva.montant,
                                ht.montant_ht,
                                -- cde.montant_total est utilisé dans le COALESCE ci-dessus mais
                                -- ne figurait pas ici. MySQL 8 l'accepte : il déduit que toutes
                                -- les colonnes de « commande » dépendent de cde.id, sa clé
                                -- primaire. MariaDB, qui fait tourner la production, n'a PAS
                                -- cette déduction : la page tombait en erreur
                                -- « 1055 'cde.montant_total' isn't in GROUP BY », donc en 500,
                                -- alors qu'elle s'affichait sans broncher en local.
                                cde.montant_total,
                                cde.cout_livraison_client,
                                cde.remise,
                                cde.numero,
                                cde.created_at
                        HAVING montant_restant > 0";
            }
         }

        return DB::select($req);
    }

    /** L'état affiché sur le web pour un règlement effectué (badge de la colonne État). */
    public static function etatDuReglement($l): string
    {
        if ((int) ($l->statut_paiement ?? 1) === 2) {
            return 'En attente de validation';
        }
        if (($l->etat_reglement ?? null) === DemandePaiement::EFFECTUEE) {
            return 'Effectuée';
        }
        if (!empty($l->etat_reglement ?? null)) {
            return 'Validée — en cours';
        }

        return 'Payé';
    }

    /**
     * Les deux listes du web, à plat, pour l'application : statut 1 = règlement
     * effectué (le reçu s'ouvre sur paiement_id), statut 2 = en attente
     * (montant = reste à payer).
     */
    public static function pourLApplication(Client $client): array
    {
        $lignes = [];
        foreach (self::lignes($client, 'effectues') as $l) {
            $lignes[] = [
                'id'              => (int) ($l->paiement_id ?? 0),
                'paiement_id'     => (int) ($l->paiement_id ?? 0),
                'ligne_id'        => (int) ($l->ligne_id ?? 0),
                'numero'          => (string) $l->num_commande,
                'num_commande'    => (string) $l->num_commande,
                'numero_recu'     => $l->numero_recu ?? null,
                'code_paiement'   => $l->code_paiement ?? null,
                'mode_paiement'   => $l->mode_paiement ?? null,
                'etat'            => self::etatDuReglement($l),
                'montant'         => round((float) $l->montant, 2),
                'montant_a_payer' => round((float) $l->montant, 2),
                'statut'          => 1,
                'service'         => (string) ($l->type_affaire ?? 'COMMANDE'),
                'service_id'      => (int) $l->commande_id,
                'client_id'       => (int) $client->id,
                'date_paiement'   => Help::dateHeure($l->date_paiement),
                'date_commande'   => Help::dateHeure($l->date_commande),
            ];
        }
        foreach (self::lignes($client, 'en-attente') as $l) {
            $lignes[] = [
                'id'              => (int) ($l->facture_id ?? $l->commande_id),
                'paiement_id'     => null,
                'ligne_id'        => null,
                'numero'          => (string) ($l->num_facture ?? $l->num_commande),
                'num_commande'    => (string) $l->num_commande,
                'numero_recu'     => null,
                'code_paiement'   => null,
                'mode_paiement'   => null,
                'etat'            => 'En attente',
                // Le reste est une soustraction de flottants (0,2400000000000091) : arrondi.
                'montant'         => round((float) $l->montant_restant, 2),
                'montant_a_payer' => round((float) $l->montant_a_payer, 2),
                'statut'          => 2,
                'service'         => 'COMMANDE',
                'service_id'      => (int) $l->commande_id,
                'client_id'       => (int) $client->id,
                'date_paiement'   => Help::dateHeure($l->date_facture ?? $l->date_commande),
                'date_commande'   => Help::dateHeure($l->date_commande),
            ];
        }

        return $lignes;
    }
}
