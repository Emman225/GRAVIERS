<?php

namespace Tests\Feature;

use App\Models\Banniere;
use App\Models\Newsletter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * LE POPUP DE PUBLICITÉ ET LE BANDEAU DE COOKIES DU SITE PUBLIC.
 *
 * Deux ajouts demandés sur la page d'accueil, sur le modèle de Jumia :
 *   · une fenêtre publicitaire proposant l'inscription à la lettre
 *     d'information, avec une pastille de rappel une fois refermée ;
 *   · un bandeau de consentement aux cookies, avec gestion par catégorie.
 *
 * Le visuel du popup n'est PAS écrit dans le code : il vient d'une bannière de
 * type « POPUP », administrable. Sans bannière de ce type, rien ne s'affiche —
 * mieux vaut aucun popup qu'un cadre vide, et cela laisse le choix de ne pas en
 * avoir.
 */
class PopupEtCookiesTest extends TestCase
{
    use DatabaseTransactions;

    private function unePubliciteActive(): Banniere
    {
        // La banniere affichee est la PREMIERE active de ce type. Si la base en
        // porte deja une, c'est elle qui sortirait, et le test mesurerait les
        // donnees ambiantes plutot que sa propre fixture.
        Banniere::where('type_banniere', 'POPUP')->update(['statut' => \Help::$STATUT_INACTIF]);

        return Banniere::create([
            'titre'         => 'Offre de rentrée',
            'sous_titre'    => 'Jusqu\'au 30 septembre',
            'image'         => 'frontend/assets/imgs/theme/produit/banner2.png',
            'num_ordre'     => 1,
            'type_banniere' => 'POPUP',
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
    }

    public function test_sans_banniere_popup_l_accueil_n_affiche_aucune_publicite(): void
    {
        // Depublier suffit et ne detruit rien : la transaction du test sera
        // annulee, mais un forceDelete sur des donnees reelles ne se rattrape
        // pas si le test est un jour lance ailleurs qu'en local.
        Banniere::where('type_banniere', 'POPUP')->update(['statut' => \Help::$STATUT_INACTIF]);

        $reponse = $this->get('/');

        $reponse->assertOk();
        $reponse->assertDontSee('pubOverlay', false);
    }

    public function test_la_banniere_popup_s_affiche_sur_l_accueil(): void
    {
        $pub = $this->unePubliciteActive();

        $reponse = $this->get('/');

        $reponse->assertOk();
        $reponse->assertSee('pubOverlay', false);
        $reponse->assertSee($pub->titre);
        $reponse->assertSee($pub->sous_titre);

        // Le formulaire d'inscription y est, avec son origine.
        $reponse->assertSee(route('newsletter.store'), false);
        $reponse->assertSee("Popup de la page d'accueil", false);

        // Et la pastille de rappel, pour rouvrir ce qui a été fermé.
        $reponse->assertSee('pubPastille', false);
    }

    public function test_une_banniere_popup_retiree_ne_s_affiche_plus(): void
    {
        $pub = $this->unePubliciteActive();
        $pub->update(['statut' => \Help::$STATUT_INACTIF]);

        $this->get('/')->assertDontSee('pubOverlay', false);
    }

    public function test_le_bandeau_de_cookies_est_sur_tout_le_site(): void
    {
        // Un visiteur qui arrive par une autre page que l'accueil doit pouvoir
        // choisir comme les autres : le consentement ne se demande pas
        // seulement à la porte d'entrée.
        $this->get('/')->assertSee('ckBandeau', false);

        $this->get(route('confidentialite'))
            ->assertOk()
            ->assertSee('ckBandeau', false);
    }

    public function test_le_bandeau_offre_de_refuser_aussi_facilement_que_d_accepter(): void
    {
        // Un bandeau qui n'offre que « Tout accepter » ne recueille pas un
        // consentement, il l'extorque.
        $reponse = $this->get('/');

        $reponse->assertSee('Tout accepter');
        $reponse->assertSee('Refuser');
        $reponse->assertSee('Gérer');
    }

    public function test_la_gestion_par_categorie_est_proposee(): void
    {
        $reponse = $this->get('/');

        $reponse->assertSee('Nécessaires');
        $reponse->assertSee("Mesure d'audience", false);
        $reponse->assertSee('Publicité et offres');

        // Les cookies nécessaires ne se refusent pas : sans eux, ni connexion
        // ni panier. La case est cochée et désactivée.
        $reponse->assertSee('checked disabled', false);
    }

    public function test_l_inscription_depuis_le_popup_est_tracee(): void
    {
        Mail::fake();

        $adresse = 'popup-' . uniqid() . '@example.com';

        $this->post(route('newsletter.store'), [
            'email'   => $adresse,
            'origine' => "Popup de la page d'accueil",
        ])->assertSessionHas('newsletter_message');

        $abonne = Newsletter::where('email', $adresse)->first();

        $this->assertNotNull($abonne);
        $this->assertEquals("Popup de la page d'accueil", $abonne->origine,
            "Savoir quel point de recueil travaille permet de juger s'il mérite d'être conservé.");
    }

    public function test_une_origine_inventee_est_ignoree(): void
    {
        // Le champ est caché, donc modifiable depuis le navigateur : rien ne
        // doit permettre d'écrire n'importe quoi dans la fiche d'un abonné.
        Mail::fake();

        $adresse = 'popup-' . uniqid() . '@example.com';

        $this->post(route('newsletter.store'), [
            'email'   => $adresse,
            'origine' => '<script>alert(1)</script>',
        ]);

        $this->assertEquals('Pied de page du site',
            Newsletter::where('email', $adresse)->value('origine'));
    }

    public function test_l_inscription_depuis_le_pied_de_page_reste_inchangee(): void
    {
        // Non-régression : le point de recueil d'origine ne doit pas être
        // abîmé par l'ajout du second.
        Mail::fake();

        $adresse = 'pied-' . uniqid() . '@example.com';

        // On n'interroge PAS la clé « success » : Flasher la capte et la retire
        // de la session pour la rejouer en bulle. Chercher ce qu'un autre a
        // déjà consommé ferait échouer un test sur un comportement normal.
        $this->post(route('newsletter.store'), ['email' => $adresse])
            ->assertSessionHas('newsletter_message');

        $this->assertEquals('Pied de page du site',
            Newsletter::where('email', $adresse)->value('origine'));
    }

    public function test_l_image_du_popup_est_servie_depuis_storage(): void
    {
        // LE CHEMIN DES BANNIERES PASSE PAR « storage/ ».
        //
        // Le formulaire depose l'image sur le disque public, et tous les ecrans
        // du back-office la servent ainsi. Lue a la racine, l'adresse repond 404
        // et le popup s'affiche sans visuel — un defaut invisible en test tant
        // qu'on ne verifie que la presence du popup.
        $pub = $this->unePubliciteActive();

        $this->get('/')->assertSee('storage/' . $pub->image, false);
    }

    public function test_le_jeu_d_essai_installe_une_publicite_affichable(): void
    {
        // Le popup ne s'affiche que s'il existe une banniere de ce type, et
        // l'ecran de creation ne dit pas qu'elle le commande. Le jeu d'essai
        // sert precisement a essayer la fonction sans le savoir.
        \App\Models\Banniere::where('type_banniere', 'POPUP')
            ->update(['statut' => \Help::$STATUT_INACTIF]);

        $this->artisan('db:seed', ['--class' => 'PopupAccueilSeeder'])->assertSuccessful();

        $pub = \App\Models\Banniere::liste('POPUP')->first();

        $this->assertNotNull($pub, "Le jeu d'essai doit laisser une publicite active.");
        $this->assertEquals('POPUP', $pub->type_banniere,
            "Le type ne doit pas avoir ete tronque par l'enumeration.");

        $this->get('/')->assertSee('pubOverlay', false);
    }

    public function test_le_jeu_d_essai_se_relance_sans_dupliquer(): void
    {
        $this->artisan('db:seed', ['--class' => 'PopupAccueilSeeder'])->assertSuccessful();
        $apresUnePasse = \App\Models\Banniere::where('type_banniere', 'POPUP')->count();

        $this->artisan('db:seed', ['--class' => 'PopupAccueilSeeder'])->assertSuccessful();
        $apresDeuxPasses = \App\Models\Banniere::where('type_banniere', 'POPUP')->count();

        $this->assertEquals($apresUnePasse, $apresDeuxPasses,
            "Relancer le jeu d'essai ne doit pas empiler les publicites.");
    }

    /** 17/09/2026 : plus de pastille flottante ; le consentement se retire depuis le pied de page. */
    public function test_le_consentement_se_retire_depuis_le_pied_de_page_sans_pastille_flottante(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('id="ckRappel"', $html, 'La pastille flottante de rappel est retirée.');
        $this->assertStringNotContainsString('.ck-rappel{', $html);
        $this->assertStringContainsString('data-ck-ouvrir', $html, "Le pied de page garde un point d'entrée pour gérer les cookies.");
        $this->assertStringContainsString('>Gérer mes cookies</a>', $html);
        $this->assertStringContainsString("querySelectorAll('[data-ck-ouvrir]')", $html, 'Le lien ouvre le panneau.');
    }
}
