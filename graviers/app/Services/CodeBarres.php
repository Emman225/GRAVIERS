<?php

namespace App\Services;

/**
 * CODE-BARRES CODE 128 (jeu B), SANS BIBLIOTHÈQUE (lot 111, 19/09/2026).
 *
 * Le site est déployé à la main, sans `composer install` sur le serveur : une
 * dépendance de plus serait un fichier de plus à oublier. Le Code 128 tient en
 * une table de 107 motifs et une somme de contrôle modulo 103 ; tous les
 * lecteurs de douchette le lisent. Le jeu B couvre les caractères ASCII 32 à
 * 127, donc tous nos numéros de bon (« ENL777003 », « ENVA3YKS »…).
 *
 * L'image est un PNG en ligne (data URI), que DomPDF comme le navigateur
 * affichent ; sans l'extension GD, repli en tableau HTML de barres.
 */
class CodeBarres
{
    /** Les 107 motifs : largeurs barre/espace alternées (11 modules, 13 pour l'arrêt). */
    private const MOTIFS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const DEPART_B = 104;
    private const ARRET = 106;

    /** Ne garde que ce que le jeu B sait coder (ASCII 32 à 126). */
    public static function nettoyer(?string $texte): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '', trim((string) $texte));
    }

    /** Les valeurs des symboles : départ B, caractères, somme de contrôle, arrêt. */
    public static function symboles(string $texte): array
    {
        $texte = self::nettoyer($texte);
        $valeurs = [self::DEPART_B];
        $somme = self::DEPART_B;
        foreach (str_split($texte) as $i => $c) {
            $v = ord($c) - 32;
            $valeurs[] = $v;
            $somme += $v * ($i + 1);
        }
        $valeurs[] = $somme % 103;
        $valeurs[] = self::ARRET;

        return $valeurs;
    }

    /** La suite de modules : « 1 » = barre noire, « 0 » = espace. */
    public static function modules(string $texte): string
    {
        if (self::nettoyer($texte) === '') {
            return '';
        }
        $bits = '';
        foreach (self::symboles($texte) as $valeur) {
            foreach (str_split(self::MOTIFS[$valeur]) as $rang => $largeur) {
                $bits .= str_repeat($rang % 2 === 0 ? '1' : '0', (int) $largeur);
            }
        }

        return $bits;
    }

    /** Image PNG en ligne (data URI), marges blanches comprises ; chaîne vide si rien à coder. */
    public static function image(?string $texte, int $module = 2, int $hauteur = 56): string
    {
        $bits = self::modules((string) $texte);
        if ($bits === '' || !function_exists('imagecreatetruecolor')) {
            return '';
        }
        $marge = 10 * $module; // zone de silence exigée par la norme
        $largeur = strlen($bits) * $module + 2 * $marge;
        $im = imagecreatetruecolor($largeur, $hauteur);
        $blanc = imagecolorallocate($im, 255, 255, 255);
        $noir = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $largeur - 1, $hauteur - 1, $blanc);
        foreach (str_split($bits) as $i => $bit) {
            if ($bit === '1') {
                $x = $marge + $i * $module;
                imagefilledrectangle($im, $x, 0, $x + $module - 1, $hauteur - 1, $noir);
            }
        }
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** Le bloc prêt à poser dans un document : image (ou barres HTML sans GD) + numéro lisible. */
    public static function bloc(?string $texte, int $hauteurPx = 46): string
    {
        $texte = self::nettoyer($texte);
        if ($texte === '') {
            return '';
        }
        $image = self::image($texte);
        if ($image !== '') {
            $barres = '<img src="' . $image . '" alt="Code-barres ' . e($texte) . '" style="height:' . $hauteurPx . 'px;">';
        } else {
            $cellules = '';
            foreach (self::suites(self::modules($texte)) as [$bit, $n]) {
                $cellules .= '<td style="width:' . ($n * 1.5) . 'px;height:' . $hauteurPx . 'px;padding:0;background:' . ($bit === '1' ? '#000' : '#fff') . ';"></td>';
            }
            $barres = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:0 auto;"><tr>' . $cellules . '</tr></table>';
        }

        return '<div class="code-barres" style="text-align:center;margin:0 0 8px;">' . $barres
            . '<div style="font-family:monospace;font-size:11px;letter-spacing:3px;margin-top:2px;">' . e($texte) . '</div></div>';
    }

    /** Regroupe « 1110010 » en [['1',3],['0',2],['1',1],['0',1]]. */
    private static function suites(string $bits): array
    {
        preg_match_all('/1+|0+/', $bits, $m);

        return array_map(fn ($s) => [$s[0], strlen($s)], $m[0]);
    }
}
