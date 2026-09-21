<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE PLAFOND DE CRÉDIT DOIT VALOIR AUSSI POUR LES LOCATIONS RÉGLÉES HORS LIGNE.
 *
 * Constaté en production le 29/08/2026 : le client 55, passé à terme à
 * 00 h 25 min 39 s avec un plafond de 5 000 F, a enregistré trois locations à
 * 00 h 30, 00 h 35 et 00 h 42 — 22 000, 104 500 et 104 500 F, toutes en
 * « paiement en agence ». Aucune n'a été refusée.
 *
 * Le contrôle existait sur les autres voies — le devis, la vente en agence,
 * l'API mobile — mais pas sur `enregistrementLocation`, qui est justement
 * celle des règlements HORS LIGNE, donc celle des clients à terme.
 *
 * Une quatrième copie du contrôle existait dans `enregistrementDeLocation` :
 * cette méthode n'est appelée de nulle part. Elle donnait l'illusion d'une
 * protection, et c'est ce qui m'a d'abord fait conclure à tort que toutes les
 * voies étaient couvertes. Cet essai vérifie donc que le contrôle est dans une
 * méthode ATTEIGNABLE.
 */
class PlafondLocationHorsLigneTest extends TestCase
{
    use DatabaseTransactions;

    private function source(): string
    {
        $code = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        // Commentaires retirés : sinon l'essai trouve les mots qu'il cherche
        // dans l'explication du correctif et passe au vert à tort.
        $code = preg_replace('!/\*.*?\*/!s', '', $code);

        return preg_replace('!^\s*//.*$!m', '', $code);
    }

    private function corpsDe(string $methode): string
    {
        $code = $this->source();
        $debut = strpos($code, 'function ' . $methode . '(');

        $this->assertNotFalse($debut, "Méthode $methode introuvable.");

        $fin = strpos($code, ' function ', $debut + 20);

        return substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);
    }

    /** LA VOIE DES RÈGLEMENTS HORS LIGNE EST DÉSORMAIS GARDÉE. */
    public function test_la_location_hors_ligne_controle_le_plafond(): void
    {
        $corps = $this->corpsDe('enregistrementLocation');

        $this->assertStringContainsString('refusPlafondCredit', $corps,
            "La voie des règlements hors ligne — donc celle des clients à terme "
            . "— crée la location sans vérifier le plafond.");

        $this->assertLessThan(
            strpos($corps, 'Location::creerDepuisDonnees'),
            strpos($corps, 'refusPlafondCredit'),
            "Le contrôle doit précéder la création : refuser après coup "
            . "laisserait la location enregistrée."
        );
    }

    /**
     * LE CONTRÔLE DOIT VIVRE DANS UNE MÉTHODE ATTEIGNABLE.
     *
     * `enregistrementDeLocation` en porte un, mais rien ne l'appelle. Compter
     * sur lui revenait à croire le plafond protégé alors qu'il ne l'était pas.
     */
    public function test_le_controle_ne_dort_pas_dans_du_code_mort(): void
    {
        $code = $this->source();

        $appels = substr_count($code, 'enregistrementDeLocation');

        $this->assertSame(
            1,
            $appels,
            "`enregistrementDeLocation` est appelée quelque part : si cette "
            . "méthode redevient vivante, vérifier que son contrôle de plafond "
            . "est toujours juste. Sinon, elle reste du code mort — et le "
            . "contrôle qu'elle contient ne protège rien."
        );
    }

    /** LE RÈGLEMENT EN LIGNE N'A PAS À ÊTRE BRIDÉ : il n'engage aucun crédit. */
    public function test_le_paiement_en_ligne_reste_libre(): void
    {
        $corps = $this->corpsDe('verifiePaiement');

        $this->assertStringNotContainsString('refusPlafondCredit', $corps,
            "Le rappel de la passerelle ne doit pas refuser une location DÉJÀ "
            . "payée : l'argent est entré avant, aucun crédit n'est engagé, et "
            . "refuser à ce stade laisserait le client réglé sans sa location.");
    }

    /** LES AUTRES VOIES RESTENT GARDÉES : on ne corrige pas l'une en cassant l'autre. */
    public function test_les_voies_deja_gardees_le_restent(): void
    {
        foreach (['validationReference', 'valideDemande'] as $methode) {
            $this->assertStringContainsString(
                'refusPlafondCredit',
                $this->corpsDe($methode),
                "$methode a perdu son contrôle de plafond."
            );
        }
    }
}
