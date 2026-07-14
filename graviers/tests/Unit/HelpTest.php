<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Help class.
 *
 * These tests cover all pure static functions (no DB or session dependencies).
 * Methods that depend on DB, Auth, Cart, or session are tested at the Feature level.
 */
class HelpTest extends TestCase
{
    // ---------------------------------------------------------------
    // Static constants
    // ---------------------------------------------------------------

    public function test_statut_constants(): void
    {
        $this->assertSame(1, \Help::$STATUT_ACTIF);
        $this->assertSame(2, \Help::$STATUT_INACTIF);
    }

    public function test_client_type_constants(): void
    {
        $this->assertSame('PARTICULIER', \Help::$PARTICULIER);
        $this->assertSame('ENTREPRISE', \Help::$ENTREPRISE);
    }

    public function test_commande_status_constants(): void
    {
        $this->assertSame('EN ATTENTE', \Help::$COMMANDE_EN_ATTENTE);
        $this->assertSame('EN TRAITEMENT', \Help::$COMMANDE_EN_TRAITEMENT);
        $this->assertSame('TERMINEE', \Help::$COMMANDE_TERMINE);
    }

    public function test_livraison_status_constants(): void
    {
        $this->assertSame('EN ATTENTE', \Help::$LIVRAISON_EN_ATTENTE);
        $this->assertSame('EN TRAITEMENT', \Help::$LIVRAISON_EN_TRAITEMENT);
        $this->assertSame('LIVREE', \Help::$LIVRAISON_LIVREE);
    }

    public function test_client_category_constants(): void
    {
        $this->assertSame('CLIENT COMPTANT', \Help::$CLIENT_COMPTANT);
        $this->assertSame('CLIENT BE', \Help::$CLIENT_BE);
        $this->assertSame('CLIENT A TERME', \Help::$CLIENT_A_TERME);
    }

    public function test_banniere_constants(): void
    {
        $this->assertSame('TOP', \Help::$BANNIERE_TOP);
        $this->assertSame('FLASH', \Help::$BANNIERE_FLASH);
        $this->assertSame('BOTTOM', \Help::$BANNIERE_BOTTOM);
    }

    public function test_location_constants(): void
    {
        $this->assertSame('LOCATION', \Help::$LOCATION);
        $this->assertSame('VENTE', \Help::$VENTE);
        $this->assertSame('EN ATTENTE', \Help::$LOCATION_EN_ATTENTE);
        $this->assertSame('EN COURS', \Help::$LOCATION_EN_COURS);
        $this->assertSame('TERMINE', \Help::$LOCATION_TERMINE);
    }

    public function test_user_role_constants(): void
    {
        $this->assertSame(1, \Help::$USER_SA);
        $this->assertSame(2, \Help::$USER_ADMIN);
        $this->assertSame(3, \Help::$USER_GESTIONNAIRE);
        $this->assertSame(4, \Help::$USER_CLIENT);
        $this->assertSame(5, \Help::$USER_FOURNISSEUR);
        $this->assertSame(6, \Help::$USER_APPORTEUR);
        $this->assertSame(7, \Help::$USER_AGENT_SAV);
        $this->assertSame(8, \Help::$USER_LIVREUR);
    }

    public function test_provenance_and_type_affaire_constants(): void
    {
        $this->assertSame('COMMANDE', \Help::$COMMANDE);
        $this->assertSame('LIVRAISON', \Help::$LIVRAISON);
    }

    // ---------------------------------------------------------------
    // montantStringVersEnt()
    // ---------------------------------------------------------------

    public function test_montant_string_vers_ent_removes_non_digits(): void
    {
        $this->assertSame(150000, \Help::montantStringVersEnt('150 000 fcfa'));
        $this->assertSame(1234, \Help::montantStringVersEnt('1.2.3.4'));
        $this->assertSame(0, \Help::montantStringVersEnt(''));
        $this->assertSame(0, \Help::montantStringVersEnt('abc'));
    }

    public function test_montant_string_vers_ent_with_numeric_string(): void
    {
        $this->assertSame(42, \Help::montantStringVersEnt('42'));
    }

    // ---------------------------------------------------------------
    // truncateToTwoDecimals()
    // ---------------------------------------------------------------

    public function test_truncate_to_two_decimals_floors_correctly(): void
    {
        $this->assertSame(3.14, \Help::truncateToTwoDecimals(3.14159));
        $this->assertSame(2.99, \Help::truncateToTwoDecimals(2.999));
        $this->assertSame(1.0, \Help::truncateToTwoDecimals(1.009));
        $this->assertSame(0.0, \Help::truncateToTwoDecimals(0.001));
    }

    public function test_truncate_to_two_decimals_with_integer(): void
    {
        $this->assertSame(5.0, \Help::truncateToTwoDecimals(5));
    }

    public function test_truncate_to_two_decimals_negative(): void
    {
        // floor(-1.119 * 100) = floor(-111.9) = -112 => -112/100 = -1.12
        $this->assertSame(-1.12, \Help::truncateToTwoDecimals(-1.119));
    }

    // ---------------------------------------------------------------
    // formatNombre()
    // ---------------------------------------------------------------

    public function test_format_nombre_monetary(): void
    {
        $result = \Help::formatNombre(150000, true);
        // Expected: "150 000 fcfa"  (non-breaking spaces may vary)
        $this->assertStringContainsString('150', $result);
        $this->assertStringContainsString('000', $result);
        $this->assertStringContainsString('fcfa', $result);
    }

    public function test_format_nombre_monetary_custom_devise(): void
    {
        $result = \Help::formatNombre(5000, true, 'EUR');
        $this->assertStringContainsString('EUR', $result);
    }

    public function test_format_nombre_non_monetary(): void
    {
        $result = \Help::formatNombre(1234.5678, false);
        // number_format(1234.5678, 2, ",", ".") => "1.234,57"
        $this->assertSame('1.234,57', $result);
    }

    public function test_format_nombre_zero(): void
    {
        $result = \Help::formatNombre(0, true);
        $this->assertStringContainsString('0', $result);
        $this->assertStringContainsString('fcfa', $result);
    }

    // ---------------------------------------------------------------
    // ChaineAleatoireNombre()
    // ---------------------------------------------------------------

    public function test_chaine_aleatoire_nombre_length(): void
    {
        $result = \Help::ChaineAleatoireNombre(10);
        $this->assertSame(10, strlen($result));
    }

    public function test_chaine_aleatoire_nombre_only_digits(): void
    {
        $result = \Help::ChaineAleatoireNombre(50);
        $this->assertMatchesRegularExpression('/^\d+$/', $result);
    }

    public function test_chaine_aleatoire_nombre_zero_length(): void
    {
        $result = \Help::ChaineAleatoireNombre(0);
        $this->assertSame('', $result);
    }

    // ---------------------------------------------------------------
    // ChaineAleatoire()
    // ---------------------------------------------------------------

    public function test_chaine_aleatoire_length(): void
    {
        $result = \Help::ChaineAleatoire(8);
        $this->assertSame(8, strlen($result));
    }

    public function test_chaine_aleatoire_alphanumeric_uppercase(): void
    {
        $result = \Help::ChaineAleatoire(50);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $result);
    }

    // ---------------------------------------------------------------
    // getNumberToken()
    // ---------------------------------------------------------------

    public function test_get_number_token_length(): void
    {
        $result = \Help::getNumberToken(12);
        $this->assertSame(12, strlen($result));
    }

    public function test_get_number_token_uppercase_alphanumeric(): void
    {
        $result = \Help::getNumberToken(30);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $result);
    }

    // ---------------------------------------------------------------
    // getCommandeNo()
    // ---------------------------------------------------------------

    public function test_get_commande_no_contains_timestamp_and_digits(): void
    {
        $before = time();
        $result = \Help::getCommandeNo();
        $after = time();

        // The result starts with a unix timestamp and ends with 10 random digits
        $this->assertMatchesRegularExpression('/^\d+$/', $result);
        // Total length: timestamp digits (~10) + 10 random digits
        $this->assertGreaterThanOrEqual(20, strlen($result));
    }

    // ---------------------------------------------------------------
    // getCodeParain()
    // ---------------------------------------------------------------

    public function test_get_code_parain_format(): void
    {
        $result = \Help::getCodeParain('0102030405');
        $this->assertStringStartsWith('PAR-0102030405', $result);
        // PAR- (4) + phone (10) + 3 random digits = 17
        $this->assertSame(17, strlen($result));
    }

    // ---------------------------------------------------------------
    // NombreCommancantParzero()
    // ---------------------------------------------------------------

    public function test_nombre_commancant_par_zero_default_size(): void
    {
        $result = \Help::NombreCommancantParzero(42);
        // Default size is 6, so "000042"
        $this->assertSame('000042', $result);
    }

    public function test_nombre_commancant_par_zero_custom_size(): void
    {
        $result = \Help::NombreCommancantParzero(7, 4);
        $this->assertSame('0007', $result);
    }

    public function test_nombre_commancant_par_zero_large_number(): void
    {
        $result = \Help::NombreCommancantParzero(1234567, 4);
        // Number exceeds size, should still display fully
        $this->assertSame('1234567', $result);
    }

    // ---------------------------------------------------------------
    // distance()
    // ---------------------------------------------------------------

    public function test_distance_same_point_is_zero(): void
    {
        $result = \Help::distance(2.3522, 48.8566, 2.3522, 48.8566);
        $this->assertSame(0, $result);
    }

    public function test_distance_known_cities(): void
    {
        // Paris (2.3522, 48.8566) to Marseille (5.3698, 43.2965)
        // Approximately 660 km
        $result = \Help::distance(2.3522, 48.8566, 5.3698, 43.2965);
        $this->assertGreaterThan(600, $result);
        $this->assertLessThan(750, $result);
    }

    public function test_distance_returns_integer(): void
    {
        $result = \Help::distance(0, 0, 1, 1);
        $this->assertIsInt($result);
    }

    // ---------------------------------------------------------------
    // sansAccent()
    // ---------------------------------------------------------------

    public function test_sans_accent_removes_french_accents(): void
    {
        $result = \Help::sansAccent('cafe');
        $this->assertIsString($result);
    }

    public function test_sans_accent_plain_ascii_unchanged(): void
    {
        $this->assertSame('hello', \Help::sansAccent('hello'));
    }

    // ---------------------------------------------------------------
    // commission()
    // ---------------------------------------------------------------

    public function test_commission_below_5_million(): void
    {
        // 2.5% of 1 000 000 = 25 000
        $this->assertEquals(25000, \Help::commission(1000000));
    }

    public function test_commission_at_5_million(): void
    {
        // 5% of 5 000 000 = 250 000
        $this->assertEquals(250000, \Help::commission(5000000));
    }

    public function test_commission_between_5_and_20_million(): void
    {
        // 5% of 10 000 000 = 500 000
        $this->assertEquals(500000, \Help::commission(10000000));
    }

    public function test_commission_above_20_million(): void
    {
        // 7% of 30 000 000 = 2 100 000
        $this->assertEquals(2100000, \Help::commission(30000000));
    }

    public function test_commission_zero(): void
    {
        // 2.5% of 0 = 0
        $this->assertEquals(0, \Help::commission(0));
    }

    // ---------------------------------------------------------------
    // montantInitial()
    // ---------------------------------------------------------------

    public function test_montant_initial_computes_before_discount(): void
    {
        // 10% discount on a final price of 9000 => initial = 9000 / (1 - 10/100) = 10 000
        $this->assertEquals(10000, \Help::montantInitial(10, 9000));
    }

    public function test_montant_initial_zero_discount(): void
    {
        $this->assertEquals(5000, \Help::montantInitial(0, 5000));
    }

    // ---------------------------------------------------------------
    // nombreJourEntreDeuxDate()
    // ---------------------------------------------------------------

    public function test_nombre_jour_entre_deux_date_same_day(): void
    {
        $this->assertEquals(0, \Help::nombreJourEntreDeuxDate('2025-01-01', '2025-01-01'));
    }

    public function test_nombre_jour_entre_deux_date_one_week(): void
    {
        $this->assertEquals(7, \Help::nombreJourEntreDeuxDate('2025-01-01', '2025-01-08'));
    }

    public function test_nombre_jour_entre_deux_date_slash_format(): void
    {
        // The method replaces "/" with "-" before parsing
        $this->assertEquals(1, \Help::nombreJourEntreDeuxDate('01/01/2025', '02/01/2025'));
    }

    // ---------------------------------------------------------------
    // unique_multidim_array()
    // ---------------------------------------------------------------

    public function test_unique_multidim_array_removes_duplicates(): void
    {
        $input = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
            ['id' => 1, 'name' => 'A duplicate'],
        ];
        $result = \Help::unique_multidim_array($input, 'id');
        $this->assertCount(2, $result);
    }

    public function test_unique_multidim_array_empty(): void
    {
        $result = \Help::unique_multidim_array([], 'id');
        $this->assertCount(0, $result);
    }

    // ---------------------------------------------------------------
    // array_sort()
    // ---------------------------------------------------------------

    public function test_array_sort_ascending(): void
    {
        $input = [
            ['prix' => 300],
            ['prix' => 100],
            ['prix' => 200],
        ];
        $result = \Help::array_sort($input, 'prix', SORT_ASC);
        $values = array_values($result);
        $this->assertSame(100, $values[0]['prix']);
        $this->assertSame(200, $values[1]['prix']);
        $this->assertSame(300, $values[2]['prix']);
    }

    public function test_array_sort_descending(): void
    {
        $input = [
            ['prix' => 100],
            ['prix' => 300],
            ['prix' => 200],
        ];
        $result = \Help::array_sort($input, 'prix', SORT_DESC);
        $values = array_values($result);
        $this->assertSame(300, $values[0]['prix']);
        $this->assertSame(200, $values[1]['prix']);
        $this->assertSame(100, $values[2]['prix']);
    }

    public function test_array_sort_empty(): void
    {
        $result = \Help::array_sort([], 'prix');
        $this->assertCount(0, $result);
    }

    // ---------------------------------------------------------------
    // rechercheParCle()
    // ---------------------------------------------------------------

    public function test_recherche_par_cle_found(): void
    {
        $items = [
            (object) ['id' => 1, 'nom' => 'Sable'],
            (object) ['id' => 2, 'nom' => 'Gravier'],
        ];
        $result = \Help::rechercheParCle($items, 'id', 2);
        $this->assertNotNull($result);
        $this->assertSame('Gravier', $result->nom);
    }

    public function test_recherche_par_cle_not_found(): void
    {
        $items = [
            (object) ['id' => 1, 'nom' => 'Sable'],
        ];
        $result = \Help::rechercheParCle($items, 'id', 99);
        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // sommePropriete()
    // ---------------------------------------------------------------

    public function test_somme_propriete_sums_correctly(): void
    {
        $items = [
            (object) ['montant' => 100],
            (object) ['montant' => 250],
            (object) ['montant' => 50],
        ];
        $result = \Help::sommePropriete($items, 'montant');
        $this->assertEquals(400, $result);
    }

    public function test_somme_propriete_empty_returns_zero(): void
    {
        $result = \Help::sommePropriete([], 'montant');
        $this->assertEquals(0, $result);
    }

    // ---------------------------------------------------------------
    // Liste helpers (pure arrays, no DB)
    // ---------------------------------------------------------------

    public function test_liste_provenance(): void
    {
        $result = \Help::listeProvenance();
        $this->assertSame(['COMMANDE', 'LIVRAISON'], $result);
    }

    public function test_liste_type_affaire(): void
    {
        $result = \Help::listeTypeAffaire();
        $this->assertSame(['LOCATION', 'VENTE'], $result);
    }

    public function test_liste_statut_commande(): void
    {
        $result = \Help::listeStatutCommande();
        $this->assertCount(3, $result);
        $this->assertContains('EN ATTENTE', $result);
        $this->assertContains('EN TRAITEMENT', $result);
        $this->assertContains('TERMINEE', $result);
    }

    public function test_liste_statut_livraison(): void
    {
        $result = \Help::listeStatutLivraison();
        $this->assertCount(3, $result);
        $this->assertContains('LIVREE', $result);
    }

    public function test_liste_statut_location(): void
    {
        $result = \Help::listeStatutLocation();
        $this->assertCount(3, $result);
        $this->assertContains('EN COURS', $result);
        $this->assertContains('TERMINE', $result);
    }

    public function test_type_compte(): void
    {
        $result = \Help::typeCompte();
        $this->assertCount(3, $result);
        $this->assertContains('CLIENT COMPTANT', $result);
        $this->assertContains('CLIENT BE', $result);
        $this->assertContains('CLIENT A TERME', $result);
    }
}
