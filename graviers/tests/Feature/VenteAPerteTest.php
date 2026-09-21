<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ON NE PART PAS SERVIR UNE COMMANDE À PERTE SANS LE SAVOIR.
 *
 * Signalé le 03/09/2026 : « Bénéfices de DALAKOUN sur les ventes : −732 020 »
 * — vendu 1 073 380, versé aux fournisseurs 1 805 400.
 *
 * La cause : le prix facturé au client (`detail_commande.prix`, figé à la
 * commande) et le prix du fournisseur retenu (`stock_produit.prix`) sont deux
 * chiffres INDÉPENDANTS. Rien ne les comparait. Un fournisseur à 7 500 F
 * l'unité sur un article facturé 150 F passait sans un mot, et la perte
 * n'apparaissait qu'au récapitulatif — la marchandise déjà partie.
 *
 * Un garde-fou existait pour le prix d'achat À ZÉRO ; il manquait son
 * symétrique.
 */
class VenteAPerteTest extends TestCase
{
    /**
     * LES DEUX ÉCRANS DE TRAITEMENT REFUSENT LA PERTE.
     *
     * Avec livraison et sans livraison : un seul des deux protégé laisserait
     * la porte ouverte, et c'est celle-là qu'on emprunterait.
     */
    public function test_les_deux_ecrans_de_traitement_refusent_de_servir_a_perte(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/OrdersController.php'));

        $this->assertSame(2, substr_count($source, 'Vente à perte :'),
            'Les DEUX écrans de traitement doivent refuser : avec livraison '
            . 'et sans livraison. Un seul protégé laisse la porte ouverte.');

        $this->assertSame(2, substr_count($source, '$prixAchat >= $prixVente'),
            'La comparaison doit porter sur les deux prix, pas sur autre chose.');
    }

    /**
     * LE GARDE-FOU DU PRIX NUL RESTE EN PLACE.
     *
     * Il vit juste au-dessus : un bon sans prix d'achat vaudrait un dû de
     * zéro, et le fournisseur ne serait jamais payé. Le nouveau contrôle ne
     * doit pas l'avoir remplacé.
     */
    public function test_le_controle_du_prix_nul_subsiste(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/OrdersController.php'));

        $this->assertSame(2, substr_count($source, 'vaudrait un dû de 0'),
            'Le contrôle du prix d\'achat nul a disparu.');
    }

    /** LE PRIX NÉGOCIÉ SAISI À LA MAIN EST BIEN CELUI QU'ON COMPARE. */
    public function test_le_prix_saisi_a_la_main_est_pris_en_compte(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/OrdersController.php'));

        // Sinon le contrôle comparerait le tarif du stock alors que le
        // gestionnaire a saisi un prix négocié : il refuserait une vente qui
        // ne perd rien, ou laisserait passer celle qui perd.
        $this->assertSame(2, substr_count($source,
            "\$prixAchat = (float) (\$request->filled('prix_fournisseur')"),
            'Le contrôle doit porter sur le prix RETENU, négocié compris.');
    }
}
