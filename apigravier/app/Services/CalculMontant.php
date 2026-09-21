<?php

namespace App\Services;

use Help;
use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\PrixPersonnalise;
use App\Models\Produit;
use App\Models\Reduction;
use App\Models\Ville;

/**
 * Calcul des montants d'une commande ou d'une location, CÔTÉ SERVEUR.
 *
 * Le prix unitaire, la TVA, la remise et le coût de livraison ne sont jamais
 * repris de l'application : ils sont recalculés ici à partir du catalogue, des
 * prix personnalisés du client, de la configuration et de l'adresse de livraison.
 *
 * Cette classe est le SEUL endroit où ce calcul existe. Elle sert à la fois :
 *   - à l'enregistrement d'une commande (CommandeController) ;
 *   - à l'enregistrement d'une location (LocationController) ;
 *   - à la vérification demandée par l'application avant validation
 *     (« verifier-montant »), pour que le client voie exactement le montant qui
 *     lui sera prélevé.
 * Un seul calcul, donc aucune divergence possible entre ce qui est affiché et
 * ce qui est facturé.
 */
class CalculMontant
{
    /**
     * @param  \App\Models\User  $user      utilisateur authentifié
     * @param  \App\Models\Client $client   client correspondant
     * @param  array  $donnees              lignes, adresse, remise, meFaireLivre, long, lat...
     * @param  bool   $estLocation          true = le montant d'une ligne est prix × qte × jours
     * @return array
     */
    public static function pour($user, $client, array $donnees, bool $estLocation = false): array
    {
        $config = Configuration::find(1);
        $lignes = $donnees['lignes'] ?? [];

        // ------------------------------------------------------------------
        // 1. Coût de livraison : calculé sur l'ADRESSE DE LIVRAISON choisie
        //    (comme le site), pas sur la position GPS du téléphone.
        // ------------------------------------------------------------------
        $montantLivraison = 0.0;
        $meFaireLivre = ($donnees['meFaireLivre'] ?? 0) == 1 || ($donnees['meFaireLivre'] ?? false) === true;

        if ($meFaireLivre) {
            $adresse   = AdresseLivraison::lire($donnees['adresse'] ?? 0);
            $villeUser = Ville::lire($user->ville_id);

            if ($adresse->id > 0 && CoutLivraison::coordonneesExploitables($adresse->longitude, $adresse->latitude)) {
                $long   = $adresse->longitude;
                $lat    = $adresse->latitude;
                $ville  = Ville::lire($adresse->ville_id);
                $region = $ville->region_id ?: $villeUser->region_id;
            } else {
                $long   = $donnees['long'] ?? 0;
                $lat    = $donnees['lat'] ?? 0;
                $region = $villeUser->region_id;
            }

            $qteTotale = 0;
            foreach ($lignes as $l) { $qteTotale += (float) ($l['qte'] ?? 0); }
            if ($qteTotale > 0) {
                $montantLivraison = (float) CoutLivraison::calculer($long, $lat, $region, $qteTotale);
                foreach ($lignes as $cle => $l) {
                    $lignes[$cle]['livraison'] = ((float) ($l['qte'] ?? 0) / $qteTotale) * $montantLivraison;
                }
            }
        }

        // ------------------------------------------------------------------
        // 2. Prix unitaires : catalogue, ou prix personnalisé du client.
        // ------------------------------------------------------------------
        $idsProduits = collect($lignes)->pluck('produit_id')->filter()->unique()->values();
        $prixPerso   = PrixPersonnalise::listeSurClient($client->id);

        // Prix CATALOGUE : celui du fournisseur actif le moins cher, à défaut
        // prix_moyen — exactement ce que l'application affiche.
        //
        // On lisait ici la colonne prix_moyen brute. Or le catalogue renvoyé à
        // l'application applique déjà le prix fournisseur : l'écran annonçait
        // 100 FCFA et la commande était facturée 2 000. Le correctif précédent
        // n'avait rétabli que l'AFFICHAGE ; la facturation restait fausse, et
        // chaque commande déclenchait au passage un écart journalisé.
        $catalogue = Produit::prixCatalogue($idsProduits->all());

        $ht     = 0.0;
        $ecarts = [];
        foreach ($lignes as $cle => $l) {
            $idProduit   = $l['produit_id'] ?? null;
            $prixServeur = (float) ($prixPerso[$idProduit] ?? ($catalogue[(int) $idProduit] ?? 0));
            $prixEnvoye  = (float) ($l['prix'] ?? 0);
            $qte         = (float) ($l['qte'] ?? 0);
            $jours       = $estLocation ? max(1, (float) ($l['nbreJours'] ?? 1)) : 1;

            if (abs($prixServeur - $prixEnvoye) > 0.01) {
                $ecarts[] = "produit $idProduit : envoyé $prixEnvoye, appliqué $prixServeur";
            }

            $lignes[$cle]['prix'] = $prixServeur;
            $ht += $prixServeur * $qte * $jours;
        }

        // ------------------------------------------------------------------
        // 3. TVA : seulement si le client y est assujetti.
        //
        // Elle est calculée PLUS BAS, une fois la remise connue : son assiette
        // est le HT DIMINUÉ DE LA REMISE, et non le HT brut.
        // ------------------------------------------------------------------
        // TVA marchandise du client (appliquée par défaut, retirable par client).
        $tauxTva = Help::tauxTvaClient($client);

        // ------------------------------------------------------------------
        // 4. Remise : code promo revérifié + points plafonnés au solde réel.
        // ------------------------------------------------------------------
        $remise          = 0.0;
        $reductionValide = null;
        if ((int) ($donnees['reduction'] ?? 0) > 0) {
            $red = Reduction::lire($donnees['reduction']);
            $aujourdhui = date('Y-m-d');
            $periodeOk = (empty($red->debut) || $aujourdhui >= date('Y-m-d', strtotime($red->debut)))
                      && (empty($red->fin)   || $aujourdhui <= date('Y-m-d', strtotime($red->fin)));
            if ($red->id > 0 && $red->est_utilise != 1 && $red->statut == Help::$STATUT_ACTIF && $periodeOk) {
                $reductionValide = $red;
                $remise += $ht * (float) $red->taux_reduction / 100;
            } else {
                $ecarts[] = "code promo {$donnees['reduction']} refusé (inactif, expiré ou déjà utilisé)";
            }
        }

        $pointsUtilises = 0.0;
        if ((float) ($donnees['pointUtilise'] ?? 0) > 0) {
            $demandes = (float) $donnees['pointUtilise'];
            $solde    = (float) ($client->point ?? 0);

            // PLANCHER DE PAIEMENT.
            //
            // Les points pouvaient couvrir la commande ENTIÈRE : sans
            // livraison, le total tombait à zéro, la passerelle était appelée
            // avec 0 et la commande restait « EN ATTENTE DE PAIEMENT », donc
            // invisible du gestionnaire — les points, eux, étant déjà débités.
            // Ils ne réduisent plus le total au-delà du minimum paramétré.
            $pointsUtilises = Help::pointsUtilisables(
                $ht,
                $remise,               // remise du code promo, déjà calculée
                $tauxTva,
                $montantLivraison,
                (float) ($config->montant_point ?? 0),
                $demandes,
                $solde
            );

            if ($pointsUtilises < $solde && $pointsUtilises < $demandes) {
                // Le reliquat RESTE au compte du client : il servira à la
                // commande suivante, rien n'est perdu.
                $ecarts[] = "points ramenés de {$demandes} à {$pointsUtilises} "
                          . "pour laisser le minimum à payer";
            } elseif ($pointsUtilises < $demandes) {
                $ecarts[] = "points demandés {$demandes}, solde réel {$solde}";
            }

            $remise += $pointsUtilises * (float) ($config->montant_point ?? 0);
        }

        // La remise porte sur la marchandise : elle ne peut pas dépasser le HT,
        // et ne vient jamais effacer le coût de livraison. C'est déjà la règle
        // du site, où le net se calcule par max(0, HT − remise).
        $remise = min($remise, $ht);

        // ------------------------------------------------------------------
        // 5. TVA sur la base NETTE, puis total.
        //
        // La TVA était prise sur le HT BRUT : le client se voyait réclamer la
        // taxe sur une remise qu'il ne payait pas. La facture, elle, applique
        // depuis le 08/08/2026 la base nette — comme le site. Les deux montants
        // divergeaient donc exactement du taux appliqué à la remise.
        //
        // Constaté en production sur la commande 849677 : HT 350, remise 35,
        // livraison 65. Le mobile a réclamé 443 (TVA 63 = 18 % de 350), la
        // facture en retenait 437 (TVA 57 = 18 % de 315). Le client avait tout
        // réglé, et son règlement restait pourtant impossible à imputer, la
        // somme versée dépassant la somme due.
        //
        // La base nette est la règle fiscale ; c'est celle du site et celle de
        // la facture. Le mobile s'y aligne, avec le même arrondi, pour que les
        // trois montants tombent au franc près.
        // ------------------------------------------------------------------
        $htNet = max(0, $ht - $remise);
        $tva   = round($htNet * $tauxTva / 100);

        // TVA sur le transport (point 5) : même décision et même taux que
        // sur le site, figés sur l'affaire à l'enregistrement.
        // TVA du transport propre au client (10/09/2026), indépendante de la TVA marchandise.
        $tvaTransport = Help::tvaTransportPour($client, (float) $montantLivraison);

        // AIRSI (10/09/2026) : 5 % du HT net de remise + TVA, pour le client sans
        // régime réel ; le net à payer devient HT + TVA + AIRSI.
        // Le transport entre dans l'assiette de l'AIRSI, comme la DGI (lot 97).
        $airsi = Help::airsiPour($client, $htNet + $tva, (float) $htNet, (float) $tauxTva / 100,
            (float) $montantLivraison, $tvaTransport > 0 ? Help::tauxTvaTransportClient($client) / 100 : 0.0);

        $total = max(0, round($htNet + $tva + $montantLivraison + $tvaTransport + $airsi));

        return [
            'lignes'           => $lignes,
            'ht'               => $ht,
            'tva'              => $tva,
            'tva_transport'    => $tvaTransport,
            'airsi'            => $airsi,
            'livraison'        => $montantLivraison,
            'remise'           => $remise,
            'total'            => $total,
            'points_utilises'  => $pointsUtilises,
            'reduction_valide' => $reductionValide,
            'ecarts'           => $ecarts,
        ];
    }
}
