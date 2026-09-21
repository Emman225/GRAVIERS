<?php

namespace App\Support;

use App\Models\Commande;
use App\Models\Location;
use Help;

/**
 * QUELLES AFFAIRES DONNENT DROIT À UNE COMMISSION, ET SUR QUOI ELLES POINTENT.
 *
 * `commission_apporteur.type_affaire` est un enum('LOCATION','VENTE') et
 * `commande_id` est POLYMORPHE : selon le type, il désigne une commande ou une
 * location. Il n'y a donc AUCUNE place pour une demande de livraison.
 *
 * Les chemins de règlement rangeaient pourtant tout ce qui n'est pas une
 * location en 'VENTE' :
 *
 *     $type = ($service === LOCATION) ? 'LOCATION' : 'VENTE';
 *
 * Le règlement d'une demande de livraison produisait alors une commission
 * étiquetée VENTE dont l'identifiant est celui d'une demande. Sur le téléphone
 * de l'apporteur : « null null (# null) » quand aucune commande ne porte cet
 * identifiant — et le client d'un AUTRE quand une commande le porte.
 *
 * La règle existait déjà ailleurs : des trois guichets, seuls ceux des ventes
 * et des locations créent une commission ; celui des demandes de livraison n'en
 * crée aucune. Elle est écrite ici une fois, et les chemins de règlement —
 * agence, en ligne, mobile — s'y réfèrent tous.
 */
class AffaireCommissionnable
{
    /**
     * Le type à inscrire sur la commission, ou NULL si le service ne se
     * commissionne pas (demande de livraison, service inconnu ou absent).
     */
    public static function type(?string $service): ?string
    {
        if ($service === Help::$LOCATION) {
            return 'LOCATION';
        }

        if ($service === Help::$COMMANDE || $service === 'VENTE') {
            return 'VENTE';
        }

        return null;
    }

    /**
     * L'affaire visée existe-t-elle vraiment ?
     *
     * Une commission qui pointe dans le vide crédite le solde de l'apporteur
     * sans justification traçable : elle est pire qu'une commission absente,
     * qui, elle, se rattrape.
     */
    public static function existe(?string $type, $id): bool
    {
        if (!$type || !$id) {
            return false;
        }

        return $type === 'LOCATION'
            ? Location::where('id', $id)->exists()
            : Commande::where('id', $id)->exists();
    }

    /**
     * Faut-il créer la commission ? Renvoie le type à inscrire, ou NULL.
     */
    public static function typeSiRattachable(?string $service, $id): ?string
    {
        $type = self::type($service);

        return self::existe($type, $id) ? $type : null;
    }
}
