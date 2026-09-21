<?php

namespace Tests\Feature;

use App\Models\Livraison;
use Tests\TestCase;

/**
 * UNE COURSE REFUSÉE NE DOIT PLUS SE LIRE « EN ATTENTE ».
 *
 * Au refus, seule la colonne `accepte` passe à 3. `etat_livraison` garde sa
 * dernière valeur — « EN ATTENTE » le plus souvent. Conséquences constatées
 * le 24/08/2026 sur la commande 536115 :
 *
 *   · la fiche de commande proposait DEUX codes « à communiquer au client »,
 *     dont un refusé, et l'annonçait « EN ATTENTE » ;
 *   · l'écran du client annonçait la même chose : une livraison qui attend,
 *     alors que plus personne ne s'en occupait.
 *
 * On ne corrige PAS en écrivant « REFUSÉE » dans la colonne : c'est un ENUM
 * fermé, la valeur y serait silencieusement tronquée. L'information existe
 * déjà dans `accepte` — on la lit.
 */
class CourseRefuseeLisibleTest extends TestCase
{
    /** L'état affiché doit dire le refus, quel que soit l'état d'avancement figé. */
    public function test_une_course_refusee_se_lit_refusee(): void
    {
        $refusee = new Livraison([
            'etat_livraison' => 'EN ATTENTE',
            'accepte'        => Livraison::REFUSEE,
        ]);
        $refusee->accepte = Livraison::REFUSEE;

        $this->assertTrue($refusee->estRefusee());
        $this->assertSame('REFUSÉE PAR LE LIVREUR', $refusee->etatLisible(),
            "Une course refusée ne doit jamais s'annoncer « EN ATTENTE ».");
    }

    /** NON-RÉGRESSION : une course normale garde son état d'avancement. */
    public function test_une_course_normale_garde_son_etat(): void
    {
        foreach (['EN ATTENTE', 'EN TRAITEMENT', 'EN COURS LIVRAISON', 'LIVREE'] as $etat) {
            $course = new Livraison(['etat_livraison' => $etat]);
            $course->accepte = Livraison::A_ACCEPTER;

            $this->assertFalse($course->estRefusee());
            $this->assertSame($etat, $course->etatLisible());
        }
    }

    /**
     * LA COLONNE RESTE UN ENUM FERMÉ.
     *
     * Si quelqu'un ajoute un jour « REFUSEE » à la liste, ce test le lui
     * rappellera : il faudra alors revoir chaque écran qui filtre sur cet état,
     * sous peine de voir des lignes disparaître sans prévenir.
     */
    public function test_la_colonne_n_a_pas_ete_elargie_en_douce(): void
    {
        $type = \Illuminate\Support\Facades\DB::selectOne(
            "SHOW COLUMNS FROM livraison WHERE Field = 'etat_livraison'"
        )->Type;

        $this->assertStringNotContainsString('REFUS', strtoupper($type),
            "L'ENUM a été élargi : vérifiez tous les écrans qui filtrent sur etat_livraison.");
    }

    /** Les deux écrans concernés doivent passer par l'état lisible. */
    public function test_les_ecrans_affichent_l_etat_lisible(): void
    {
        $fiche = file_get_contents(resource_path('views/orders/orders-details.blade.php'));

        $this->assertStringContainsString('etatLisible()', $fiche);
        $this->assertStringContainsString('coursesRefusees', $fiche,
            "Les codes refusés doivent être séparés de ceux à communiquer au client.");

        $ecranClient = file_get_contents(resource_path('views/client/recuperationProduit.blade.php'));

        $this->assertStringContainsString('estRefusee()', $ecranClient,
            "L'écran du client doit distinguer une course refusée d'une course en attente.");
    }
}
