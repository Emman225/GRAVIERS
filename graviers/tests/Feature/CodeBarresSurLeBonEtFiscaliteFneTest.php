<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Services\DocumentDgi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Lot 111 (19/09/2026) : code-barres sur le bon de livraison ; NCC et régime complétés sur la facture FNE. */
class CodeBarresSurLeBonEtFiscaliteFneTest extends TestCase
{
    use DatabaseTransactions;

    public function test_les_deux_gabarits_de_bon_portent_le_code_barres(): void
    {
        foreach (['livreur/bonImprime', 'fournisseur/bonImprime'] as $vue) {
            $source = file_get_contents(resource_path("views/$vue.blade.php"));
            $this->assertStringContainsString('CodeBarres::bloc(', $source, $vue);
        }
        $bloc = \App\Services\CodeBarres::bloc('ENL777003');
        $this->assertStringContainsString('class="code-barres"', $bloc);
        $this->assertStringContainsString('ENL777003', $bloc);
    }

    /** Lot 112 : le bon de livraison porte l'identité fiscale du client et celle de DALAKOUN. */
    public function test_le_bon_de_livraison_porte_ncc_et_regime_du_client_et_de_l_entreprise(): void
    {
        $bon = \App\Models\Enlevement::whereNotNull('code_enleve')->whereHas('livraison')->orderByDesc('id')->get()
            ->first(fn ($e) => $e->livraison?->client);
        if (!$bon) {
            $this->markTestSkipped('Aucun bon avec client.');
        }
        $bon->livraison->client->update(['ncc_clt' => '12 34567 A', 'regime_imposition' => 'RSI']);
        \App\Models\Configuration::first()->update(['ncc' => '7654321Z', 'rccm' => 'CI-ABJ-2014-B-22650', 'regime_imposition' => 'RNI']);

        $html = view('livreur.bonImprime', \App\Services\BonDeLivraisonClient::donnees($bon->fresh()))->render();
        $this->assertStringContainsString('NCC : <span style="font-weight:bold">1234567A</span>', $html, 'NCC du client, sans espace.');
        $this->assertStringContainsString('RSI (Réel simplifié d&#039;imposition)', $html, 'Régime du client.');
        $this->assertStringContainsString('NCC : 7654321Z', $html, "NCC de l'entreprise dans l'en-tête.");
        $this->assertStringContainsString('RCCM : CI-ABJ-2014-B-22650', $html);
        $this->assertStringContainsString("Régime d&#039;imposition : RNI", $html);

        // Le bon tient toujours sur UNE page, signature comprise.
        $pdf = \PDF::loadView('livreur.bonImprime', \App\Services\BonDeLivraisonClient::donnees($bon->fresh()));
        $pdf->render();
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count(), 'Le cadre de signature ne doit pas glisser sur une seconde page.');

        // Un particulier sans NCC ni régime : pas de ligne vide.
        $bon->livraison->client->update(['ncc_clt' => null, 'regime_imposition' => null]);
        $html = view('livreur.bonImprime', \App\Services\BonDeLivraisonClient::donnees($bon->fresh()))->render();
        $this->assertStringNotContainsString('NCC : <span style="font-weight:bold">', $html);
    }

    public function test_la_facture_fne_complete_ncc_et_regime_du_client_depuis_sa_fiche(): void
    {
        // La fixture se construit elle-même : une commande réelle avec client, une facture certifiée neuve.
        $commande = \App\Models\Commande::whereNotNull('client_id')->orderByDesc('id')->get()->first(fn ($c) => $c->client);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec client.');
        }
        $ref = 'TEST' . strtoupper(substr(uniqid(), -8));
        $facture = Facture::create([
            'numero' => \Help::genererNumeroUnique('facture'), 'numero_fne' => $ref, 'user_id' => \App\Models\User::first()?->id,
            'statut' => 2, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id, 'client_id' => $commande->client_id,
            'montant' => 11262, 'fne_status' => 'certified', 'fne_invoice_id' => 'inv-' . $ref, 'fne_reference' => $ref,
            'fne_certified_at' => now(), 'fne_request_payload' => ['items' => [['taxes' => ['TVA']]]],
        ]);
        $facture->client->update(['ncc_clt' => '12 34567 A', 'regime_imposition' => 'RSI']);
        $d = json_decode(file_get_contents(base_path('tests/Fixtures/verification_dgi.json')), true);
        $d['clientNcc'] = null;
        $d['clientTaxRegime'] = null;

        $v = DocumentDgi::variables($facture->fresh(), $d);
        $this->assertSame('1234567A', $v['fne_client']['ncc'], 'NCC de la fiche, sans espace.');
        $this->assertSame('RSI', $v['fne_client']['regime_imposition']);
        // L'émetteur : NCC, RCCM et régime viennent de la réponse de la DGI.
        $this->assertSame('1339220N', $v['fne_config']['ncc']);
        $this->assertSame('CI-ABJ-03-2013-B13-12197', $v['fne_config']['rccm']);
        $this->assertSame('RNI', $v['fne_config']['regime_imposition']);

        // Quand la DGI renvoie le NCC du client, c'est elle qui fait foi.
        $d['clientNcc'] = '9999999Z';
        $this->assertSame('9999999Z', DocumentDgi::variables($facture->fresh(), $d)['fne_client']['ncc']);

        $html = view('document.factureDgi', DocumentDgi::variables($facture->fresh(), array_merge($d, ['clientNcc' => null])))->render();
        $this->assertStringContainsString('NCC :&nbsp; 1234567A', $html);
        $this->assertStringContainsString("Régime d'imposition :&nbsp; RSI", $html);
        $this->assertStringContainsString('RCCM :&nbsp; CI-ABJ-03-2013-B13-12197', $html);
    }
}
