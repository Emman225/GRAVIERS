<?php

namespace App\Services;

use App\Models\Apporteur;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\Location;
use App\Models\Paiement;
use Help;

/**
 * CE QUI SUIT UN RÈGLEMENT DE COMMANDE DEVENU EFFECTIF.
 *
 * Deux conséquences accompagnent chaque tranche encaissée sur une vente :
 * la commission de l'apporteur qui a parrainé le client, et les points de
 * fidélité du client. Elles vivaient dans le guichet des ventes
 * (CommandeComptantController::validerEncaissement). Depuis les avances
 * clients (point 19, 07/09/2026), un règlement naît aussi SANS guichet —
 * quand une commande « en agence » s'impute sur une avance déjà validée —
 * et il doit produire exactement les mêmes effets. Une seule règle, un seul
 * endroit.
 */
class ReglementValide
{
    /** Commission de l'apporteur + points du client, pour cette tranche. */
    public static function appliquer(Commande $commande, Paiement $paiement): void
    {
        self::crediterApporteur($commande, (float) $paiement->montant_total);

        // POINTS DE FIDÉLITÉ : LA MÊME RÈGLE QUE PARTOUT. Calculés sur le
        // montant RÉELLEMENT encaissé, et inscrits sur le règlement : sans
        // cette trace, une annulation obligerait à deviner ce qu'il faut
        // reprendre.
        if ($commande->client) {
            $points = Help::pointsPour((float) $paiement->montant_total);

            if ($points > 0) {
                $commande->client->update([
                    'point' => (float) $commande->client->point + $points,
                ]);
                $paiement->update(['points_attribues' => $points]);
            }
        }
    }

    /**
     * CE QUI SUIT UN RÈGLEMENT DE LOCATION DEVENU EFFECTIF.
     *
     * Même raison d'être que pour une vente : la règle vivait dans le guichet
     * des locations (LocationComptantController::validerEncaissement). Depuis
     * que l'avance d'un client s'impute aussi sur une location « en agence »
     * (10/09/2026), un règlement de location naît sans guichet et doit
     * produire les mêmes effets : le drapeau de la location (2 = partielle,
     * 3 = soldée), la commission de l'apporteur au barème des locations, et
     * les points du client.
     */
    public static function appliquerLocation(Location $location, Paiement $paiement): void
    {
        // La validation d'une location par le gestionnaire s'appuie sur
        // location.statut (3 = soldée), et non sur les montants.
        $location->update(['statut' => $location->montantRestantDu() <= 0 ? 3 : 2]);

        self::crediterApporteurLocation($location, (float) $paiement->montant_total);

        if ($location->client) {
            $points = Help::pointsPour((float) $paiement->montant_total);

            if ($points > 0) {
                $location->client->update([
                    'point' => (float) $location->client->point + $points,
                ]);
                $paiement->update(['points_attribues' => $points]);
            }
        }
    }

    /**
     * Commission de l'apporteur sur une LOCATION : le taux dépend de la
     * taille de l'affaire (2,5 % sous 5 millions, 5 % jusqu'à 20 millions,
     * 7 % au-delà), mais ne porte que sur la TRANCHE encaissée.
     */
    public static function crediterApporteurLocation(Location $location, float $montantTranche): void
    {
        $client = $location->client;

        if (!$client || !$client->code_parrain) {
            return;
        }

        $apporteur = Apporteur::where('code', $client->code_parrain)->first();

        if (!$apporteur) {
            return;
        }

        $montantTotalLoc = (float) $location->montant_total;

        if ($montantTotalLoc < 5000000) {
            $taux = 2.5;
        } elseif ($montantTotalLoc <= 20000000) {
            $taux = 5;
        } else {
            $taux = 7;
        }

        // Arrondi au franc entier : le FCFA n'a pas de décimales.
        $commission = CommissionApporteur::create([
            // L'affaire se désigne dans `commande_id`, colonne polymorphe : c'est
            // elle que lit l'application apporteur. `location_id` est conservée
            // par prudence.
            'commande_id'  => $location->id,
            'location_id'  => $location->id,
            'apporteur_id' => $apporteur->id,
            'montant'      => round($montantTranche * $taux / 100),
            'type_affaire' => 'LOCATION',
            'statut'       => 1,
        ]);

        $apporteur->update([
            'solde' => (float) $apporteur->solde + (float) $commission->montant,
        ]);
    }

    /**
     * Commission de l'apporteur qui a parrainé le client.
     *
     * Portée à la VALIDATION du règlement, jamais à la saisie, et sur la
     * TRANCHE encaissée, jamais sur le total de la commande — sinon une vente
     * réglée en trois fois paierait la commission trois fois.
     */
    private static function crediterApporteur(Commande $commande, float $montantTranche): void
    {
        $client = $commande->client;

        if (!$client || !$client->code_parrain) {
            return;
        }

        // L'apporteur est lu AVANT d'accéder à son solde : un code_parrain
        // orphelin provoquerait sinon une page d'erreur alors que le paiement
        // vient d'être validé.
        $apporteur = Apporteur::where('code', $client->code_parrain)->first();

        if (!$apporteur) {
            return;
        }

        // Taux propre à l'apporteur, à défaut celui de la configuration.
        $taux = (float) ($apporteur->pourcentage ?? 0);
        if ($taux <= 0) {
            $taux = (float) (Configuration::first()?->taux_commission_standard ?? 3);
        }

        $commission = CommissionApporteur::create([
            'commande_id'  => $commande->id,
            'apporteur_id' => $apporteur->id,
            // Arrondi au franc entier : le FCFA n'a pas de décimales.
            'montant'      => round($montantTranche * $taux / 100),
            // Une commande est une vente, par construction (voir l'historique
            // de la commission n° 44 du 28/08/2026).
            'type_affaire' => 'VENTE',
            'montantPaye'  => $montantTranche,
            'statut'       => 1,
        ]);

        // On INCRÉMENTE le solde, on ne l'écrase pas : les commissions se cumulent.
        $apporteur->update([
            'solde' => (float) $apporteur->solde + (float) $commission->montant,
        ]);
    }
}
