<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * DÉMÉNAGEMENT (12/09/2026) : plus aucun ancien nom d'hôte ni adresse de courriel en
 * dur dans le code du site ; tout vient du .env (APP_URL, EMAIL_CONTACT).
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
                $contenu = file_get_contents($f->getPathname());
                if (preg_match('/fneconnect\.net|gravierci\.com/i', $contenu)) {
                    $trouves[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }
        $this->assertSame([], $trouves, 'Anciens hôtes encore écrits en dur.');
    }

    public function test_l_adresse_de_contact_vient_de_la_configuration(): void
    {
        config(['constantes.email_contact' => 'contact@exemple.test']);
        $this->assertSame('contact@exemple.test', \Help::emailContact());

        config(['constantes.email_contact' => null, 'mail.from.address' => 'expediteur@exemple.test']);
        $this->assertSame('expediteur@exemple.test', \Help::emailContact());

        config(['mail.from.address' => '', 'app.url' => 'https://mongravier.com']);
        $this->assertSame('contact@mongravier.com', \Help::emailContact());
    }

    public function test_les_pages_publiques_affichent_l_adresse_configuree(): void
    {
        config(['constantes.email_contact' => 'contact@exemple.test']);
        $this->get('/nous-contacter')->assertOk()->assertSee('contact@exemple.test');
        $this->get('/a-propos')->assertOk()->assertSee('mailto:contact@exemple.test', false);
    }
}
