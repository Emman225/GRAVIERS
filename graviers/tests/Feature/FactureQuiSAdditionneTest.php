<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UNE FACTURE DOIT S'ADDITIONNER.
 *
 * Un client doit pouvoir refaire le calcul au stylo. La facture de location
 * U260000000002 du 25/08/2026 ne le permettait pas :
 *
 *   TOTAL HT        22 000
 *   TVA              7 920   <- 36 % d'un HT annoncé à 18 %
 *   Coût livraison   4 000
 *   TOTAL TTC       29 920   <- la livraison n'y était pas
 *   TOTAL A PAYER   67 840   <- sans rapport avec ce qui précède
 *
 * Trois défauts distincts, une seule cause : chaque total venait d'une source
 * différente au lieu de découler des lignes imprimées.
 *
 *   · la TVA reprenait la valeur STOCKÉE, qui pouvait contredire les lignes ;
 *   · le TTC imprimait « HT + TVA » alors que $totalTTC, calculé juste au-dessus
 *     avec la livraison et la remise, n'était jamais utilisé ;
 *   · le total à payer reprenait facture.montant, dont la formule ajoutait TVA
 *     et livraison à un montant_total qui les contenait déjà.
 *
 * Après correction : 22 000 + 3 960 + 4 000 = 29 960, à payer 29 960.
 */
class FactureQuiSAdditionneTest extends TestCase
{
    private function gabarit(string $nom): string
    {
        return file_get_contents(resource_path("views/document/{$nom}.blade.php"));
    }

    /** Le TTC imprimé doit être celui qui comprend livraison et remise. */
    public function test_le_ttc_imprime_comprend_la_livraison(): void
    {
        foreach (['factureLocation', 'factureCommande'] as $doc) {
            $g = $this->gabarit($doc);

            $this->assertStringNotContainsString(
                "TOTAL TTC</td>\n            <td class=\"valeur\">{{ number_format(\$totalHT + \$totalTVA",
                $g,
                "{$doc} : le TTC ne doit pas se limiter à « HT + TVA » — la livraison manquerait."
            );
            $this->assertStringContainsString('number_format($totalTTC', $g,
                "{$doc} : le TTC calculé doit être celui qu'on imprime.");
        }
    }

    /** La TVA de la facture de location se calcule sur le HT imprimé. */
    public function test_la_tva_de_location_decoule_des_lignes(): void
    {
        $g = $this->gabarit('factureLocation');

        $this->assertStringContainsString(
            '$totalTVA      = \Help::arrondiFranc(max(0, $totalHT - $remise) * ($tauxTva / 100));',
            $g,
            'La TVA doit se recalculer sur le HT après remise, et non reprendre une valeur stockée '
            . 'qui peut le contredire.'
        );

        $this->assertStringContainsString('$totalAPayer = $totalTTC + $airsi;', $g,
            'Le total à payer EST le TTC : il ne doit pas venir d\'une autre formule.');
    }

    /**
     * LE MONTANT STOCKÉ SUIT LA MÊME FORMULE QUE LE DOCUMENT.
     *
     * Sans cela, le PDF, le solde du client et le TTC du code-barres se
     * contredisent — et c'est le code-barres qui fait foi devant la DGI.
     */
    public function test_le_montant_stocke_repart_des_lignes(): void
    {
        $controleur = file_get_contents(app_path('Http/Controllers/OrdersController.php'));
        $debut = strpos($controleur, 'public function genererFactureLocation');
        $corps = substr($controleur, $debut, 2000);

        $this->assertStringContainsString("\$ht        = (float) \$location->detailLocation->sum('prix');", $corps,
            'Le HT doit se relire depuis les lignes, seule source non ambiguë.');
        $this->assertStringNotContainsString('$location->montant_total', $corps,
            'montant_total contient déjà TVA et livraison : les rajouter les compte deux fois.');
    }

    /** L'arithmétique de la facture signalée, refaite. */
    public function test_les_chiffres_de_la_facture_signalee(): void
    {
        $ht = 22000.0;
        $remise = 0.0;
        $livraison = 4000.0;

        $tva = \Help::arrondiFranc(max(0, $ht - $remise) * 0.18);
        $ttc = max(0, $ht - $remise) + $tva + $livraison;

        $this->assertSame(3960.0, $tva, 'TVA : 18 % de 22 000.');
        $this->assertSame(29960.0, $ttc, 'TTC : 22 000 + 3 960 + 4 000.');
    }

    /**
     * LA FACTURE DE VENTE ASSIED SA TVA SUR LE HT REMISE DÉDUITE.
     *
     * Elle la calculait sur le HT BRUT : 18 % de 24 750 = 4 455, alors que le
     * client devait 18 % de 21 027,5 = 3 785. La facture U260000000001
     * annonçait « TVA 4 455, TOTAL TTC 29 483 » sous un « TOTAL A PAYER
     * 28 812 » — 671 F d'écart, et une remise taxée après avoir été accordée.
     */
    public function test_la_tva_de_vente_deduit_la_remise(): void
    {
        $g = $this->gabarit('factureCommande');

        $this->assertStringContainsString('$totalHT * (1 - $partRemise)', $g,
            'La TVA doit porter sur le HT après remise, comme le montant réclamé au client.');
        $this->assertStringContainsString('$totalAPayer = $totalTTC + $airsi;', $g,
            "Le total à payer EST le TTC imprimé : deux formules finissent par diverger.");
    }

    /**
     * LE TOTAL S'ADDITIONNE À PARTIR DES MONTANTS IMPRIMÉS.
     *
     * Chaque poste s'affiche arrondi ; si le total part des valeurs brutes, le
     * papier ne tombe pas juste. Avec une remise réelle de 3 722,5, le total
     * brut donnait 28 813 sous des postes qui totalisaient 28 812.
     */
    public function test_le_total_se_verifie_au_stylo(): void
    {
        foreach (['factureCommande', 'factureLocation'] as $doc) {
            // Les espaces different d un gabarit a l autre : on les normalise
            // plutot que de jongler avec les echappements d une expression.
            $normalise = preg_replace('/\s+/', ' ', $this->gabarit($doc));

            $this->assertStringContainsString(
                '$remise = \Help::arrondiFranc($remise)',
                $normalise,
                "{$doc} : la remise doit etre arrondie AVANT d entrer dans le total, "
                . "sinon les postes imprimes ne s additionnent pas."
            );
        }

        // L'addition de la facture U260000000001, refaite au stylo.
        $this->assertSame(28812, 24750 - 3723 + 3785 + 4000);
    }

    /**
     * LA LIGNE DOIT SE LIRE SANS AMBIGUÏTÉ.
     *
     * « (location 2 j) — P.U 22 000 — Qté 1 » invitait à multiplier par deux,
     * alors que le prix couvre déjà la période. Le tarif journalier lève le
     * doute.
     */
    public function test_la_ligne_donne_le_tarif_journalier(): void
    {
        $this->assertStringContainsString('/jour', $this->gabarit('factureLocation'),
            'La désignation doit donner le tarif par jour, pour que la période se vérifie.');
    }
}
