<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LA LOCATION REÇOIT LE MÊME TRAITEMENT QUE LA VENTE.
 *
 * Trois manques, tous constatés le 24/08/2026 :
 *
 *   · le code de validation d'une location n'apparaissait NULLE PART dans le
 *     back-office. Quand le courriel au client échouait, plus personne ne
 *     pouvait le lui redonner — et le livreur ne pouvait plus valider ;
 *
 *   · cet échec était SILENCIEUX : un simple avertissement au journal. Le
 *     gestionnaire lisait « Location validée » et ne savait pas que le client
 *     n'avait rien reçu ;
 *
 *   · une course refusée continuait de porter un code, présenté comme valide.
 *     Le donner au client, c'est le bloquer devant le livreur.
 */
class CodeLocationRetrouvableTest extends TestCase
{
    private function ecran(string $vue): string
    {
        return file_get_contents(resource_path("views/{$vue}.blade.php"));
    }

    /**
     * LES CODES SONT RETROUVABLES CHEZ LE CLIENT, PLUS AU BACK-OFFICE.
     *
     * Règle du 10/09/2026 : « Locations traitées » ne montre plus le code de
     * livraison ni le bon d'enlèvement. Le client les lit sur Mon compte, le
     * détail de sa location et l'application ; le livreur lit le bon dans la
     * sienne. Un courriel manqué ne bloque donc plus rien. Le gestionnaire
     * garde l'ÉTAT de la course et de l'enlèvement.
     */
    public function test_les_codes_de_location_sont_retrouvables(): void
    {
        $ecran = $this->ecran('gestionnaire/locationsTraitees');

        $this->assertStringContainsString('Livraison / enlèvement', $ecran);
        $this->assertStringNotContainsString('Codes à communiquer', $ecran);
        $this->assertStringNotContainsString('{{ $course->numero }}', $ecran, 'Le code de livraison ne se lit plus ici.');
        $this->assertStringContainsString("where('provenance', 'LOCATION')", $ecran,
            'Seules les courses de LOCATION doivent être listées ici.');

        // Le client, lui, les a sous les yeux (Mon compte et détail de la location).
        foreach (['client/monCompte', 'client/detailDeLocation'] as $vue) {
            $this->assertStringContainsString("@include('client._codesLivraison'", $this->ecran($vue), $vue);
            $this->assertStringContainsString('coursesAcceptees()', $this->ecran($vue), $vue);
        }
    }

    /** Une course refusée reste mise à part, sans en montrer le code. */
    public function test_une_course_refusee_est_mise_a_part(): void
    {
        $ecran = $this->ecran('gestionnaire/locationsTraitees');

        $this->assertStringContainsString('estRefusee()', $ecran,
            'Le tri des courses doit se faire sur le refus, pas sur etat_livraison qui ment.');
        $this->assertStringContainsString('refusée(s) par le livreur', $ecran);
        $this->assertStringNotContainsString('{{ $refusee->numero }}', $ecran, 'Même refusé, un code ne s\'affiche plus.');
        $this->assertStringContainsString('etatLisible()', $ecran,
            "L'état affiché doit passer par etatLisible(), sinon un refus se lit « EN ATTENTE ».");
    }

    /**
     * L'ÉCHEC D'ENVOI DOIT ÊTRE DIT, ET SUR UNE CLÉ QUE FLASHER N'AVALE PAS.
     */
    public function test_l_echec_d_envoi_est_signale_au_gestionnaire(): void
    {
        $controleur = file_get_contents(app_path('Http/Controllers/UserController.php'));

        $this->assertStringContainsString("'code_non_envoye'", $controleur,
            "La validation d'une location doit signaler un code non transmis.");
        // CE QUI COMPTE EST LE NUMÉRO, PAS LA TOURNURE.
        //
        // Cet essai exigeait le libellé « Code de validation location ».
        // Depuis qu'un retrait sur place envoie un code d'ENLÈVEMENT, cette
        // formulation est fausse la moitié du temps — et l'essai exigeait
        // donc qu'elle le reste. Il vérifie maintenant que le numéro de la
        // location est journalisé, et que le type de code est nommé.
        $this->assertStringContainsString('{$location->numero} non envoyé', $controleur,
            "L'échec doit être journalisé avec le numéro de la location : "
            . 'sans lui, personne ne sait quel client rappeler.');

        $this->assertStringContainsString('$estRetrait ? "retrait" : "validation"', $controleur,
            'Le journal doit dire DE QUEL code il s’agit : un code de retrait '
            . 'et un code de validation ne se rattrapent pas de la même façon.');

        $this->assertStringContainsString("session('code_non_envoye')",
            $this->ecran('gestionnaire/listeLocation'),
            "L'écran d'arrivée doit afficher l'avertissement, sinon il n'existe que dans le journal.");
    }

    /**
     * LE REFUS CÔTÉ API DOIT RENDRE LA LOCATION RÉAFFECTABLE.
     *
     * Sans cela, la location restait « EN COURS » — donc absente de l'écran
     * d'affectation, qui ne liste que les « EN ATTENTE ». Elle disparaissait
     * purement et simplement, sans un mot d'explication.
     */
    public function test_le_refus_rend_la_location_reaffectable(): void
    {
        $api = base_path('../apigravier/app/Http/Controllers/LivreurController.php');

        if (!is_file($api)) {
            $this->markTestSkipped("Le projet apigravier n'est pas à côté de graviers.");
        }

        $code = file_get_contents($api);

        $this->assertStringContainsString('Help::$LOCATION_EN_ATTENTE', $code,
            'Au refus, la location doit repasser « EN ATTENTE ».');
        $this->assertStringContainsString("'livreur_id'    => null", $code,
            'Le livreur qui refuse doit être détaché, sinon la fiche le montre encore en charge.');
    }
}
