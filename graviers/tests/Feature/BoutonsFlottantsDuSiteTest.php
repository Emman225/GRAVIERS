<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Bouton WhatsApp flottant au-dessus du bouton « remonter » (lot 108, 17/09/2026). */
class BoutonsFlottantsDuSiteTest extends TestCase
{
    public function test_le_bouton_whatsapp_est_sur_le_site_public(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="https://wa.me/2250700130798"', $html, 'Le bouton ouvre WhatsApp sur le 07 00 13 07 98.');
        $this->assertStringContainsString('class="whatsapp-flottant"', $html);
        $this->assertStringNotContainsString('07 00 13 07 98</span>', $html, "Le numéro n'est pas affiché : icône seule.");
        $this->assertStringContainsString('aria-label="Nous écrire sur WhatsApp"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        // 17/09/2026 : « remonter » caché en haut de page (WhatsApp à sa place), visible en défilant (WhatsApp au-dessus).
        $js = file_get_contents(public_path('frontend/assets/js/main.js'));
        $this->assertStringContainsString('$.scrollUp(', $js);
        $this->assertStringContainsString("classList.toggle('avec-remonter'", $js);
        $css = file_get_contents(public_path('frontend/assets/css/premium-client.css'));
        $this->assertStringContainsString('body.avec-remonter .whatsapp-flottant { bottom: 104px; }', $css);
        $this->assertStringNotContainsString('#scrollUp { display: none !important; }', $css);
        $this->assertStringContainsString('class="newsletter-intitule"', $html);
        $this->assertStringContainsString('Laissez votre adresse e-mail pour recevoir nos offres', $html);
    }

    public function test_dalakoun_sarlu_du_pied_de_page_mene_au_site_de_l_entreprise(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="https://www.dalakoun.com" target="_blank" rel="noopener" class="ck-lien-pied" title="Site de DALAKOUN SARLU">DALAKOUN SARLU</a>', $html);
    }
}
