<?php

namespace App\Support;

use App\Models\Enlevement;
use Illuminate\Database\Eloquent\Builder;

/**
 * CE QUI COMPTE COMME UNE VENTE, ET À QUEL PRIX.
 *
 * Trois écrans mesurent le chiffre d'affaires — « CA détaillé », « CA par
 * famille » et « Récapitulatif des ventes ». Ils appliquaient chacun leur
 * propre règle, recopiée à la main : corriger l'un laissait les autres faux.
 * Constaté le 04/09/2026, « Marge brute HT −732 020 fcfa » sur « CA détaillé »
 * alors que le récapitulatif des ventes venait d'être corrigé. La règle tient
 * donc ici, en un seul endroit.
 *
 * DEUX PIÈGES, TOUS DEUX SILENCIEUX.
 *
 * 1. UNE LOCATION N'EST PAS UNE VENTE. Une course de location range
 *    l'identifiant de sa ligne dans la MÊME colonne que les commandes :
 *    `livraison.detail_commande_id` porte alors un id de `detail_location`
 *    (UserController, création de la course de location). Deux conséquences,
 *    aucune visible :
 *
 *      · le plus souvent aucune ligne de commande ne répond, et le bon passe
 *        pour une vente orpheline ;
 *      · mais les deux tables numérotent leurs lignes à partir de 1, si bien
 *        qu'un id peut exister DANS LES DEUX. La relation charge alors la
 *        ligne de commande d'un AUTRE client, pour un AUTRE produit, et lui
 *        emprunte son prix.
 *
 *    On écarte donc sur la PROVENANCE de la course, jamais sur l'absence de
 *    ligne : c'est le seul critère qui ne se laisse pas tromper.
 *
 * 2. UN BON SANS LIGNE N'A PAS DE PRIX DE VENTE. Le prix facturé vit sur la
 *    ligne de commande. Quand elle a disparu, ces écrans retombaient sur le
 *    prix du CATALOGUE : une recette qui n'a jamais été facturée à personne,
 *    et qui, face à un coût bien réel, écrase la marge des vraies ventes.
 *    Le prix est ici `null` — à l'appelant de mettre le bon de côté et de
 *    l'annoncer, jamais de lui en inventer un.
 */
final class BonsDeVente
{
    /**
     * Les bons servis de la période qui sont des VENTES.
     *
     * Le filtre est une liste NOIRE — tout sauf les locations — et non une
     * liste blanche sur « COMMANDE » : une course créée sans provenance
     * explicite prend le défaut de la colonne, et ce défaut n'est pas
     * forcément le même en ligne qu'en local. Une liste blanche effacerait
     * ces ventes-là du chiffre d'affaires.
     *
     * @param  string|null  $du  au format Y-m-d, bornes comprises
     * @param  string|null  $au
     */
    public static function servis(?string $du = null, ?string $au = null): Builder
    {
        return Enlevement::query()
            ->whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereDoesntHave('livraison', function ($q) {
                $q->where('provenance', \Help::$LOCATION);
            })
            ->when($du, fn ($q) => $q->where('fournisseur_validation', '>=', $du . ' 00:00:00'))
            ->when($au, fn ($q) => $q->where('fournisseur_validation', '<=', $au . ' 23:59:59'));
    }

    /**
     * Le prix unitaire RÉELLEMENT facturé au client sur ce bon.
     *
     * `null` quand on ne le connaît pas : la ligne de commande a disparu, ou
     * la course n'est pas une vente. Le prix du catalogue n'est pas un repli
     * acceptable — c'est une recette inventée.
     */
    public static function prixFacture(Enlevement $bon): ?float
    {
        $livraison = $bon->livraison;

        if (!$livraison) {
            return null;
        }

        // Ceinture et bretelles : `servis()` a déjà écarté les locations, mais
        // cette méthode sert aussi à des appelants qui composent leur propre
        // requête, et la ligne chargée serait alors celle d'un autre client.
        if ($livraison->provenance === \Help::$LOCATION) {
            return null;
        }

        $detail = $livraison->detailCommande;

        if (!$detail || $detail->prix === null) {
            return null;
        }

        return (float) $detail->prix;
    }
}
