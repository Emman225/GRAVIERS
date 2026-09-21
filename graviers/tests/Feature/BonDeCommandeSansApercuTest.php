<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE BON DE COMMANDE DU CLIENT NE S'AFFICHE PLUS EN GRAND (10/09/2026) sur le
 * traitement d'une demande de livraison : la ligne « Bon de commande joint par
 * le client — N° … » avec « Consulter » et « Télécharger » reste, l'aperçu part.
 */
class BonDeCommandeSansApercuTest extends TestCase
{
    public function test_la_ligne_reste_et_l_apercu_disparait(): void
    {
        $source = file_get_contents(resource_path('views/gestionnaire/traiteLivraison.blade.php'));

        $this->assertStringContainsString('Bon de commande joint par le client', $source);
        $this->assertStringContainsString('Consulter', $source);
        $this->assertStringContainsString('Télécharger', $source);
        $this->assertStringContainsString("'mode' => 'inline'", $source);
        $this->assertStringContainsString("'mode' => 'download'", $source);

        $this->assertStringNotContainsString('<embed', $source, 'Le PDF ne doit plus être incrusté.');
        $this->assertStringNotContainsString('alt="Bon de commande"', $source, "L'image ne doit plus être affichée.");
        $this->assertStringNotContainsString('Format non prévisualisable', $source);
    }
}
