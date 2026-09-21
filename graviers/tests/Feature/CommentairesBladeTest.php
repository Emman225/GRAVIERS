<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * AUCUN COMMENTAIRE BLADE MAL FORMÉ DANS LES VUES.
 *
 * Un commentaire Blade NE S'IMBRIQUE PAS. Ouvrir un second commentaire à
 * l'intérieur d'un premier fait fermer celui-ci trop tôt : tout ce qui suit —
 * balises, code, et le marqueur de fermeture restant — s'affiche EN CLAIR dans
 * la page.
 *
 * C'est arrivé quatre fois, dans quatre écrans différents, chaque fois de la
 * même façon : un bloc déjà mis en commentaire, dans lequel on a écrit une
 * explication. Le visiteur voyait alors un « --}} » posé au milieu de l'écran.
 *
 * Aucun outil ne le signale : la page se rend sans erreur, PHP ne dit rien, et
 * le défaut ne se voit qu'à l'œil, sur l'écran concerné. D'où ce test.
 */
class CommentairesBladeTest extends TestCase
{
    public function test_aucun_commentaire_blade_n_est_imbrique_ou_ouvert(): void
    {
        $fautifs = [];

        $dossier = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($dossier as $fichier) {
            if (!$fichier->isFile() || !str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $contenu = file_get_contents($fichier->getPathname());
            $relatif = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $fichier->getPathname());

            $position = 0;

            while (($debut = strpos($contenu, '{{--', $position)) !== false) {
                $fin = strpos($contenu, '--}}', $debut);

                if ($fin === false) {
                    $ligne = substr_count(substr($contenu, 0, $debut), "\n") + 1;
                    $fautifs[] = "{$relatif} ligne {$ligne} : commentaire jamais fermé";
                    break;
                }

                if (str_contains(substr($contenu, $debut + 4, $fin - $debut - 4), '{{--')) {
                    $ligne = substr_count(substr($contenu, 0, $debut), "\n") + 1;
                    $fautifs[] = "{$relatif} ligne {$ligne} : commentaire imbriqué";
                }

                $position = $fin + 4;
            }
        }

        $this->assertEmpty($fautifs,
            "Des commentaires Blade mal formés feront apparaître « --}} » et du code "
            . "dans la page :\n  " . implode("\n  ", $fautifs));
    }
}
