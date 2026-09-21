<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE CHEMIN VERS L'INSCRIPTION D'UN APPORTEUR D'AFFAIRE.
 *
 * La page d'inscription existait — apporteur/register — mais AUCUNE page n'y
 * menait : il fallait connaitre l'adresse par coeur. Un apporteur pressenti qui
 * arrivait sur l'ecran de connexion ne pouvait que repartir.
 */
class InscriptionApporteurDepuisLoginTest extends TestCase
{
    public function test_la_page_de_connexion_mene_a_l_inscription(): void
    {
        $this->get(route('apporteur.login'))
            ->assertOk()
            ->assertSee(route('apporteur.register'), false)
            ->assertSee('Créer mon compte');
    }

    public function test_la_page_d_inscription_repond(): void
    {
        // Un lien qui mene a une page en erreur serait pire que pas de lien.
        $this->get(route('apporteur.register'))->assertOk();
    }

    public function test_la_connexion_reste_l_action_principale(): void
    {
        // Le lien ne doit pas concurrencer le bouton : il vient APRES lui.
        $html = $this->get(route('apporteur.login'))->getContent();

        $this->assertLessThan(
            strpos($html, route('apporteur.register')),
            strpos($html, 'auth-btn-submit'),
            "Le bouton « Se connecter » doit rester avant le lien d'inscription."
        );
    }
}
