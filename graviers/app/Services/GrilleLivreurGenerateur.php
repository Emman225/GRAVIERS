<?php

namespace App\Services;

use App\Models\CoutLivraison;
use App\Models\CoutLivraisonLivreur;
use App\Models\Livreur;

/**
 * DÉRIVE LA GRILLE D'UN LIVREUR DE CELLE DU CLIENT.
 *
 * La grille client compte 96 tranches. Les saisir une à une pour chaque livreur
 * découragerait n'importe qui — et la fonction resterait inutilisée. Un livreur
 * nouvellement créé se retrouve donc sans grille, et ce qu'on lui doit se
 * calcule alors sur son ancien mode de tarification, sans marge garantie.
 *
 * En dérivant chaque tranche d'un POURCENTAGE du tarif client, la marge devient
 * garantie partout : à 60 % pour le livreur, DALAKOUN garde 40 % sur chaque
 * tranche, sans exception ni oubli. Chaque ligne reste corrigeable à la main
 * ensuite.
 *
 * CE CALCUL VIT ICI ET NULLE PART AILLEURS. Il était enfermé dans la commande
 * `livreur:grille`, hors de portée du back-office. Le recopier pour l'écran
 * aurait créé deux règles de rémunération qui auraient divergé au premier
 * ajustement — sur de l'argent réellement versé.
 */
class GrilleLivreurGenerateur
{
    /**
     * Les tranches du catalogue général, hors tarifs propres à une ville.
     */
    public static function tranchesClient()
    {
        return CoutLivraison::with('uniteProduit')
            ->where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))
            ->orderBy('unite_produit_id')
            ->orderBy('unite_min')
            ->orderBy('distance_min_km')
            ->get();
    }

    /**
     * CE QUE TOUCHE LE LIVREUR SUR UNE TRANCHE.
     *
     * Le plancher garantit un minimum par course : 40 % d'une course à 4 000 F
     * font 1 600 F, quand le livreur en touche déjà 2 500. Appliquer le taux nu
     * lui annoncerait une baisse sur les seules courses qu'il fait réellement.
     *
     * Mais il ne peut JAMAIS dépasser ce que le client paie : au-delà, ce ne
     * serait plus une marge réduite mais une perte, et l'entreprise paierait
     * pour livrer.
     */
    public static function montantLivreur(float $prixClient, float $part, ?float $plancher): float
    {
        $montant = round($prixClient * $part / 100);

        if ($plancher === null) {
            return $montant;
        }

        return max($montant, min($plancher, $prixClient));
    }

    /**
     * Écrit la grille du livreur. Renvoie ce qui a été fait, pour le dire.
     *
     * @return array{tranches:int, total_client:float, total_livreur:float,
     *               relevees:int, ecretees:int}
     */
    public static function remplir(Livreur $livreur, float $part, ?float $plancher): array
    {
        $tranches = self::tranchesClient();

        $totalClient = 0.0;
        $totalLivreur = 0.0;
        $relevees = 0;
        $ecretees = 0;

        // On repart d'une grille propre : sans cela, la nouvelle dérivation
        // cohabiterait avec l'ancienne et deux tranches se recouvriraient sur
        // la même course — c'est la première qui trancherait, au hasard.
        CoutLivraisonLivreur::where('livreur_id', $livreur->id)->delete();

        foreach ($tranches as $t) {
            $prixClient = (float) $t->prix_km;
            $prixLivreur = self::montantLivreur($prixClient, $part, $plancher);

            CoutLivraisonLivreur::create([
                'livreur_id'       => $livreur->id,
                'unite_produit_id' => $t->unite_produit_id,
                'ville_id'         => null,
                'unite_min'        => $t->unite_min,
                'unite_max'        => $t->unite_max,
                'distance_min_km'  => $t->distance_min_km,
                'distance_max_km'  => $t->distance_max_km,
                'prix'             => $prixLivreur,
            ]);

            $totalClient += $prixClient;
            $totalLivreur += $prixLivreur;

            if ($plancher !== null) {
                if (round($prixClient * $part / 100) < min($plancher, $prixClient)) {
                    $relevees++;
                }
                if ($prixClient < $plancher) {
                    $ecretees++;
                }
            }
        }

        $livreur->update(['part_grille' => $part]);

        return [
            'tranches'      => $tranches->count(),
            'total_client'  => $totalClient,
            'total_livreur' => $totalLivreur,
            'relevees'      => $relevees,
            'ecretees'      => $ecretees,
        ];
    }
}
