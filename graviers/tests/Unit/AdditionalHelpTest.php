<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests complémentaires pour les méthodes statiques pures de Help.php
 * non déjà couvertes par HelpTest.php.
 *
 * On reste en tests purs (PHPUnit\Framework\TestCase) pour éviter
 * de booter Laravel (qui actuellement échoue à cause d'une route
 * `[cart]` non résolue dans routes/web.php).
 */
class AdditionalHelpTest extends TestCase
{
    // ---------------------------------------------------------------
    // urlPaiement()
    // ---------------------------------------------------------------

    public function test_url_paiement_replaces_http_with_https(): void
    {
        // Sans variable d'env PAIEMENT_BASE_URL, seul le scheme est remplacé
        $result = \Help::urlPaiement('http://example.com/callback');
        $this->assertStringStartsWith('https://', $result);
        $this->assertStringContainsString('example.com/callback', $result);
    }

    public function test_url_paiement_keeps_https(): void
    {
        $result = \Help::urlPaiement('https://example.com/cb');
        $this->assertSame('https://example.com/cb', $result);
    }

    // ---------------------------------------------------------------
    // formatNumeroFacture()
    // ---------------------------------------------------------------

    public function test_format_numero_facture_pads_with_zeros(): void
    {
        // Largeur par défaut = 6
        $this->assertSame('000042', \Help::formatNumeroFacture('42'));
        $this->assertSame('000001', \Help::formatNumeroFacture(1));
    }

    public function test_format_numero_facture_with_custom_width(): void
    {
        $this->assertSame('0007', \Help::formatNumeroFacture('7', 4));
        $this->assertSame('00000007', \Help::formatNumeroFacture(7, 8));
    }

    public function test_format_numero_facture_returns_empty_on_null_or_empty(): void
    {
        $this->assertSame('', \Help::formatNumeroFacture(null));
        $this->assertSame('', \Help::formatNumeroFacture(''));
    }

    public function test_format_numero_facture_with_prefix_unchanged(): void
    {
        // Si non purement numérique, renvoyé tel quel (string)
        $this->assertSame('FAC-12345', \Help::formatNumeroFacture('FAC-12345'));
        $this->assertSame('NCC100000U25', \Help::formatNumeroFacture('NCC100000U25'));
    }

    public function test_format_numero_facture_already_padded(): void
    {
        // Déjà 6 chiffres
        $this->assertSame('123456', \Help::formatNumeroFacture('123456'));
        // Plus long que la largeur : padding ne change rien
        $this->assertSame('1234567', \Help::formatNumeroFacture('1234567'));
    }

    // ---------------------------------------------------------------
    // Constantes complémentaires
    // ---------------------------------------------------------------

    public function test_numero_facture_width_constant(): void
    {
        $this->assertSame(6, \Help::$NUMERO_FACTURE_WIDTH);
    }

    public function test_url_base_fichier_constant(): void
    {
        $this->assertIsString(\Help::$URL_BASE_FICHIER);
        $this->assertNotEmpty(\Help::$URL_BASE_FICHIER);
    }

    // ---------------------------------------------------------------
    // commission - cas limites
    // ---------------------------------------------------------------

    public function test_commission_at_20_million_threshold(): void
    {
        // 20 000 000 inclus dans tranche 5-20 (taux 5%)
        $this->assertEquals(1000000, \Help::commission(20000000));
    }

    public function test_commission_at_4_999_999(): void
    {
        // Juste en-dessous de 5M => tranche 2.5%
        $this->assertEquals(124999.975, \Help::commission(4999999));
    }

    public function test_commission_just_above_20_million(): void
    {
        // 20 000 001 => tranche 7%
        $this->assertEquals(1400000.07, \Help::commission(20000001));
    }

    // ---------------------------------------------------------------
    // montantInitial - cas limites
    // ---------------------------------------------------------------

    public function test_montant_initial_with_50_percent_discount(): void
    {
        // 50% sur prix final 5000 => initial = 5000 / 0.5 = 10000
        $this->assertEquals(10000, \Help::montantInitial(50, 5000));
    }

    public function test_montant_initial_with_25_percent_discount(): void
    {
        // 25% sur 7500 => initial = 7500 / 0.75 = 10000
        $this->assertEquals(10000, \Help::montantInitial(25, 7500));
    }

    // ---------------------------------------------------------------
    // nombreJourEntreDeuxDate - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_nombre_jour_entre_deux_date_one_month(): void
    {
        $this->assertEquals(31, \Help::nombreJourEntreDeuxDate('2025-01-01', '2025-02-01'));
    }

    public function test_nombre_jour_entre_deux_date_negative_when_inverted(): void
    {
        // Date début > date fin => ceil retourne 0 ou négatif
        $result = \Help::nombreJourEntreDeuxDate('2025-01-08', '2025-01-01');
        $this->assertLessThanOrEqual(0, $result);
    }

    // ---------------------------------------------------------------
    // truncateToTwoDecimals - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_truncate_to_two_decimals_zero(): void
    {
        $this->assertSame(0.0, \Help::truncateToTwoDecimals(0));
    }

    public function test_truncate_to_two_decimals_exactly_two_decimals(): void
    {
        $this->assertSame(12.34, \Help::truncateToTwoDecimals(12.34));
    }

    // ---------------------------------------------------------------
    // ChaineAleatoire / ChaineAleatoireNombre - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_chaine_aleatoire_different_calls_can_differ(): void
    {
        // Pas forcément toujours différentes, mais devrait l'être sur 50 chars
        $a = \Help::ChaineAleatoire(50);
        $b = \Help::ChaineAleatoire(50);
        $c = \Help::ChaineAleatoire(50);
        // Au moins une différence sur trois tirages 50 chars
        $allSame = ($a === $b) && ($b === $c);
        $this->assertFalse($allSame, 'Les chaînes aléatoires ne devraient pas toutes être identiques');
    }

    public function test_chaine_aleatoire_nombre_size_100(): void
    {
        $result = \Help::ChaineAleatoireNombre(100);
        $this->assertSame(100, strlen($result));
        $this->assertMatchesRegularExpression('/^\d+$/', $result);
    }

    // ---------------------------------------------------------------
    // getCodeParain - différents numéros
    // ---------------------------------------------------------------

    public function test_get_code_parain_different_phone(): void
    {
        $result = \Help::getCodeParain('0707070707');
        $this->assertStringStartsWith('PAR-0707070707', $result);
        $this->assertSame(17, strlen($result));
    }

    // ---------------------------------------------------------------
    // distance - différents cas
    // ---------------------------------------------------------------

    public function test_distance_short_distance(): void
    {
        // Deux points très proches => distance faible
        $result = \Help::distance(2.3522, 48.8566, 2.3523, 48.8567);
        $this->assertLessThan(1, $result);
    }

    public function test_distance_antipodes(): void
    {
        // Points opposés => distance significative (~20000 km)
        $result = \Help::distance(0, 0, 180, 0);
        $this->assertGreaterThan(15000, $result);
    }

    // ---------------------------------------------------------------
    // sommePropriete - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_somme_propriete_single_element(): void
    {
        $items = [(object) ['x' => 42]];
        $this->assertEquals(42, \Help::sommePropriete($items, 'x'));
    }

    public function test_somme_propriete_negative_values(): void
    {
        $items = [
            (object) ['v' => 100],
            (object) ['v' => -30],
            (object) ['v' => -20],
        ];
        $this->assertEquals(50, \Help::sommePropriete($items, 'v'));
    }

    // ---------------------------------------------------------------
    // unique_multidim_array - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_unique_multidim_array_no_duplicates(): void
    {
        $input = [
            ['id' => 1, 'v' => 'A'],
            ['id' => 2, 'v' => 'B'],
            ['id' => 3, 'v' => 'C'],
        ];
        $result = \Help::unique_multidim_array($input, 'id');
        $this->assertCount(3, $result);
    }

    public function test_unique_multidim_array_all_duplicates(): void
    {
        $input = [
            ['id' => 1, 'v' => 'A'],
            ['id' => 1, 'v' => 'B'],
            ['id' => 1, 'v' => 'C'],
        ];
        $result = \Help::unique_multidim_array($input, 'id');
        $this->assertCount(1, $result);
    }

    // ---------------------------------------------------------------
    // rechercheParCle - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_recherche_par_cle_first_element(): void
    {
        $items = [
            (object) ['id' => 1, 'nom' => 'Premier'],
            (object) ['id' => 2, 'nom' => 'Second'],
        ];
        $result = \Help::rechercheParCle($items, 'id', 1);
        $this->assertNotNull($result);
        $this->assertSame('Premier', $result->nom);
    }

    public function test_recherche_par_cle_empty_array(): void
    {
        $result = \Help::rechercheParCle([], 'id', 1);
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // sansAccent - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_sans_accent_with_uppercase(): void
    {
        $result = \Help::sansAccent('HELLO');
        $this->assertSame('HELLO', $result);
    }

    public function test_sans_accent_digits(): void
    {
        $result = \Help::sansAccent('12345');
        $this->assertSame('12345', $result);
    }

    // ---------------------------------------------------------------
    // array_sort - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_array_sort_single_element(): void
    {
        $input = [['p' => 10]];
        $result = \Help::array_sort($input, 'p', SORT_ASC);
        $this->assertCount(1, $result);
    }

    public function test_array_sort_already_sorted(): void
    {
        $input = [
            ['p' => 1],
            ['p' => 2],
            ['p' => 3],
        ];
        $result = \Help::array_sort($input, 'p', SORT_ASC);
        $values = array_values($result);
        $this->assertSame(1, $values[0]['p']);
        $this->assertSame(3, $values[2]['p']);
    }

    // ---------------------------------------------------------------
    // NombreCommancantParzero - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_nombre_commancant_par_zero_negative(): void
    {
        $result = \Help::NombreCommancantParzero(-5, 4);
        // Le signe - reste; padding sur la chaîne entière
        $this->assertIsString($result);
    }

    public function test_nombre_commancant_par_zero_zero(): void
    {
        $result = \Help::NombreCommancantParzero(0);
        $this->assertSame('000000', $result);
    }

    // ---------------------------------------------------------------
    // listeStatutLocation
    // ---------------------------------------------------------------

    public function test_liste_statut_location_contains_en_attente(): void
    {
        $result = \Help::listeStatutLocation();
        $this->assertContains('EN ATTENTE', $result);
    }

    // ---------------------------------------------------------------
    // formatNombre - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_format_nombre_large_value(): void
    {
        $result = \Help::formatNombre(1234567890, false);
        // Avec . comme séparateur de milliers
        $this->assertStringContainsString('.', $result);
    }

    public function test_format_nombre_negative(): void
    {
        $result = \Help::formatNombre(-1000, true);
        $this->assertStringContainsString('-', $result);
        $this->assertStringContainsString('fcfa', $result);
    }

    // ---------------------------------------------------------------
    // montantStringVersEnt - cas supplémentaires
    // ---------------------------------------------------------------

    public function test_montant_string_vers_ent_with_unicode_spaces(): void
    {
        // Avec espace insécable
        $result = \Help::montantStringVersEnt("1\xc2\xa0000\xc2\xa0fcfa");
        $this->assertSame(1000, $result);
    }

    public function test_montant_string_vers_ent_only_spaces(): void
    {
        $this->assertSame(0, \Help::montantStringVersEnt('   '));
    }

    public function test_montant_string_vers_ent_mixed(): void
    {
        $this->assertSame(9876, \Help::montantStringVersEnt('AB9C8D7E6F fcfa'));
    }
}
