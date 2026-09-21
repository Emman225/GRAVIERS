<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE CODE DE LIVRAISON NE S'AFFICHE PLUS AU BACK-OFFICE DES DEMANDES DE
 * LIVRAISON (10/09/2026) : ni sur la liste, ni sur l'écran de traitement,
 * ni dans le message d'échec d'envoi. Le client le lit chez lui ; le
 * gestionnaire garde « Renvoyer le code ».
 */
class CodeDeLivraisonHorsDuBackOfficeTest extends TestCase
{
    public function test_le_traitement_garde_le_renvoi_mais_pas_le_code(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/traiteLivraison.blade.php'));
        $this->assertStringNotContainsString('{{ $uneCourse->numero }}', $vue);
        $this->assertStringNotContainsString('<th>Code</th>', $vue);
        $this->assertStringContainsString('Renvoyer le code', $vue);
        $this->assertStringContainsString('show.renvoyerCodeDemandeLivraison', $vue);
    }

    public function test_la_liste_ne_porte_aucun_code_de_course(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/demandeLivraisonlist.blade.php'));
        $this->assertStringNotContainsString('_codesLivraison', $vue);
        $this->assertStringNotContainsString('Code de livraison', $vue);
        $this->assertDoesNotMatchRegularExpression('/\$[a-zA-Z]+->livraisons\b.*->numero/', $vue);
    }

    public function test_le_message_d_echec_d_envoi_ne_cite_plus_le_code(): void
    {
        $controleur = file_get_contents(app_path('Http/Controllers/UserController.php'));
        $this->assertStringNotContainsString('Communiquez-lui le code %s', $controleur);
        $this->assertStringContainsString("Le client le trouve sur son compte et dans l'application", $controleur);
    }
}
