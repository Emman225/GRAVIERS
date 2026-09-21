<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CHAQUE PAGE DE CONNEXION RAMÈNE AU SITE PUBLIC.
 *
 * Les connexions des espaces — administration, apporteur, fournisseur,
 * livreur — s'affichent seules, sans en-tête ni menu. Quelqu'un qui arrivait
 * là par erreur, ou qui renonçait à se connecter, n'avait plus aucun chemin
 * vers la boutique : il fallait retaper l'adresse à la main.
 *
 * Le test parcourt les CINQ pages. En viser une seule laisserait les autres
 * se faire remanier sans que le lien manquant se voie.
 */
class RetourSitePublicTest extends TestCase
{
    /** Les pages de connexion, et la route publique où chacune doit ramener. */
    public static function pagesDeConnexion(): array
    {
        return [
            'administration' => ['show.login'],
            'apporteur'      => ['apporteur.login'],
            'fournisseur'    => ['sellers.login'],
            'livreur'        => ['livreur.login'],
            'client'         => ['client.login'],
        ];
    }

    /**
     * @dataProvider pagesDeConnexion
     */
    public function test_la_page_de_connexion_mene_au_site_public(string $routeConnexion): void
    {
        $reponse = $this->get(route($routeConnexion));

        $reponse->assertOk();

        // On vise l'adresse d'accueil, pas un libellé : le texte du bouton peut
        // être réécrit sans que le chemin disparaisse, et l'inverse serait un
        // vrai défaut.
        $reponse->assertSee('href="' . route('client.index') . '"', false);
        $reponse->assertSee('Retour au site');
    }

    /**
     * Les quatre pages autonomes partagent le MÊME partiel : sans cela, le
     * libellé et la position se mettraient à diverger d'un espace à l'autre.
     */
    public function test_les_espaces_partagent_le_meme_bouton(): void
    {
        $vues = [
            'compte/account-login',
            'apporteur/login',
            'fournisseur/login',
            'livreur/login',
        ];

        foreach ($vues as $vue) {
            $this->assertStringContainsString(
                "@include('layout._retourSitePublic')",
                file_get_contents(resource_path("views/{$vue}.blade.php")),
                "La page {$vue} doit inclure le partiel commun, pas sa propre copie."
            );
        }
    }
}
