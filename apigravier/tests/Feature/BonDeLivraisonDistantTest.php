<?php

namespace Tests\Feature;

use App\Models\Livraison;
use App\Services\BonDeLivraisonDistant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Le bon de livraison demandé au site après une course clôturée depuis l'application (lot 84). */
class BonDeLivraisonDistantTest extends TestCase
{
    use DatabaseTransactions;

    private function uneLivraison(): Livraison
    {
        $l = Livraison::orderByDesc('id')->first();
        if (!$l) {
            $this->markTestSkipped('Aucune livraison en base.');
        }

        return $l;
    }

    public function test_le_site_est_sollicite_avec_le_jeton(): void
    {
        $l = $this->uneLivraison();
        Http::fake(['*/api/interne/livraison/*' => Http::response(['code' => 200, 'envoye' => true], 200)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $this->assertTrue(BonDeLivraisonDistant::envoyer($l));
        Http::assertSent(fn ($r) => $r->url() === 'http://site.recette/api/interne/livraison/' . $l->id . '/bon-de-livraison'
            && $r['jeton'] === 'jeton-de-recette');
    }

    public function test_sans_jeton_ou_site_en_erreur_rien_ne_casse(): void
    {
        $l = $this->uneLivraison();
        Http::fake(['*/api/interne/livraison/*' => Http::response('Erreur', 500)]);
        config(['constantes.jeton_interne' => '']);
        $this->assertFalse(BonDeLivraisonDistant::envoyer($l));
        Http::assertNothingSent();

        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);
        $this->assertFalse(BonDeLivraisonDistant::envoyer($l));
    }
}
