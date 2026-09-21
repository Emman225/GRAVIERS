<?php

namespace Tests\Feature;

use App\Models\Reduction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * CODES PROMO (09/09/2026) :
 *  1. sur la page de modification (/update-de-code-promo-{id}), le bouton
 *     s'appelle « Enregistrer », plus « Modifier le code promo » ;
 *  2. sur la page de création, chaque code de la liste se copie d'un clic et
 *     se partage par WhatsApp (wa.me), comme les codes de livraison du client.
 */
class CodePromoCopierPartagerTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur.');
        }

        return $u;
    }

    public function test_la_liste_propose_copier_et_whatsapp_pour_chaque_code(): void
    {
        Auth::guard('web')->login($this->admin());
        $reduction = Reduction::whereNull('deleted_at')->first();
        if (!$reduction) {
            $this->markTestSkipped('Aucun code promo.');
        }

        $html = $this->get('/creation-de-code-promo')->assertOk()->getContent();
        $this->assertStringContainsString('js-copier-code', $html, 'Le bouton « copier » manque.');
        $this->assertStringContainsString('data-code="' . $reduction->code . '"', $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html, 'Le lien WhatsApp manque.');
        $this->assertStringContainsString(rawurlencode('Code promo GRAVIER.COM : ' . $reduction->code), $html);
        $this->assertStringContainsString('fa-brands fa-whatsapp', $html);
        $this->assertStringContainsString('navigator.clipboard', $html, 'Le script de copie manque.');
    }

    public function test_le_bouton_de_modification_s_appelle_enregistrer(): void
    {
        Auth::guard('web')->login($this->admin());
        $reduction = Reduction::whereNull('deleted_at')->first();
        if (!$reduction) {
            $this->markTestSkipped('Aucun code promo.');
        }

        $html = $this->get('/update-de-code-promo-' . $reduction->id)->assertOk()->getContent();
        $this->assertStringContainsString('class="btn btn-primary">Enregistrer</button>', $html);
        $this->assertStringNotContainsString('Modifier le code promo', $html);
    }
}
