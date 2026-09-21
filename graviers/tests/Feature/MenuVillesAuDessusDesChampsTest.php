<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /commande-en-adresse (09/09/2026) : le menu déroulant des villes (Select2)
 * passait sous le libellé « Ville de livraison » ou sous le champ « Région »,
 * parce que chaque bloc de champ (.custom_select) monte à z-index 9999 alors
 * que le menu, rattaché à <body>, restait à 1051. Le menu ouvert monte à
 * 10060 dans la feuille principale du site public.
 */
class MenuVillesAuDessusDesChampsTest extends TestCase
{
    public function test_le_menu_select2_ouvert_passe_au_dessus_des_champs(): void
    {
        $css = file_get_contents(public_path('frontend/assets/css/main.css'));
        $this->assertMatchesRegularExpression('/\.select2-container--open\s*\{\s*z-index:\s*10060\s*!important;\s*\}/', $css,
            'La règle qui place le menu ouvert au-dessus des champs manque.');
        $this->assertStringContainsString('z-index: 9999', $css, 'Le contexte (.custom_select à 9999) a changé : revoir la règle.');

        // Le cache des navigateurs ne peut pas garder l'ancienne feuille.
        $this->assertStringContainsString('frontend/assets/css/main.css?v=6.3', file_get_contents(resource_path('views/client/head.blade.php')));
    }
}
