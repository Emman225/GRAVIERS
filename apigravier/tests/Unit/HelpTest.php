<?php

namespace Tests\Unit;

use Help;
use PHPUnit\Framework\TestCase;

class HelpTest extends TestCase
{
    // -------------------------------------------------------
    // HashPassword / HashVerifier → moved to Feature/HelpHashTest.php
    // (requires Laravel facade boot)
    // -------------------------------------------------------

    // -------------------------------------------------------
    // ChaineAleatoireNombre
    // -------------------------------------------------------

    public function test_chaine_aleatoire_nombre_returns_correct_length(): void
    {
        $result = Help::ChaineAleatoireNombre(4);
        $this->assertEquals(4, strlen($result));
    }

    public function test_chaine_aleatoire_nombre_contains_only_digits(): void
    {
        $result = Help::ChaineAleatoireNombre(4);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $result);
    }

    // -------------------------------------------------------
    // ChaineAleatoire
    // -------------------------------------------------------

    public function test_chaine_aleatoire_returns_correct_length(): void
    {
        $result = Help::ChaineAleatoire(6);
        $this->assertEquals(6, strlen($result));
    }

    public function test_chaine_aleatoire_contains_only_alphanumeric_uppercase(): void
    {
        $result = Help::ChaineAleatoire(6);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $result);
    }

    // -------------------------------------------------------
    // NombreCommancantParzero
    // -------------------------------------------------------

    public function test_nombre_commancant_par_zero_pads_correctly(): void
    {
        $result = Help::NombreCommancantParzero(42, 6);
        $this->assertEquals('000042', $result);
    }

    public function test_nombre_commancant_par_zero_default_size(): void
    {
        $result = Help::NombreCommancantParzero(1);
        $this->assertEquals('000001', $result);
    }

    // -------------------------------------------------------
    // formatNombre
    // -------------------------------------------------------

    public function test_format_nombre_monetaire(): void
    {
        $result = Help::formatNombre(1500.50, true, 'F');
        $this->assertStringContainsString('F', $result);
        $this->assertStringContainsString('1', $result);
    }

    public function test_format_nombre_non_monetaire(): void
    {
        $result = Help::formatNombre(1500.50, false);
        $this->assertIsString($result);
        // number_format(1500.50, 2, ",", ".") => "1.500,50"
        $this->assertEquals('1.500,50', $result);
    }

    // -------------------------------------------------------
    // urlFichier
    // -------------------------------------------------------

    public function test_url_fichier_empty_returns_null(): void
    {
        $this->assertNull(Help::urlFichier(''));
    }

    public function test_url_fichier_http_returns_same_url(): void
    {
        $url = 'http://example.com/img.png';
        $this->assertEquals($url, Help::urlFichier($url));
    }

    public function test_url_fichier_https_returns_same_url(): void
    {
        $url = 'https://example.com/img.png';
        $this->assertEquals($url, Help::urlFichier($url));
    }

    public function test_url_fichier_relative_path_prepends_base_url(): void
    {
        $result = Help::urlFichier('images/test.png');
        $this->assertEquals(Help::$URL_BASE_FICHIER . 'images/test.png', $result);
    }

    // -------------------------------------------------------
    // distance
    // -------------------------------------------------------

    public function test_distance_same_point_returns_zero(): void
    {
        $this->assertEquals(0, Help::distance(0, 0, 0, 0));
    }

    public function test_distance_abidjan_area_returns_positive(): void
    {
        $result = Help::distance(-5.3364, 3.9628, -5.3600, 4.0083);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // -------------------------------------------------------
    // nombreJourEntreDeuxDate
    // -------------------------------------------------------

    public function test_nombre_jour_entre_deux_dates(): void
    {
        $result = Help::nombreJourEntreDeuxDate('01/01/2025', '10/01/2025');
        $this->assertEquals(9, $result);
    }

    public function test_nombre_jour_entre_deux_dates_same_day(): void
    {
        $result = Help::nombreJourEntreDeuxDate('01/01/2025', '01/01/2025');
        $this->assertEquals(0, $result);
    }

    // -------------------------------------------------------
    // sommePropriete
    // -------------------------------------------------------

    public function test_somme_propriete_with_objects(): void
    {
        $items = [
            (object) ['montant' => 100],
            (object) ['montant' => 250],
            (object) ['montant' => 50],
        ];
        $result = Help::sommePropriete($items, 'montant');
        $this->assertEquals(400, $result);
    }

    public function test_somme_propriete_empty_array_returns_zero(): void
    {
        $result = Help::sommePropriete([], 'montant');
        $this->assertEquals(0, $result);
    }

    // -------------------------------------------------------
    // Static constants
    // -------------------------------------------------------

    public function test_user_type_constants(): void
    {
        $this->assertEquals(4, Help::$USER_CLIENT);
        $this->assertEquals(6, Help::$USER_APPORTEUR);
        $this->assertEquals(8, Help::$USER_LIVREUR);
        $this->assertEquals(1, Help::$USER_SA);
        $this->assertEquals(2, Help::$USER_ADMIN);
        $this->assertEquals(3, Help::$USER_GESTIONNAIRE);
        $this->assertEquals(5, Help::$USER_FOURNISSEUR);
        $this->assertEquals(7, Help::$USER_AGENT_SAV);
    }

    public function test_statut_constants(): void
    {
        $this->assertEquals(1, Help::$STATUT_ACTIF);
        $this->assertEquals(2, Help::$STATUT_INACTIF);
        $this->assertEquals(3, Help::$STATUT_DEMANDE_SUP);
        $this->assertEquals(4, Help::$STATUT_SUPPRIMER);
    }

    // -------------------------------------------------------
    // listeStatutCommande
    // -------------------------------------------------------

    public function test_liste_statut_commande_returns_three_statuses(): void
    {
        $result = Help::listeStatutCommande();
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('EN ATTENTE', $result);
        $this->assertContains('EN TRAITEMENT', $result);
        $this->assertContains('TERMINEE', $result);
    }

    // -------------------------------------------------------
    // listeStatutLivraison
    // -------------------------------------------------------

    public function test_liste_statut_livraison_returns_three_statuses(): void
    {
        $result = Help::listeStatutLivraison();
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('EN ATTENTE', $result);
        $this->assertContains('EN TRAITEMENT', $result);
        $this->assertContains('LIVREE', $result);
    }
}
