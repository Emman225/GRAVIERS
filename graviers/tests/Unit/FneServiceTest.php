<?php

namespace Tests\Unit;

use App\Services\FneService;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests unitaires pour FneService - méthodes statiques pures.
 *
 * Les méthodes signInvoice, refundInvoice, genererQrCodeBase64, etc.
 * dépendent de modèles Eloquent et de la pile HTTP de Laravel ; elles
 * ne sont pas testées ici (testées en intégration uniquement).
 */
class FneServiceTest extends TestCase
{
    // ---------------------------------------------------------------
    // mapPaymentMethod()
    // ---------------------------------------------------------------

    public function test_map_payment_method_especes(): void
    {
        $this->assertSame('cash', FneService::mapPaymentMethod('Espèces'));
        $this->assertSame('cash', FneService::mapPaymentMethod('especes'));
        $this->assertSame('cash', FneService::mapPaymentMethod('cash'));
        $this->assertSame('cash', FneService::mapPaymentMethod('Paiement en cash'));
    }

    public function test_map_payment_method_carte(): void
    {
        $this->assertSame('card', FneService::mapPaymentMethod('Carte bancaire'));
        $this->assertSame('card', FneService::mapPaymentMethod('carte'));
        $this->assertSame('card', FneService::mapPaymentMethod('CB'));
        $this->assertSame('card', FneService::mapPaymentMethod('credit card'));
    }

    public function test_map_payment_method_cheque(): void
    {
        $this->assertSame('check', FneService::mapPaymentMethod('Chèque'));
        $this->assertSame('check', FneService::mapPaymentMethod('cheque bancaire'));
        $this->assertSame('check', FneService::mapPaymentMethod('check'));
    }

    public function test_map_payment_method_mobile_money(): void
    {
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('Orange Money'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('Wave'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('MTN Mobile Money'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('Momo'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('mobile money'));
    }

    public function test_map_payment_method_virement(): void
    {
        $this->assertSame('transfer', FneService::mapPaymentMethod('Virement bancaire'));
        $this->assertSame('transfer', FneService::mapPaymentMethod('transfer'));
        $this->assertSame('transfer', FneService::mapPaymentMethod('virement'));
    }

    public function test_map_payment_method_deferred(): void
    {
        $this->assertSame('deferred', FneService::mapPaymentMethod('À terme'));
        $this->assertSame('deferred', FneService::mapPaymentMethod('credit'));
        $this->assertSame('deferred', FneService::mapPaymentMethod('crédit'));
        $this->assertSame('deferred', FneService::mapPaymentMethod('deferred'));
    }

    public function test_map_payment_method_case_insensitive(): void
    {
        $this->assertSame('cash', FneService::mapPaymentMethod('ESPECES'));
        $this->assertSame('card', FneService::mapPaymentMethod('CARTE'));
        $this->assertSame('mobile-money', FneService::mapPaymentMethod('WAVE'));
    }

    public function test_map_payment_method_with_whitespace(): void
    {
        $this->assertSame('cash', FneService::mapPaymentMethod('  cash  '));
        $this->assertSame('card', FneService::mapPaymentMethod("  carte\n"));
    }

    // ---------------------------------------------------------------
    // determineTemplate()
    // ---------------------------------------------------------------

    public function test_determine_template_with_ncc_returns_b2b(): void
    {
        $client = new stdClass();
        $client->ncc_clt = '1234567';
        $client->type_client = 'particulier'; // Doit être ignoré au profit du NCC

        $this->assertSame('B2B', FneService::determineTemplate($client));
    }

    public function test_determine_template_particulier_returns_b2c(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'particulier';

        $this->assertSame('B2C', FneService::determineTemplate($client));
    }

    public function test_determine_template_individuel_returns_b2c(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'individuel';

        $this->assertSame('B2C', FneService::determineTemplate($client));
    }

    public function test_determine_template_b2c_alias_returns_b2c(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'b2c';

        $this->assertSame('B2C', FneService::determineTemplate($client));
    }

    public function test_determine_template_etat_returns_b2g(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'etat';

        $this->assertSame('B2G', FneService::determineTemplate($client));
    }

    public function test_determine_template_gouvernement_returns_b2g(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'gouvernement';

        $this->assertSame('B2G', FneService::determineTemplate($client));
    }

    public function test_determine_template_institution_returns_b2g(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'institution';

        $this->assertSame('B2G', FneService::determineTemplate($client));
    }

    public function test_determine_template_international_returns_b2f(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'international';

        $this->assertSame('B2F', FneService::determineTemplate($client));
    }

    public function test_determine_template_etranger_returns_b2f(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'etranger';

        $this->assertSame('B2F', FneService::determineTemplate($client));
    }

    public function test_determine_template_b2f_alias_returns_b2f(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'b2f';

        $this->assertSame('B2F', FneService::determineTemplate($client));
    }

    public function test_determine_template_ncc_priority_over_type(): void
    {
        // Même avec type_client = 'etat', si NCC présent => B2B
        $client = new stdClass();
        $client->ncc_clt = '1234567';
        $client->type_client = 'etat';

        $this->assertSame('B2B', FneService::determineTemplate($client));
    }

    public function test_determine_template_empty_ncc_string_falls_back(): void
    {
        $client = new stdClass();
        $client->ncc_clt = ''; // Vide ne compte pas comme NCC
        $client->type_client = 'particulier';

        $this->assertSame('B2C', FneService::determineTemplate($client));
    }

    public function test_determine_template_uppercase_type(): void
    {
        $client = new stdClass();
        $client->ncc_clt = null;
        $client->type_client = 'PARTICULIER';

        // strtolower est appliqué => B2C
        $this->assertSame('B2C', FneService::determineTemplate($client));
    }
}
