<?php

namespace App\Support;

/**
 * AU-DESSUS D'UN CERTAIN MONTANT, ON NE PAIE PAS EN LIGNE.
 *
 * Jumelle de la classe du même nom côté site. Les deux dépôts sont séparés :
 * la règle est donc écrite deux fois, mais UNE SEULE fois dans chacun, et les
 * deux fichiers doivent rester identiques dans leur contenu.
 *
 * Elle était recopiée à la main dans TROIS points d'entrée de l'API, avec deux
 * comparaisons différentes :
 *
 *   · la commande testait `<= 2000000` ;
 *   · la location aussi ;
 *   · la demande de livraison testait `< 2000000`, ce qui range le montant
 *     EXACT de 2 000 000 du mauvais côté — le client se voyait refuser un
 *     règlement en ligne que le site, lui, acceptait.
 *
 * Et dans les trois cas, dépasser le plafond ne REFUSAIT rien : la passerelle
 * était simplement sautée. L'affaire s'enregistrait comme si elle était payée
 * au comptoir, entrait dans la file du gestionnaire sans qu'un franc soit
 * encaissé, et l'application n'avait aucun moyen de le dire au client.
 */
final class PlafondPaiementEnLigne
{
    /**
     * Le plafond, en francs CFA.
     *
     * L'application cliente porte la même valeur pour construire son menu
     * (choix_adresse_screen.dart). Le jour où elle change, les deux doivent
     * changer ensemble — sinon l'application propose un règlement que le
     * serveur refuse.
     */
    public const PLAFOND = 2000000;

    /**
     * Ce montant dépasse-t-il le plafond ?
     *
     * « SUPÉRIEUR à », comme le dit le message montré au client : une affaire
     * de 2 000 000 tout rond passe encore en ligne.
     */
    public static function depasse($montant): bool
    {
        return (float) $montant > self::PLAFOND;
    }

    /** La phrase montrée au client, écrite une fois pour toutes. */
    public static function message(): string
    {
        return 'Pour tout montant supérieur à '
            . number_format(self::PLAFOND, 0, ',', ' ')
            . ' fcfa le paiement doit se faire par virement bancaire, en agence '
            . 'ou en plusieurs commandes.';
    }

    /** Le refus opposé à un client qui a tout de même demandé un règlement en ligne. */
    public static function refus(): string
    {
        return self::message()
            . ' Choisissez « Paiement en agence » pour poursuivre.';
    }
}
