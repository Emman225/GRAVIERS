<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La classe Help est toujours appelée avec sa casse exacte.
 *
 * Windows résout `HELP::` sans broncher — son système de fichiers est
 * insensible à la casse. Linux, lui, cherche `HELP.php` et ne trouve rien :
 * la page tombe en erreur 500 dès que la classe n'a pas déjà été chargée
 * ailleurs dans la requête. Un défaut invisible en développement.
 */
class CasseClasseHelpTest extends TestCase
{
    public function test_aucun_appel_a_help_en_mauvaise_casse(): void
    {
        $fautifs = [];

        foreach ([app_path(), resource_path('views')] as $racine) {
            $fichiers = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($fichiers as $fichier) {
                if (!$fichier->isFile() || !str_ends_with($fichier->getFilename(), '.php')) {
                    continue;
                }

                $contenu = file_get_contents($fichier->getPathname());

                // `Help` est la seule casse correcte : c'est le nom du fichier
                // app/Help.php, et donc ce que l'autoloader cherchera.
                if (preg_match('/\b(HELP|help)::/', $contenu)) {
                    $fautifs[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $fichier->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            "Ces fichiers appellent Help avec une casse que Linux ne résoudra pas :\n"
            . implode("\n", $fautifs)
        );
    }
}
