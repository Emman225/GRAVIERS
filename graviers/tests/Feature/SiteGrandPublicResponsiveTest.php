<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Produit;
use App\Models\User;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SITE GRAND PUBLIC 100 % RESPONSIVE (12/09/2026) : la feuille additive et le
 * script sont servis sur toutes les pages publiques, la balise viewport est
 * là, et la page de commande ne fait plus d'erreur 500 sans l'étape adresse.
 */
class SiteGrandPublicResponsiveTest extends TestCase
{
    use DatabaseTransactions;

    private function pagesPubliques(): array
    {
        $produit = Produit::where('statut', \Help::$STATUT_ACTIF)->orderBy('id')->value('id');
        $pages = ['/', '/client/login', '/client/register', '/location-materiel-construction',
            '/demande-de-livraison', '/a-propos', '/nous-contacter', '/aide', '/search?search=gravier',
            '/c-est-mon-panier'];
        if ($produit) {
            $pages[] = '/' . $produit . '/description';
        }

        return $pages;
    }

    public function test_les_pages_publiques_chargent_la_feuille_et_le_script_responsive(): void
    {
        foreach ($this->pagesPubliques() as $page) {
            $reponse = $this->get($page);
            $reponse->assertOk();
            $reponse->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1" />', false);
            $reponse->assertSee('frontend/assets/css/responsive-dalakoun.css', false);
            $reponse->assertSee('frontend/assets/js/responsive-dalakoun.js', false);
            // La feuille additive vient APRÈS premium-responsive.css : ses règles l'emportent.
            $html = $reponse->getContent();
            $this->assertGreaterThan(strpos($html, 'premium-responsive.css'), strpos($html, 'responsive-dalakoun.css'), $page);
        }
    }

    public function test_la_feuille_additive_ne_touche_pas_l_ordinateur(): void
    {
        $css = file_get_contents(public_path('frontend/assets/css/responsive-dalakoun.css'));
        // Toute règle est sous un @media (max-width: …) : hors media query, rien.
        $sansCommentaires = preg_replace('#/\*.*?\*/#s', '', $css);
        $horsMedia = preg_replace('#@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}#s', '', $sansCommentaires);
        $this->assertSame('', trim($horsMedia), 'Aucune règle hors media query.');
        $this->assertStringNotContainsString('@media (min-width', $css, 'Aucune règle pour les grands écrans.');
        $this->assertMatchesRegularExpression('/max-width:\s*991\.98px/', $css);
    }

    public function test_la_page_de_commande_sans_adresse_renvoie_a_l_etape_adresse(): void
    {
        $user = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        Client::create(['user_id' => $user->id, 'nom' => 'Recette', 'prenom' => 'Mobile', 'email' => $user->email,
            'contact1' => '0101010101', 'type_client' => \Help::$PARTICULIER, 'statut' => \Help::$STATUT_ACTIF, 'applique_tva' => 1]);
        $produit = Produit::where('statut', \Help::$STATUT_ACTIF)->orderBy('id')->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit actif.');
        }
        Cart::add($produit->id, $produit->nom ?? 'Produit', 1, 1000, ['unite' => 'tonne']);

        $reponse = $this->actingAs($user)->get('/panier-en-commande');

        $reponse->assertRedirect(route('client.commandeAdresse'));
        Cart::destroy();
    }
}
