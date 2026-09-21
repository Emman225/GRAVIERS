<?php

namespace Tests\Feature;

use App\Models\Paiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Le PDF du reçu remis à l'API pour l'application (lot 88, 15/09/2026). */
class RecuPdfPourLApplicationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_route_interne_rend_le_pdf_du_recu_avec_le_jeton_et_le_refuse_sans(): void
    {
        $paiement = Paiement::where('statut', 1)->orderByDesc('id')->first();
        if (!$paiement) {
            $this->markTestSkipped('Aucun règlement validé.');
        }
        config(['constantes.jeton_interne' => 'jeton-de-recette']);

        $this->postJson('/api/interne/recu-paiement/' . $paiement->id . '/pdf', ['jeton' => 'faux'])->assertStatus(403);

        $r = $this->post('/api/interne/recu-paiement/' . $paiement->id . '/pdf', ['jeton' => 'jeton-de-recette']);
        $r->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
        $this->assertTrue(str_starts_with($r->getContent(), '%PDF'), 'Le corps est un PDF.');
        $this->assertNotEmpty($r->headers->get('X-Numero-Recu'));

        $this->postJson('/api/interne/recu-paiement/999999999/pdf', ['jeton' => 'jeton-de-recette'])->assertStatus(404);
    }
}
