<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE SOUS-TOTAL DU PANIER DE LOCATION SE LIT EN ENTIER (10/09/2026) : le champ
 * « montant » était écrasé à 54 px par le cadre bleu et le client ne voyait
 * que le premier chiffre.
 */
class SousTotalDuPanierDeLocationLisibleTest extends TestCase
{
    public function test_le_champ_prend_toute_la_largeur_de_sa_cellule(): void
    {
        $source = file_get_contents(resource_path('views/client/panierLocation.blade.php'));
        $this->assertStringContainsString('td.panier-location-montant { min-width: 190px; }', $source);
        $this->assertStringContainsString('class="qty-val panier-location-montant__champ"', $source);
        $this->assertStringContainsString('width: 100% !important; min-width: 120px;', $source);
        $this->assertStringNotContainsString('<div class="radius w-100 border-bleu">', $source, 'Le cadre étroit doit avoir disparu.');
        // La feuille de style de la page passe par la section cssPart de la tête publique.
        $this->assertStringContainsString("@section('cssPart')", $source);
        $this->assertStringContainsString("@yield('cssPart')", file_get_contents(resource_path('views/client/head.blade.php')));
    }
}
