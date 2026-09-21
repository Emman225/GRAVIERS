<?php

namespace Tests\Feature;

use App\Models\Configuration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE COMMANDE NE PEUT PAS TOMBER À ZÉRO FRANC.
 *
 * Les points de fidélité pouvaient couvrir la totalité d'une commande. Il
 * suffisait que le client vienne chercher sa marchandise — donc aucun coût de
 * livraison — et que ses points valent le montant du panier.
 *
 * Le total tombait alors à 0, et la commande devenait une IMPASSE :
 *
 *   · EN LIGNE, la passerelle était appelée avec un montant de 0 et la commande
 *     restait « EN ATTENTE DE PAIEMENT », donc INVISIBLE dans la file du
 *     gestionnaire — les points, eux, étant déjà débités ;
 *   · EN AGENCE, l'encaissement exige au moins 1 franc (`min:1`) : le caissier
 *     ne pouvait pas la solder ;
 *   · PAR VIREMENT, il fallait le justificatif d'un virement de 0.
 *
 * Les points ne réduisent donc plus le total en dessous du minimum paramétré.
 * Le reliquat RESTE au compte du client.
 */
class PlancherDePaiementTest extends TestCase
{
    use DatabaseTransactions;

    private function plancher(float $valeur): void
    {
        Configuration::first()->update(['montant_minimum_a_payer' => $valeur]);
    }

    /**
     * LE CAS QUI CASSAIT TOUT : des points qui couvrent tout, sans livraison.
     */
    public function test_les_points_ne_ramenent_jamais_le_total_a_zero(): void
    {
        $this->plancher(1000);

        // 22 000 F de marchandise, aucune livraison, TVA 18 %, le point vaut
        // 10 F, et le client a de quoi tout couvrir.
        $points = \Help::pointsUtilisables(22000, 0, 18, 0, 10, 5000, 5000);

        $ht = 22000 - ($points * 10);
        $total = $ht * 1.18;

        $this->assertGreaterThanOrEqual(1000, round($total),
            "Le total à payer ne doit jamais descendre sous le plancher : "
            . "à zéro, la commande ne peut être réglée par AUCUN moyen.");
        $this->assertLessThan(5000, $points,
            'Tous les points ne peuvent pas être posés sur cette commande.');
    }

    /**
     * LE RELIQUAT RESTE AU CLIENT.
     *
     * C'est la contrepartie de la règle : on ne consomme que les points
     * réellement utiles. Le client ne perd rien, sa remise est étalée.
     */
    public function test_seuls_les_points_utiles_sont_consommes(): void
    {
        $this->plancher(1000);

        $demandes = 5000.0;
        $retenus = \Help::pointsUtilisables(22000, 0, 18, 0, 10, $demandes, 5000);

        $this->assertLessThan($demandes, $retenus);

        // Un point de plus ferait passer sous le plancher : la coupure est au
        // bon endroit, on ne bride pas plus que nécessaire.
        $totalAvec = (22000 - $retenus * 10) * 1.18;
        $totalAvecUnDePlus = (22000 - ($retenus + 1) * 10) * 1.18;

        $this->assertGreaterThanOrEqual(1000, round($totalAvec));
        $this->assertLessThan(1000, round($totalAvecUnDePlus),
            "La limite doit être serrée : un point de plus doit franchir le "
            . "plancher, sinon on prive le client d'une remise possible.");
    }

    /**
     * LA LIVRAISON SUFFIT À TENIR LE PLANCHER.
     *
     * Elle n'est jamais effacée par une remise. Dès qu'elle atteint à elle
     * seule le minimum, les points peuvent couvrir toute la marchandise : le
     * client paiera son transport, et le total ne sera pas nul.
     */
    public function test_une_livraison_suffisante_libere_tous_les_points(): void
    {
        $this->plancher(1000);

        $retenus = \Help::pointsUtilisables(22000, 0, 18, 4000, 10, 5000, 5000);

        $this->assertSame(2200.0, $retenus,
            'Les points peuvent couvrir tout le HT : la livraison de 4 000 F '
            . 'reste due, et le total vaut donc 4 000 F.');
    }

    /** Le code promo est un engagement déjà pris : ce sont les points qui cèdent. */
    public function test_le_code_promo_passe_avant_les_points(): void
    {
        $this->plancher(1000);

        // 22 000 F de HT, 20 000 F déjà retirés par un code promo.
        $retenus = \Help::pointsUtilisables(22000, 20000, 18, 0, 10, 5000, 5000);

        $resteHt = 22000 - 20000 - ($retenus * 10);
        $this->assertGreaterThanOrEqual(1000, round($resteHt * 1.18),
            'La remise promo est conservée, les points s\'arrêtent au plancher.');
    }

    /** On ne consomme jamais plus que ce que le client possède. */
    public function test_le_solde_du_client_reste_la_limite_absolue(): void
    {
        $this->plancher(0);

        $this->assertSame(12.0, \Help::pointsUtilisables(500000, 0, 18, 0, 10, 5000, 12),
            "Solde de 12 points : on n'en pose pas 5 000.");
    }

    /**
     * PLANCHER NON PARAMÉTRÉ : on ne bride rien.
     *
     * Mieux vaut l'ancien comportement qu'un plancher inventé — un écran de
     * paramétrage laissé vide ne doit pas changer la règle commerciale dans le
     * dos du gestionnaire.
     */
    public function test_un_plancher_a_zero_retire_la_limite(): void
    {
        $this->plancher(0);

        $this->assertSame(5000.0,
            \Help::pointsUtilisables(22000, 0, 18, 0, 10, 5000, 5000));
    }

    /** Un point sans valeur ne réduit rien : on n'en consomme aucun. */
    public function test_un_point_sans_valeur_n_est_pas_consomme(): void
    {
        $this->plancher(1000);

        $this->assertSame(0.0,
            \Help::pointsUtilisables(22000, 0, 18, 0, 0, 5000, 5000));
    }
}
