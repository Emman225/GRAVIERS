<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE CODE DU BON D'ENLÈVEMENT DOIT ATTEINDRE L'APPLICATION DU LIVREUR.
 *
 * Depuis le 28/08/2026, valider une location produit un bon d'enlèvement,
 * comme une vente. Encore faut-il que son code parvienne au téléphone du
 * livreur : c'est ce qu'il montre au fournisseur pour retirer le matériel.
 *
 * La chaîne complète, du plus profond au plus visible :
 *
 *   liste-livraison  ->  LivraisonController::listeLivraison
 *                    ->  Livraison::liste($livreur->id)
 *                    ->  leftJoin('enlevement') + alias `code_enlevement`
 *                    ->  RetourLivraison.code_enlevement (Flutter)
 *                    ->  « N°BE: … » sur la liste et sur le détail
 *
 * Trois maillons peuvent la rompre sans bruit, et ces essais les tiennent :
 *
 *   · une JOINTURE INTERNE sur `enlevement` ferait disparaître toute course
 *     qui n'a pas de bon — les demandes de livraison, et les locations
 *     validées avant ce changement ;
 *
 *   · une jointure interne sur `type_livraison` ferait disparaître les
 *     LOCATIONS : leur création ne renseigne pas cette colonne. La version du
 *     site emploie d'ailleurs des jointures internes ici, et ne conviendrait
 *     pas ;
 *
 *   · un filtre sur `provenance` écarterait les locations d'un trait.
 */
class CodeEnlevementSurMobileTest extends TestCase
{
    private function requeteDeListe(): string
    {
        $source = file_get_contents(app_path('Models/Livraison.php'));

        // Les commentaires sont retirés avant toute vérification : sans cela,
        // un essai trouve les mots qu'il cherche dans une explication et passe
        // au vert alors que le code exécuté a disparu.
        $source = preg_replace('!/\*.*?\*/!s', '', $source);
        $source = preg_replace('!^\s*//.*$!m', '', $source);

        $debut = strpos($source, 'function liste(');

        // La borne est « public ... function », et non le simple mot « function » :
        // les fermetures passees a when() en contiennent, et l'extrait s'arretait
        // au tiers de la requete — les filtres places apres n'etaient donc
        // controles par personne.
        $fin = strpos($source, 'public static function', $debut + 10);
        $suivante = strpos($source, 'public function', $debut + 10);

        if ($suivante !== false && ($fin === false || $suivante < $fin)) {
            $fin = $suivante;
        }

        return substr($source, $debut, ($fin === false ? strlen($source) : $fin) - $debut);
    }

    /** LE CODE EST BIEN SERVI, sous le nom qu'attend l'application. */
    public function test_la_liste_expose_le_code_enlevement(): void
    {
        $requete = $this->requeteDeListe();

        $this->assertStringContainsString(
            'enlevement.code_enleve as code_enlevement',
            $requete,
            "Le code n'est pas renvoyé sous l'alias attendu : l'application lit "
            . '`code_enlevement`, et afficherait un N°BE vide.'
        );
    }

    /** LA JOINTURE RESTE EXTERNE : une course sans bon ne doit pas disparaître. */
    public function test_les_jointures_fragiles_restent_externes(): void
    {
        $requete = $this->requeteDeListe();

        foreach (['enlevement', 'type_livraison', 'adresse_livraison', 'client'] as $table) {
            $this->assertStringContainsString(
                "->leftJoin('" . $table . "'",
                $requete,
                "La jointure sur `$table` n'est plus externe. Une course qui n'a "
                . "pas de ligne dans cette table disparaîtrait de la tournée du "
                . 'livreur, sans message ni erreur.'
            );
        }
    }

    /** AUCUN FILTRE DE PROVENANCE : ventes, locations et transports cohabitent. */
    public function test_aucune_provenance_n_est_ecartee(): void
    {
        $requete = $this->requeteDeListe();

        $this->assertStringNotContainsString(
            "'provenance'",
            $requete,
            'Un filtre sur la provenance est apparu : il écarterait les locations '
            . "de la tournée du livreur, et le code d'enlèvement avec elles."
        );
    }

    /**
     * L'APPLICATION AFFICHE LE CODE APRÈS ACCEPTATION.
     *
     * C'est la demande précise : le livreur voit le code une fois qu'il a
     * accepté la course, pour le communiquer au fournisseur.
     */
    public function test_l_application_affiche_le_code_une_fois_la_course_acceptee(): void
    {
        $racine = dirname(base_path()) . DIRECTORY_SEPARATOR . 'mon_gravier_mobile_livreur';

        if (!is_dir($racine)) {
            $this->markTestSkipped("Le dossier de l'application livreur n'est pas à côté de l'API.");
        }

        $detail = file_get_contents(
            $racine . '/lib/screens/details_livraison/details_livraison_screen.dart'
        );

        $this->assertStringContainsString('livraison.accepte == 1', $detail,
            "Le code doit n'apparaître qu'une fois la course acceptée.");

        // Libellé en clair depuis le 08/09/2026 (« N°BE » auparavant), avec
        // copie et partage par WhatsApp.
        $this->assertStringContainsString("'Numéro bon enlèvement'", $detail,
            "Le code d'enlèvement ne s'affiche plus sur le détail de la course.");
        $this->assertStringContainsString('CodePartageable(', $detail,
            'Le bon doit se copier et se partager par WhatsApp.');

        $modele = file_get_contents($racine . '/lib/models/retour_livraison.dart');

        $this->assertStringContainsString("code_enlevement = json['code_enlevement']", $modele,
            "L'application ne lit plus le champ renvoyé par l'API : le N°BE "
            . 'resterait vide quoi que le serveur envoie.');
    }
}
