<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PLUS DE « Chaque Mot En Majuscule » (09/09/2026).
 *
 * Le client a relevé « Voici Un Aperçu De Votre Activité Aujourd'hui » : un
 * style « text-transform: capitalize » et des ucwords() sur des phrases
 * donnaient au site l'allure d'un texte généré. Désormais :
 *  - Help::phrase() met une majuscule au premier caractère, rien d'autre ;
 *  - ucwords() reste réservé aux noms de personnes ;
 *  - le style « capitalize » a quitté le sous-titre du tableau de bord, les
 *    fils d'Ariane et les menus.
 */
class PhrasesSansMajusculesGenereesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_help_phrase_ne_touche_qu_au_premier_caractere(): void
    {
        $this->assertSame('Ciment-colle carrelage 25 kg', \Help::phrase('ciment-colle carrelage 25 kg'));
        $this->assertSame('Paiement en agence', \Help::phrase('paiement en agence'));
        $this->assertSame('Étang du Nord, lot 12', \Help::phrase('étang du Nord, lot 12'));
        $this->assertSame('', \Help::phrase(null));
        $this->assertSame('Barre de fer 10 mm', \Help::phrase('  Barre de fer 10 mm '));
    }

    public function test_les_vues_n_appliquent_plus_ucwords_aux_phrases(): void
    {
        $interdits = ['produit', 'affichage', 'libelle', 'description', 'lieu', 'fne_adresse', 'destination'];
        $fautes = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($f->getPathname());
            if (preg_match_all('/ucwords\(([^)]*)\)/', $source, $m)) {
                foreach ($m[1] as $arg) {
                    foreach ($interdits as $mot) {
                        if (str_contains($arg, $mot)) {
                            $fautes[] = $f->getFilename() . ' : ucwords(' . $arg . ')';
                        }
                    }
                }
            }
        }
        $this->assertSame([], $fautes, "ucwords() ne s'applique plus aux désignations, libellés et adresses :\n" . implode("\n", $fautes));
    }

    public function test_les_styles_ne_capitalisent_plus_les_phrases_et_les_menus(): void
    {
        $blocs = [
            [public_path('backend/assets/css/premium-dashboard.css'), '.dash-welcome-subtitle {'],
            [public_path('frontend/assets/css/main.css'), '.breadcrumb {'],
            [public_path('frontend/assets/css/premium-client.css'), '.main-menu > nav > ul > li > a {'],
        ];
        foreach ($blocs as [$fichier, $selecteur]) {
            $css = file_get_contents($fichier);
            $debut = strrpos($css, $selecteur);
            $this->assertNotFalse($debut, "{$selecteur} absent de {$fichier}");
            $bloc = substr($css, $debut, strpos($css, '}', $debut) - $debut);
            $this->assertStringNotContainsString('capitalize', $bloc, "{$selecteur} capitalise encore ({$fichier}).");
        }
    }

    public function test_le_sous_titre_du_tableau_de_bord_est_une_phrase(): void
    {
        $source = file_get_contents(resource_path('views/layout/index.blade.php'));
        $this->assertStringContainsString("Voici un aperçu de votre activité aujourd'hui", $source);
        // Le cache des navigateurs ne peut pas garder l'ancienne feuille.
        $this->assertStringContainsString('premium-dashboard.css?v=1.1', file_get_contents(resource_path('views/layout/head.blade.php')));
    }
}
