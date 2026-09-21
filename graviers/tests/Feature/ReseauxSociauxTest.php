<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LES RESEAUX SOCIAUX AFFICHES SUR LE SITE PUBLIC.
 *
 * DALAKOUN n'est presente ni sur Instagram ni sur Pinterest : les deux icones
 * venaient du gabarit. Proposer de suivre l'entreprise la ou elle n'existe pas
 * envoie le visiteur sur rien.
 *
 * Le pied de page est masque sur telephone (d-none d-md-block) et la meme
 * rangee est reprise dans le menu mobile : les retirer d'un seul endroit les
 * aurait laisses visibles sur l'autre.
 */
class ReseauxSociauxTest extends TestCase
{
    public function test_ni_instagram_ni_pinterest_sur_le_site_public(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('icon-instagram', $html);
        $this->assertStringNotContainsString('icon-pinterest', $html);
    }

    public function test_les_reseaux_conserves_sont_toujours_la(): void
    {
        // Non-regression : on retire deux icones, pas la rangee entiere.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('icon-facebook', $html);
        $this->assertStringContainsString('icon-twitter', $html);
        $this->assertStringContainsString('icon-youtube', $html);
        $this->assertStringContainsString('Suivez-nous', $html);
    }

    public function test_les_deux_rangees_sont_traitees_pareil(): void
    {
        // Celle du pied de page et celle du menu mobile.
        foreach (['client/footer.blade.php', 'client/navMobile.blade.php'] as $vue) {
            $contenu = file_get_contents(resource_path('views/' . $vue));

            $this->assertStringNotContainsString('icon-instagram', $contenu, $vue);
            $this->assertStringNotContainsString('icon-pinterest', $contenu, $vue);
        }
    }
}
