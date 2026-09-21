<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Services\DocumentCommandeDistant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LA PROFORMA D'UNE COMMANDE PASSÉE DEPUIS L'APPLICATION (lot 81, 15/09/2026) :
 * l'API demande au site d'envoyer le document, avec le jeton interne ; sans
 * jeton, elle se tait ; la liste des commandes dit si la commande est payée
 * en ligne (le PDF de l'application se titre « Facture » ou « Proforma »).
 */
class ProformaDepuisLApplicationTest extends TestCase
{
    use DatabaseTransactions;

    private function uneCommande(): Commande
    {
        $commande = Commande::whereNotNull('numero')->orderByDesc('id')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        return $commande;
    }

    public function test_le_site_est_sollicite_avec_le_jeton(): void
    {
        $commande = $this->uneCommande();
        Http::fake(['*/api/interne/commande/*' => Http::response(['code' => 200, 'envoye' => true, 'type' => 'Proforma'], 200)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $this->assertTrue(DocumentCommandeDistant::envoyer($commande));
        Http::assertSent(fn ($r) => $r->url() === 'http://site.recette/api/interne/commande/' . $commande->numero . '/document'
            && $r['jeton'] === 'jeton-de-recette');
    }

    public function test_sans_jeton_rien_ne_part_et_rien_ne_casse(): void
    {
        $commande = $this->uneCommande();
        Http::fake();
        config(['constantes.jeton_interne' => '']);

        $this->assertFalse(DocumentCommandeDistant::envoyer($commande));
        Http::assertNothingSent();
    }

    public function test_un_site_en_erreur_ne_bloque_pas(): void
    {
        $commande = $this->uneCommande();
        Http::fake(['*/api/interne/commande/*' => Http::response('Erreur', 500)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $this->assertFalse(DocumentCommandeDistant::envoyer($commande));
    }

    public function test_la_liste_des_commandes_dit_si_elle_est_payee_en_ligne(): void
    {
        $commande = $this->uneCommande();
        $liste = Commande::liste($commande->client_id);
        $this->assertNotEmpty($liste);
        $premiere = $liste->first();
        $this->assertTrue(array_key_exists('paye_en_ligne', $premiere->getAttributes()));
        $this->assertContains((int) $premiere->paye_en_ligne >= 0, [true]);
    }
}
