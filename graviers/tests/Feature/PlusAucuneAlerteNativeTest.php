<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PLUS JAMAIS DE FENÊTRE NATIVE (10/09/2026).
 *
 * Le client a vu un alert() du navigateur sur le guichet des créances à
 * terme : « je ne veux plus jamais un message d'alerte native dans ce
 * projet ». Règle : alerte() et confirmer() (SweetAlert2, dans
 * delete-confirm.js), ou le formulaire js-delete-form ; jamais alert() ni
 * confirm() écrits dans une vue ou un script — sauf l'attribut
 * onclick="return confirm(…)", que delete-confirm.js intercepte et rejoue
 * en SweetAlert2.
 */
class PlusAucuneAlerteNativeTest extends TestCase
{
    /** @return string[] chemins des fichiers à balayer */
    private function fichiers(): array
    {
        $racines = [resource_path('views'), public_path('backend/assets/js'), public_path('frontend/assets/js')];
        $liste = [];
        foreach ($racines as $racine) {
            if (!is_dir($racine)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine)) as $f) {
                if (!$f->isFile()) {
                    continue;
                }
                $chemin = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
                if (!preg_match('/\.(blade\.php|js)$/', $chemin)) {
                    continue;
                }
                if (preg_match('#/(vendors|plugins)/|\.min\.js$|delete-confirm\.js$#', $chemin)) {
                    continue;
                }
                $liste[] = $chemin;
            }
        }

        return $liste;
    }

    public function test_aucune_vue_ni_script_n_appelle_alert_ou_confirm(): void
    {
        $fautes = [];
        foreach ($this->fichiers() as $chemin) {
            // Les blocs de commentaire ({{-- --}}, /* */) sont retirés d'abord :
            // ils racontent l'histoire des anciens confirm(), ils n'en appellent pas.
            $contenu = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($chemin));
            $contenu = preg_replace('#/\*.*?\*/#s', '', $contenu);
            foreach (preg_split('/?
/', $contenu) as $i => $ligne) {
                $code = ltrim($ligne);
                if (str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*') || str_starts_with($code, '{{--')) {
                    continue;
                }
                // Un commentaire en fin de ligne ne compte pas.
                $code = preg_replace('#//.*$#', '', $code);
                if (preg_match('/(^|[^A-Za-z0-9_.$])alert\(/', $code)) {
                    $fautes[] = basename($chemin) . ':' . ($i + 1) . ' alert(';
                }
                if (preg_match('/(^|[^A-Za-z0-9_.$])confirm\(/', $code) && !preg_match('/onclick="return confirm\(/', $code)) {
                    $fautes[] = basename($chemin) . ':' . ($i + 1) . ' confirm(';
                }
            }
        }
        $this->assertSame([], $fautes, "Fenêtres natives interdites : utilisez alerte(), confirmer() ou js-delete-form.\n" . implode("\n", $fautes));
    }

    public function test_les_aides_sweetalert2_existent_et_le_filet_est_pose(): void
    {
        foreach (['backend/assets/js/delete-confirm.js', 'frontend/assets/js/delete-confirm.js'] as $script) {
            $js = file_get_contents(public_path($script));
            $this->assertStringContainsString('window.alerte = function', $js, "{$script} : l'aide alerte() manque.");
            $this->assertStringContainsString('window.confirmer = function', $js, "{$script} : l'aide confirmer() manque.");
            $this->assertStringContainsString('window.alert = window.alerte;', $js, "{$script} : le filet sur alert() manque.");
        }
        // Le cache des navigateurs ne peut pas garder les anciens scripts.
        $this->assertStringContainsString('delete-confirm.js?v=4.3', file_get_contents(resource_path('views/layout/head.blade.php')));
        $this->assertStringContainsString('delete-confirm.js?v=2.4', file_get_contents(resource_path('views/client/head.blade.php')));
    }
}
