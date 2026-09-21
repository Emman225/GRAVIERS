<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Les fichiers des applications vivent dans le stockage du site et s'adressent par URL_SITE (17/09/2026). */
class FichiersDesApplicationsSurLeSiteTest extends TestCase
{
    public function test_l_adresse_d_un_fichier_suit_url_site(): void
    {
        config(['constantes.url_site' => 'https://site.recette/']);
        $this->assertSame('https://site.recette/storage/imageUser/12.png', \Help::urlFichier('imageUser/12.png'));
        $this->assertSame('https://ailleurs/x.png', \Help::urlFichier('https://ailleurs/x.png'), 'Une adresse absolue reste telle quelle.');
        $this->assertNull(\Help::urlFichier(''));
    }

    public function test_le_disque_principal_ecrit_dans_le_dossier_configure(): void
    {
        $dossier = sys_get_temp_dir() . '/stockage-site-recette-' . uniqid();
        mkdir($dossier);
        config(['filesystems.disks.principal.root' => $dossier]);
        Storage::forgetDisk('principal');

        Storage::disk('principal')->put('imageUser/recette.png', 'octets');

        $this->assertFileExists($dossier . '/imageUser/recette.png', 'La photo va dans le dossier configuré (celui du site en production).');
        @unlink($dossier . '/imageUser/recette.png');
        @rmdir($dossier . '/imageUser');
        @rmdir($dossier);
        Storage::forgetDisk('principal');
    }
}
