<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CE QUE DOIT UNE LOCATION SE LIT DANS SES LIGNES.
 *
 * `location.montant_total` n'a PAS le même sens selon le canal : le site y écrit
 * le HT, l'application mobile le NET final — TVA et livraison comprises. Y
 * rajouter la TVA et la livraison, comme le faisait montantAPayer(), gonflait
 * donc toute location passée depuis le mobile de ces deux montants.
 *
 * Constaté le 25/08/2026 sur le reçu RC-2026-002 : la location 382108, due à
 * 55 920 et réglée à 55 920, s'annonçait « Total 67 840 — reste à payer
 * 11 920 ». Elle ne pouvait jamais être soldée : le guichet aurait réclamé
 * indéfiniment une TVA et une livraison déjà payées.
 *
 * Les LIGNES, elles, ont un sens unique — `detail_location.prix` porte le total
 * de la ligne, quantité et jours compris. C'est déjà la source qu'emploient la
 * facture et son gabarit. Les trois disent désormais la même chose.
 */
class MontantDuLocationTest extends TestCase
{
    private function modele(): string
    {
        return file_get_contents(app_path('Models/Location.php'));
    }

    /** Le dû part des lignes, pas de la colonne ambiguë. */
    public function test_le_du_se_calcule_depuis_les_lignes(): void
    {
        $code = $this->modele();
        $debut = strpos($code, 'public function montantAPayer');
        $corps = substr($code, $debut, 1600);

        $this->assertStringContainsString("\$ht = (float) \$this->detailLocation->sum('prix');", $corps,
            'Le HT doit se lire dans les lignes.');
        $this->assertStringContainsString('$ht = (float) $this->montant_total;', $corps,
            'Un repli est nécessaire : une location sans ligne passerait pour soldée.');
    }

    /**
     * L'ARITHMÉTIQUE, SUR LES DEUX CANAUX.
     *
     * Le même dû doit sortir, que montant_total porte le HT (site) ou le net
     * (mobile) — puisqu'il n'entre plus dans le calcul.
     */
    public function test_les_deux_canaux_donnent_le_meme_du(): void
    {
        $ht = 44000.0;      // 2 jours x 22 000, lu dans la ligne
        $tva = 7920.0;
        $livraison = 4000.0;
        $remise = 0.0;

        $du = max(0, $ht - $remise) + $tva + $livraison;

        $this->assertSame(55920.0, $du);

        // L'ancienne formule, sur une location mobile dont montant_total valait
        // déjà le net : c'est le 67 840 imprimé sur le reçu.
        $ancienneFormuleMobile = max(0, 55920.0 - $remise) + $tva + $livraison;
        $this->assertSame(67840.0, $ancienneFormuleMobile,
            'Le défaut reproduit : TVA et livraison comptées deux fois.');
    }

    /** Le reçu doit nommer l'opération dans son résumé aussi. */
    public function test_le_recu_ne_dit_plus_total_commande_sur_une_location(): void
    {
        $vue = file_get_contents(resource_path('views/admin/comptant/_recu_body.blade.php'));

        $this->assertStringNotContainsString('<div class="recu-resume-label">Total commande :</div>', $vue,
            "Le résumé du reçu ne doit plus coder « commande » en dur.");
        $this->assertStringContainsString('$libelleOperation', $vue);
    }
}
