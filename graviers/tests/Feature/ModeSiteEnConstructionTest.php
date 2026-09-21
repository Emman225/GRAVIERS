<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Lot 114 (19/09/2026) : mode « site en construction ». */
class ModeSiteEnConstructionTest extends TestCase
{
    use DatabaseTransactions;

    private function mode(bool $actif): void
    {
        Configuration::first()->update(['site_en_construction' => $actif]);
    }

    private function unAdmin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])->firstOrFail();
    }

    public function test_mode_inactif_le_site_est_ouvert_et_la_page_renvoie_a_l_accueil(): void
    {
        $this->mode(false);
        $this->get('/')->assertOk();
        $this->get('/a-propos')->assertOk();
        $this->get('/site-en-construction')->assertRedirect(route('client.index'));
    }

    public function test_mode_actif_un_visiteur_ne_voit_que_la_page_en_construction(): void
    {
        $this->mode(true);
        foreach (['/', '/a-propos', '/blog', '/nous-contacter', '/demande-de-livraison', '/client/register'] as $page) {
            $this->get($page)->assertRedirect(route('siteEnConstruction'));
        }
        $html = $this->get('/site-en-construction')->assertOk()->getContent();
        $this->assertStringContainsString('Site en <span>construction</span>', $html);
        $this->assertStringContainsString(route('client.login'), $html);
        $this->assertStringContainsString('noindex', $html);
        // Une requête AJAX reçoit un refus net, pas une redirection.
        $this->getJson('/')->assertStatus(503);
    }

    /** Lot 114 bis : deux boutons sur la page ; connexion du client sans en-tête, sans pied, sans inscription. */
    public function test_mode_actif_la_page_n_a_que_deux_boutons_et_la_connexion_s_affiche_seule(): void
    {
        $this->mode(true);
        $page = $this->get('/site-en-construction')->assertOk()->getContent();
        $this->assertSame(2, substr_count($page, 'class="btn '), 'Se connecter et WhatsApp, rien d\'autre.');
        $this->assertStringNotContainsString('Espace professionnel', $page);
        $this->assertStringNotContainsString(route('show.login'), $page);

        $login = $this->get('/client/login')->assertOk()->getContent();
        $this->assertStringContainsString('id="style-visiteur-en-construction"', $login, 'En-tête, bandeaux et pied masqués.');
        $this->assertStringContainsString('footer.main', $login);
        $this->assertStringNotContainsString('Créer un compte</a>', $login, "L'invitation à s'inscrire n'est pas rendue.");
        $this->assertStringContainsString(route('siteEnConstruction'), $login, 'Le lien de retour ramène à la page du mode.');
        $this->assertStringContainsString('jquery', $login, 'Les scripts du pied restent chargés.');

        // Mode désactivé : tout redevient comme avant.
        $this->mode(false);
        $login = $this->get('/client/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="style-visiteur-en-construction"', $login);
        $this->assertStringContainsString('Créer un compte</a>', $login);
        $this->assertStringContainsString('Retour au site', $login);
    }

    public function test_mode_actif_les_portes_d_entree_restent_ouvertes(): void
    {
        $this->mode(true);
        foreach (['/client/login', '/login-account', '/livreur/login', '/seller/login', '/apporteur/login', '/demandeEmail'] as $page) {
            $this->get($page)->assertOk();
        }
        // Le téléchargement des applications n'est pas bloqué par le mode.
        $this->assertNotSame(route('siteEnConstruction'), $this->get('/telecharger-application/client')->headers->get('Location'));
    }

    public function test_mode_actif_une_personne_connectee_navigue_et_voit_le_rappel(): void
    {
        $this->mode(true);
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        $this->actingAs($client->user);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('rappel-site-en-construction', $html);
        $this->get('/a-propos')->assertOk();
        $this->get('/site-en-construction')->assertRedirect(route('client.index'));
    }

    public function test_l_interrupteur_est_dans_parametres_et_reserve_aux_administrateurs(): void
    {
        $this->mode(false);
        $admin = $this->unAdmin();
        $page = $this->actingAs($admin)->get('/parametre')->assertOk()->getContent();
        $this->assertStringContainsString('carte-site-en-construction', $page);
        $this->assertStringContainsString(route('show.basculerSiteEnConstruction'), $page);
        $this->assertStringContainsString('js-delete-form', $page, 'Confirmation SweetAlert2, jamais une alerte native.');

        $this->actingAs($admin)->post('/parametre/site-en-construction')->assertRedirect(route('show.parametre'));
        $this->assertTrue(Configuration::siteEnConstruction());
        $this->actingAs($admin)->post('/parametre/site-en-construction');
        $this->assertFalse(Configuration::siteEnConstruction());

        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->first();
        if ($gestionnaire) {
            $this->actingAs($gestionnaire)->post('/parametre/site-en-construction')->assertForbidden();
            $this->assertFalse(Configuration::siteEnConstruction());
        }
    }
}
