<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UNE CARTE DU BACK-OFFICE DOIT ATTENDRE QUE LEAFLET SOIT CHARGÉ.
 *
 * Signalé le 27/08/2026 sur « Nouvelle région » : le champ « Adresse complète »
 * ne montrait rien et la carte restait un rectangle gris. Rien à voir avec la
 * carte elle-même — c'était l'ORDRE D'EXÉCUTION.
 *
 * Le gabarit du back-office charge leaflet.js, Control.Geocoder.js et
 * recherche-lieu.js avec l'attribut « defer ». Un script différé ne s'exécute
 * qu'une fois toute la page analysée, donc APRÈS les scripts écrits en ligne
 * dans la vue. Un bloc qui appelle « L.map(...) » immédiatement trouve donc un
 * « L » qui n'existe pas encore : la première ligne lève une ReferenceError et
 * TOUT le bloc meurt — la carte ET la barre de recherche avec.
 *
 * Mesuré le 27/08/2026 en rejouant les deux cas avec les fichiers réels :
 *   · appel immédiat            -> ReferenceError : L is not defined
 *   · attente de DOMContentLoad -> carte créée, recherche = object
 *
 * Le site public échappait au piège : son propre pied de page charge les mêmes
 * fichiers SANS « defer ». C'est pourquoi la demande de livraison fonctionnait
 * pendant que la page des régions était muette — une divergence entre les deux
 * gabarits, invisible à la lecture d'une seule vue.
 *
 * Cet essai lit les vues du back-office : toute vue qui crée une carte doit
 * attendre. Il échouerait si quelqu'un ajoutait demain un écran de carte sans
 * cette précaution, ou retirait celle qui vient d'être posée.
 */
class CarteDuBackOfficeTest extends TestCase
{
    /** Les vues du back-office qui construisent une carte Leaflet. */
    private function vuesAvecCarte(): array
    {
        $racine = resource_path('views');
        $trouvees = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $contenu = file_get_contents($fichier->getPathname());

            // Seul le back-office est concerné : c'est son pied de page qui
            // diffère les scripts. Le site public les charge sans « defer ».
            if (! str_contains($contenu, "@extends('layout.main')")) {
                continue;
            }
            if (! str_contains($contenu, 'L.map(')) {
                continue;
            }

            $trouvees[str_replace($racine . DIRECTORY_SEPARATOR, '', $fichier->getPathname())] =
                $this->sansCommentaires($contenu);
        }

        return $trouvees;
    }

    /**
     * Retire les commentaires avant toute analyse.
     *
     * SANS CELA, CET ESSAI NE VÉRIFIE RIEN — et c'est arrivé : la première
     * version cherchait le mot « DOMContentLoaded » n'importe où dans la vue.
     * Or le commentaire qui EXPLIQUE la correction contient ce mot, et il est
     * placé juste avant l'appel. L'essai passait donc même après avoir remis le
     * défaut. Vérifié en le remettant pour de bon : il passait au vert.
     *
     * On ne garde donc que le code réellement exécuté.
     */
    private function sansCommentaires(string $contenu): string
    {
        // Commentaires Blade, puis commentaires JavaScript de ligne et de bloc.
        $contenu = preg_replace('/\{\{--.*?--\}\}/s', '', $contenu);
        $contenu = preg_replace('#/\*.*?\*/#s', '', $contenu);
        $contenu = preg_replace('#^\s*//.*$#m', '', $contenu);

        return $contenu;
    }

    public function test_le_gabarit_du_back_office_differe_bien_les_scripts(): void
    {
        $pied = file_get_contents(resource_path('views/layout/footer.blade.php'));

        $this->assertStringContainsString(
            'defer src="{{ asset(\'frontend/assets/leaflet/leaflet.js\') }}"',
            $pied,
            "Si « defer » disparaît un jour, la contrainte tombe et cet essai n'a "
            . "plus lieu d'être — mais il faut le constater, pas le découvrir."
        );
    }

    public function test_chaque_carte_du_back_office_attend_le_chargement(): void
    {
        $vues = $this->vuesAvecCarte();

        $this->assertNotEmpty(
            $vues,
            "Aucune vue du back-office ne crée de carte : soit elles ont changé de "
            . "gabarit, soit la recherche ne les voit plus. À vérifier."
        );

        foreach ($vues as $chemin => $contenu) {
            $positionCarte = strpos($contenu, 'L.map(');
            $positionAttente = strpos($contenu, "addEventListener('DOMContentLoaded'");

            $this->assertNotFalse(
                $positionAttente,
                "$chemin appelle L.map() sans jamais attendre « DOMContentLoaded ». "
                . "Les scripts Leaflet du back-office sont différés : « L » n'existe "
                . "pas encore, et TOUT le bloc meurt sur une ReferenceError — ni "
                . "carte, ni barre de recherche."
            );

            $this->assertLessThan(
                $positionCarte,
                $positionAttente,
                "Dans $chemin, l'attente de « DOMContentLoaded » arrive APRÈS le "
                . "premier L.map() : celui-ci s'exécute donc toujours trop tôt."
            );
        }
    }

    /**
     * LE SITE PUBLIC, LUI, NE DIFFÈRE PAS — et ses vues n'ont donc pas à attendre.
     *
     * Cet essai fixe la raison de la différence, pour qu'on ne « corrige » pas un
     * jour le pied de page public en y ajoutant « defer », ce qui casserait d'un
     * coup les neuf écrans de carte du parcours client.
     */
    public function test_le_pied_de_page_public_charge_leaflet_sans_defer(): void
    {
        $pied = file_get_contents(resource_path('views/client/footer.blade.php'));

        $this->assertMatchesRegularExpression(
            '#<script src="\{\{ asset\(\'frontend/assets/leaflet/leaflet\.js\'\) \}\}">#',
            $pied,
            "Le pied de page public charge Leaflet sans « defer ». Y ajouter "
            . "« defer » casserait les cartes du parcours client, dont le code "
            . "s'exécute immédiatement."
        );
    }
}
