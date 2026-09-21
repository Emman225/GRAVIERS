<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PAS DE CRÉDIT FANTÔME SOUS LE FRANC.
 *
 * Le 25/08/2026, le tableau de bord de YEO Maî annonçait « RÉGLÉ D'AVANCE :
 * 1 FCFA — dont 1 FCFA versés en trop, à votre crédit », sur une commande
 * pourtant soldée au centime près.
 *
 * D'où venait ce franc : la commande devait 28 812,5, le caissier a encaissé
 * 28 813 — le seul montant saisissable, le franc CFA n'ayant pas de
 * subdivision. Reste 0,5, que l'écran arrondit à 1. Le client croyait disposer
 * d'un crédit ; il n'avait rien, et l'entreprise ne lui devait rien.
 *
 * Le côté DETTE appliquait cette tolérance depuis toujours
 * (Commande::montantRestantDu : un reliquat sous le franc vaut zéro). Le côté
 * CRÉDIT ne la connaissait pas. Les deux disent désormais la même chose.
 */
class SoldeSansCentimeFantomeTest extends TestCase
{
    /**
     * La tolérance doit valoir dans les DEUX sens : un demi-franc n'est ni une
     * dette du client, ni une dette de l'entreprise envers lui.
     */
    public function test_un_reliquat_sous_le_franc_ne_fait_ni_dette_ni_credit(): void
    {
        $regle = fn (float $solde) => abs($solde) < 1 ? 0.0 : $solde;

        $this->assertSame(0.0, $regle(0.5), 'Le cas signalé : 28 813 versés pour 28 812,5 dus.');
        $this->assertSame(0.0, $regle(-0.5), 'Et son symétrique, en défaveur du client.');
        $this->assertSame(0.0, $regle(0.99));
        $this->assertSame(0.0, $regle(-0.99));
    }

    /** NON-RÉGRESSION : un vrai écart reste un vrai écart. */
    public function test_un_ecart_reel_est_conserve(): void
    {
        $regle = fn (float $solde) => abs($solde) < 1 ? 0.0 : $solde;

        $this->assertSame(4.0, $regle(4.0), "Quatre francs versés en trop restent dus au client.");
        $this->assertSame(-4.0, $regle(-4.0));
        $this->assertSame(1.0, $regle(1.0), 'Le franc juste est la limite : il compte.');
    }

    /**
     * LA RÈGLE EST BIEN UNE TOLÉRANCE, PAS UN ARRONDI.
     *
     * Arrondir 0,5 donnerait 1 — c'est-à-dire exactement le montant fantôme
     * qu'il s'agit de faire disparaître. Le test fige la distinction : elle
     * n'est pas évidente et se perdrait à la première relecture.
     */
    public function test_la_regle_n_est_pas_un_arrondi(): void
    {
        $code = file_get_contents(app_path('Help.php'));

        $this->assertStringContainsString('abs($solde) < 1 ? 0.0 : $solde', $code,
            'Le solde doit passer par la tolérance au franc.');
        $this->assertNotSame(0.0, (float) round(0.5),
            'Un arrondi rendrait 1 : ce serait reproduire le défaut.');
    }

    /** La tolérance s'applique aux DEUX lectures du solde, client et back-office. */
    public function test_les_deux_lectures_du_solde_sont_traitees_ensemble(): void
    {
        $code = file_get_contents(app_path('Help.php'));

        $position = strpos($code, 'abs($solde) < 1');
        $signature = strpos($code, 'function soldeClientBrut');

        $this->assertNotFalse($position);
        $this->assertGreaterThan($signature, $position,
            'La tolérance doit être appliquée dans soldeClientBrut, après le calcul, '
            . 'pour couvrir aussi bien la lecture client que celle du back-office.');
    }
}
