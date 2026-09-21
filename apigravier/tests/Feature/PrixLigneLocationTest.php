<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE PRIX D'UNE LIGNE DE LOCATION EST LE TOTAL DE LA PÉRIODE.
 *
 * `detail_location.prix` porte le TOTAL de la ligne — prix unitaire × quantité ×
 * nombre de jours. C'est la convention du site, et celle dont vivent la facture
 * et le montant réclamé au client.
 *
 * L'API mobile y recopiait le prix UNITAIRE JOURNALIER, tel que CalculMontant le
 * lui rend : ce service garde le prix unitaire dans la ligne et calcule le HT à
 * part. Une bétonnière à 22 000/jour louée 2 jours s'écrivait donc 22 000.
 *
 * Le défaut se voyait à l'œil nu sur la facture U260000000002 du 25/08/2026 :
 *
 *   TOTAL HT   22 000     <- le tarif d'UNE journée
 *   TVA         7 920     <- 18 % de 44 000, le VRAI dû
 *
 * La TVA était juste, la ligne fausse — l'inverse de ce qu'on croyait d'abord.
 * Le client était sous-facturé de 22 000 F sur cette location.
 */
class PrixLigneLocationTest extends TestCase
{
    private function controleur(): string
    {
        return file_get_contents(app_path('Http/Controllers/LocationController.php'));
    }

    /** Le prix écrit doit multiplier par la quantité ET par les jours. */
    public function test_le_prix_de_ligne_inclut_la_quantite_et_les_jours(): void
    {
        $code = $this->controleur();

        $this->assertStringContainsString("\$ligne->prix = (float) \$l['prix']", $code);
        $this->assertStringContainsString("* (float) \$l['qte']", $code);
        $this->assertStringContainsString("* max(1, (float) (\$l['nbreJours'] ?? 1));", $code);

        $this->assertStringNotContainsString("\$ligne->prix = \$l['prix'];", $code,
            "Le prix unitaire ne doit pas être recopié tel quel : la colonne porte le total de la ligne.");
    }

    /**
     * CALCULMONTANT REND BIEN UN PRIX UNITAIRE.
     *
     * C'est la prémisse du correctif. Si ce service se mettait un jour à rendre
     * un total de ligne, multiplier une seconde fois DOUBLERAIT la facture — et
     * ce test le signalera avant le client.
     */
    public function test_le_calcul_partage_rend_un_prix_unitaire(): void
    {
        $service = file_get_contents(app_path('Services/CalculMontant.php'));

        $this->assertStringContainsString("\$lignes[\$cle]['prix'] = \$prixServeur;", $service,
            'La ligne doit porter le prix unitaire du catalogue.');
        $this->assertStringContainsString('$ht += $prixServeur * $qte * $jours;', $service,
            'Le HT, lui, applique la quantité et les jours — preuve que le prix de ligne est unitaire.');
    }

    /** L'arithmétique du cas signalé. */
    public function test_les_chiffres_de_la_location_signalee(): void
    {
        $tarifJournalier = 22000.0;
        $quantite = 1.0;
        $jours = 2.0;

        $prixLigne = $tarifJournalier * $quantite * $jours;

        $this->assertSame(44000.0, $prixLigne,
            'Une bétonnière à 22 000/jour louée 2 jours vaut 44 000, pas 22 000.');

        $this->assertSame(7920.0, round($prixLigne * 0.18),
            'La TVA de 7 920 réellement enregistrée correspond à ce HT : elle était juste.');
    }
}
