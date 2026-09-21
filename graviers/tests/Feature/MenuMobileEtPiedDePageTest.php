<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 13/09/2026 : pied de page « Mon Gravier - DALAKOUN SARLU », menu mobile identique
 * au menu de l'ordinateur, page de connexion et logo traités par la feuille additive.
 */
class MenuMobileEtPiedDePageTest extends TestCase
{
    public function test_le_pied_de_page_porte_la_nouvelle_marque(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        // 17/09/2026 : « DALAKOUN SARLU » est devenu un lien vers le site de l'entreprise.
        $this->assertMatchesRegularExpression('#Mon Gravier</strong> - <a href="https://www\.dalakoun\.com"[^>]*>DALAKOUN SARLU</a>#', $html);
        $this->assertStringNotContainsString('gravierci</strong>', $html);
    }

    public function test_le_menu_mobile_reprend_les_entrees_du_menu_ordinateur(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $debut = strpos($html, 'class="mobile-menu font-heading"');
        $fin = strpos($html, '</ul>', strpos($html, 'mobile-menu-sous'));
        $this->assertNotFalse($debut);
        $menu = substr($html, $debut, $fin - $debut);
        foreach (['Accueil', 'Location', 'Livraison', 'Blog', 'A propos', 'Contact', 'Espace pro',
                  route('livreur.login'), route('apporteur.login'), route('sellers.login')] as $attendu) {
            $this->assertStringContainsString($attendu, $menu);
        }
        $this->assertStringNotContainsString('#deal', $menu);
        // Le bloc d'informations du tiroir (sous le menu) n'a plus « Notre localisation » ni le numéro
        $infos = substr($html, strpos($html, 'mobile-header-info-wrap'), strpos($html, 'icon-facebook-white') - strpos($html, 'mobile-header-info-wrap'));
        $this->assertStringNotContainsString('Notre localisation', $infos);
        $this->assertStringNotContainsString('07 333', $infos);
    }

    public function test_la_feuille_additive_est_a_jour_sur_la_page_de_connexion(): void
    {
        $this->get('/client/login')->assertOk()->assertSee('responsive-dalakoun.css?v=1.1', false);
        $css = file_get_contents(public_path('frontend/assets/css/responsive-dalakoun.css'));
        $this->assertStringContainsString('.hero-login__content', $css);
        $this->assertStringContainsString('mobile-menu-espace-pro', file_get_contents(resource_path('views/client/navMobile.blade.php')));
    }
}
