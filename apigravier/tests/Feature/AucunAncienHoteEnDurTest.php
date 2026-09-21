<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * DÉMÉNAGEMENT (12/09/2026) : l'API ne connaît le site que par URL_SITE (.env).
 */
class AucunAncienHoteEnDurTest extends TestCase
{
    public function test_aucun_ancien_hote_dans_le_code(): void
    {
        $trouves = [];
        foreach (['app', 'config', 'resources/views', 'routes'] as $dossier) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dossier)));
            foreach ($it as $f) {
                if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) {
                    continue;
                }
                if (preg_match('/fneconnect\.net|gravierci\.com/i', file_get_contents($f->getPathname()))) {
                    $trouves[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }
        $this->assertSame([], $trouves, 'Anciens hôtes encore écrits en dur.');
    }

    public function test_les_images_du_catalogue_suivent_url_site(): void
    {
        $this->assertSame(rtrim((string) config('constantes.url_site'), '/') . '/storage/', \Help::$URL_BASE_FICHIER);
        $this->assertStringStartsWith('https://', \Help::$URL_BASE_FICHIER);
    }
}
