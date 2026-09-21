<?php

namespace App\Support;

/**
 * LES ADRESSES ATTEIGNABLES DEPUIS LE MENU LATÉRAL.
 *
 * Le bouton « Retour » n'a aucun sens sur une page ouverte d'un clic dans le
 * menu : on n'y « revient » de nulle part, on y est allé directement.
 *
 * Cette liste était tenue À LA MAIN dans layout/main.blade.php. Elle a dérivé :
 * chaque entrée ajoutée au menu depuis lors gardait son bouton, faute d'avoir
 * pensé à l'inscrire ailleurs. On la déduit donc du menu lui-même — ajouter
 * une entrée suffit désormais, et rien d'autre n'est à tenir.
 *
 * La lecture des gabarits est faite UNE FOIS par requête (mémorisation
 * statique) : quelques dizaines de kilo-octets, à côté de la compilation des
 * mêmes gabarits par Blade, ne pèsent rien.
 */
class MenusLateraux
{
    /** Gabarits du menu latéral, tous profils confondus. */
    private const GABARITS = [
        'layout/navbar.blade.php',
        'layout/navGestionnnaire.blade.php',
        'layout/navbar/navAdmin.blade.php',
        // Les trois blocs de l'administrateur, découpés le 07/09/2026 (point 1).
        'layout/navbar/navClient.blade.php',
        'layout/navbar/navEtats.blade.php',
        'layout/navbar/navConfiguration.blade.php',
        'layout/navFournisseur.blade.php',
        'layout/navLivreur.blade.php',
        'layout/navAgent.blade.php',
    ];

    /** @var array<int,string>|null */
    private static ?array $noms = null;

    /**
     * Noms de route cités par le menu latéral.
     *
     * @return array<int,string>
     */
    public static function nomsDeRoute(): array
    {
        if (self::$noms !== null) {
            return self::$noms;
        }

        $noms = [];

        foreach (self::GABARITS as $gabarit) {
            $chemin = resource_path('views/' . $gabarit);

            if (!is_file($chemin)) {
                continue;
            }

            // route('nom') et route("nom", …) — le premier argument seul.
            if (preg_match_all('/route\(\s*[\'"]([a-zA-Z0-9_.\-]+)[\'"]/',
                    (string) file_get_contents($chemin), $trouves)) {
                foreach ($trouves[1] as $nom) {
                    $noms[$nom] = true;
                }
            }
        }

        return self::$noms = array_keys($noms);
    }

    /**
     * La page courante est-elle atteignable directement depuis le menu ?
     */
    public static function contientLaRouteCourante(): bool
    {
        $noms = self::nomsDeRoute();

        return $noms !== [] && request()->routeIs(...$noms);
    }

    /** Remet à zéro la mémorisation — utile aux essais. */
    public static function oublier(): void
    {
        self::$noms = null;
    }
}
