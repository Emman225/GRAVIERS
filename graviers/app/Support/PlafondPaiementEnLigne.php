<?php

namespace App\Support;

/**
 * AU-DESSUS D'UN CERTAIN MONTANT, ON NE PAIE PAS EN LIGNE.
 *
 * La règle est annoncée au client depuis toujours, sur le panier comme sur la
 * page de paiement : « Pour tout montant supérieur à 2 000 000 fcfa le paiement
 * doit se faire par virement bancaire, en agence ou en plusieurs commandes. »
 *
 * Elle était écrite HUIT FOIS, à la main, avec trois comparaisons différentes :
 *
 *   · les vues du panier annonçaient « supérieur à » (`> 2000000`) ;
 *   · la demande de livraison et la location testaient `< 2000000`, ce qui
 *     range le montant EXACT de 2 000 000 du mauvais côté ;
 *   · la commande ne testait rien du tout — la ligne était commentée. Le
 *     client lisait le message, puis partait quand même vers la passerelle.
 *     Constaté le 04/09/2026 sur une commande de 6 502 000 fcfa.
 *
 * Et là où le test existait, il se contentait de SAUTER le paiement en
 * silence : l'affaire était enregistrée comme si de rien n'était, sans qu'un
 * franc soit encaissé et sans que personne soit prévenu.
 *
 * Le seuil et la phrase tiennent donc ici, en un seul endroit.
 */
final class PlafondPaiementEnLigne
{
    /**
     * Le plafond, en francs CFA.
     *
     * Il n'est pas dans la table de configuration : l'y mettre demanderait une
     * colonne de plus, et la valeur n'a jamais bougé. Le jour où elle doit
     * changer, c'est ici — et nulle part ailleurs.
     */
    public const PLAFOND = 2000000;

    /**
     * Ce montant dépasse-t-il le plafond ?
     *
     * « SUPÉRIEUR à », comme le dit le message au client : une affaire de
     * 2 000 000 tout rond passe encore en ligne.
     */
    public static function depasse($montant): bool
    {
        return (float) $montant > self::PLAFOND;
    }

    /**
     * Ce mode de paiement est-il utilisable pour ce montant ?
     *
     * Un mode hors ligne — « Paiement en agence », virement — convient à
     * n'importe quel montant. Un mode en ligne s'arrête au plafond.
     */
    public static function modeAutorise($mode, $montant): bool
    {
        if (!$mode) {
            return false;
        }

        return ((int) $mode->en_ligne !== 1) || !self::depasse($montant);
    }

    /** La phrase montrée au client, écrite une fois pour toutes. */
    public static function message(): string
    {
        return 'Pour tout montant supérieur à '
            . number_format(self::PLAFOND, 0, ',', ' ')
            . ' fcfa le paiement doit se faire par virement bancaire, en agence '
            . 'ou en plusieurs commandes.';
    }

    /** Le refus opposé à un client qui a tout de même choisi un mode en ligne. */
    public static function refus(): string
    {
        return self::message()
            . ' Choisissez « Paiement en agence » pour poursuivre.';
    }
}
