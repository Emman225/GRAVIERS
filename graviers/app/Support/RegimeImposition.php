<?php

namespace App\Support;

/**
 * LE RÉGIME D'IMPOSITION D'UN CLIENT ENTREPRISE.
 *
 * On STOCKE un code court (RNI, RSI, RE, RME) et on AFFICHE un intitulé
 * complet. Les deux étaient confondus : la fiche client conservait le libellé
 * entier tel qu'il figurait dans le menu déroulant, si bien que changer un mot
 * à l'écran aurait laissé les anciens clients avec l'ancien texte — et deux
 * orthographes du même régime sur les factures.
 *
 * Libellés demandés le 07/09/2026 :
 *   RE  → Taxe d'État de l'Entreprenant (TEE)
 *   RNI → Réel normal d'imposition
 *   RSI → Réel simplifié d'imposition
 * RME (micro-entreprises) reste proposé : des clients l'ont déjà choisi.
 *
 * `libelle()` accepte aussi bien un code qu'un ancien libellé complet : les
 * fiches non encore normalisées s'affichent donc correctement dès le
 * déploiement, avant même la migration.
 */
class RegimeImposition
{
    public const CODES = [
        'RNI' => "Réel normal d'imposition",
        'RSI' => "Réel simplifié d'imposition",
        'RME' => 'Régime des micro-entreprises',
        'RE'  => "Taxe d'État de l'Entreprenant (TEE)",
    ];

    /** Le code court, quelle que soit la forme reçue (code, ancien libellé, casse). */
    public static function code(?string $valeur): ?string
    {
        $v = strtoupper(trim((string) $valeur));
        if ($v === '') {
            return null;
        }

        foreach (array_keys(self::CODES) as $code) {
            // « RNI », « RNI — Régime Normal d'Imposition », « RNI - ... »
            if ($v === $code || preg_match('/^' . $code . '\s*[—\-–]/u', $v)) {
                return $code;
            }
        }

        return null;
    }

    /** L'intitulé complet à imprimer ; la valeur d'origine si elle est inconnue. */
    public static function libelle(?string $valeur): string
    {
        $code = self::code($valeur);

        return $code ? self::CODES[$code] : trim((string) $valeur);
    }

    /** Règle de validation pour un formulaire : un des codes connus. */
    public static function regle(): string
    {
        return 'in:' . implode(',', array_keys(self::CODES));
    }
}
