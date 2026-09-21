<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\FneService;
use App\Support\RegimeImposition;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE RÉGIME D'IMPOSITION : UN CODE EN BASE, UN INTITULÉ À L'ÉCRAN.
 *
 * Demandé le 07/09/2026 : RE → « Taxe d'État de l'Entreprenant (TEE) »,
 * RNI → « Réel normal d'imposition », RSI → « Réel simplifié d'imposition ».
 * Les fiches existantes stockaient l'ancien libellé complet : elles doivent
 * s'afficher avec le nouveau, sans rien perdre.
 */
class RegimeImpositionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_un_ancien_libelle_est_reconnu_et_traduit(): void
    {
        $this->assertSame('RNI', RegimeImposition::code('RNI — Régime Normal d’Imposition'));
        $this->assertSame('RSI', RegimeImposition::code('RSI — Régime Simplifié d’Imposition'));
        $this->assertSame('RE',  RegimeImposition::code('RE — Régime de l’Entreprenant'));
        $this->assertSame('RME', RegimeImposition::code('RME — Régime des Micro-Entreprises'));
        $this->assertSame('RE',  RegimeImposition::code('re'));
        $this->assertNull(RegimeImposition::code(''));
        $this->assertNull(RegimeImposition::code('REEL'));

        $this->assertSame("Taxe d'État de l'Entreprenant (TEE)", RegimeImposition::libelle('RE — Régime de l’Entreprenant'));
        $this->assertSame("Réel normal d'imposition", RegimeImposition::libelle('RNI'));
        $this->assertSame("Réel simplifié d'imposition", RegimeImposition::libelle('RSI'));
        // Une valeur inconnue ressort telle quelle : on n'invente rien.
        $this->assertSame('Autre chose', RegimeImposition::libelle('Autre chose'));
    }

    public function test_le_formulaire_d_inscription_propose_les_nouveaux_intitules(): void
    {
        $reponse = $this->get(route('client.register'));
        $reponse->assertOk();
        $reponse->assertSee('value="RNI"', false);
        $reponse->assertSee("Réel normal d'imposition");
        $reponse->assertSee("Réel simplifié d'imposition");
        $reponse->assertSee("Taxe d'État de l'Entreprenant (TEE)");
        $reponse->assertDontSee('RNI — Régime Normal');
    }

    public function test_la_facture_imprime_le_nouvel_intitule_pour_une_fiche_ancienne(): void
    {
        $client = Client::where('type_client', 'ENTREPRISE')->first() ?? Client::first();
        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        // Fiche telle qu'enregistrée AVANT le 07/09/2026.
        $client->regime_imposition = 'RSI — Régime Simplifié d’Imposition';
        $client->save();

        $donnees = FneService::getDonneesFne(null, $client);

        $this->assertSame("Réel simplifié d'imposition", $donnees['fne_client']['regime_imposition']);
    }
}
