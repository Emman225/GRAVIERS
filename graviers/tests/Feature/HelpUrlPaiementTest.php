<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Help::urlPaiement() construit l'adresse que la passerelle de paiement
 * rappellera une fois le règlement effectué. Deux garanties, indépendantes
 * de la machine sur laquelle le code tourne :
 *
 *   1. l'adresse est toujours en https ;
 *   2. elle porte la base publique du site, jamais celle du serveur qui a
 *      généré le lien. En développement, route() produit
 *      « http://127.0.0.1:8000/... », une adresse que la passerelle ne peut
 *      pas rappeler : le paiement serait encaissé sans que la commande soit
 *      jamais soldée.
 *
 * La base est lue dans PAIEMENT_BASE_URL, avec repli sur config('app.url').
 * Ce repli est indispensable : après un `php artisan config:cache`, env() ne
 * renvoie plus rien.
 */
class HelpUrlPaiementTest extends TestCase
{
    public function test_l_adresse_de_retour_ne_pointe_jamais_sur_la_machine_locale(): void
    {
        $resultat = \Help::urlPaiement('http://127.0.0.1:8000/callBackPaiement');

        $this->assertStringNotContainsString('127.0.0.1', $resultat);
        $this->assertStringNotContainsString('localhost', $resultat);
        $this->assertStringEndsWith('/callBackPaiement', $resultat);
    }

    public function test_l_adresse_de_retour_est_toujours_en_https(): void
    {
        $this->assertStringStartsWith('https://', \Help::urlPaiement('http://127.0.0.1:8000/cb'));
        $this->assertStringStartsWith('https://', \Help::urlPaiement('https://graviers.example.net/cb'));
    }

    public function test_une_base_de_paiement_est_bien_configuree(): void
    {
        // Sans base configurée, le lien de paiement partirait avec l'adresse
        // interne du serveur : c'est le réglage qu'il ne faut pas oublier au
        // déploiement.
        $this->assertNotEmpty(
            env('PAIEMENT_BASE_URL') ?: config('app.url'),
            'Ni PAIEMENT_BASE_URL ni APP_URL ne sont renseignés.'
        );
    }
}
