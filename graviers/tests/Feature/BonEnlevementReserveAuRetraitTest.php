<?php

namespace Tests\Feature;

use App\Models\Livraison;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE BON D'ENLÈVEMENT N'EST MONTRÉ AU CLIENT QUE S'IL RETIRE LUI-MÊME (08/09/2026).
 *
 * Quand un livreur vient, c'est lui qui porte le bon ; le client n'a que le
 * code de livraison à remettre. Le partiel des codes suit cette règle sur
 * toutes les pages qui l'incluent (Mes commandes, suivi, récupération).
 */
class BonEnlevementReserveAuRetraitTest extends TestCase
{
    use DatabaseTransactions;

    private function uneLivraisonAvecBon(): Livraison
    {
        $l = Livraison::whereHas('enlevement', fn ($q) => $q->whereNotNull('code_enleve')->where('code_enleve', '<>', ''))
            ->whereNotNull('numero')
            ->orderByDesc('id')
            ->first();
        if (!$l) {
            $this->markTestSkipped('Aucune livraison avec un bon d\'enlèvement.');
        }
        return $l;
    }

    public function test_une_livraison_par_un_livreur_ne_montre_que_le_code_de_livraison(): void
    {
        $l = $this->uneLivraisonAvecBon();

        $html = view('client._codesLivraison', ['livraison' => $l, 'numeroCommande' => '123456'])->render();

        $this->assertStringContainsString('Code de livraison', $html);
        $this->assertStringContainsString($l->numero, $html);
        // Blade échappe l'apostrophe : on cherche la forme rendue.
        $this->assertStringNotContainsString('Bon d&#039;enl', $html);
        $this->assertStringNotContainsString($l->enlevement->code_enleve, $html);
    }

    public function test_un_retrait_par_le_client_ne_montre_que_le_bon(): void
    {
        $l = $this->uneLivraisonAvecBon();

        $html = view('client._codesLivraison', ['livraison' => $l, 'queEnlevement' => true])->render();

        $this->assertStringContainsString('Bon d&#039;enl', $html);
        $this->assertStringContainsString($l->enlevement->code_enleve, $html);
        $this->assertStringNotContainsString('Code de livraison', $html);
    }
}
