<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Régression du 16/09/2026 : avec « Retrait sur place », le bloc d'adresse masqué
 * gardait ses champs `required` et le navigateur refusait d'envoyer le formulaire
 * en silence. L'obligation doit suivre la visibilité du bloc.
 */
class RetraitSurPlaceSansChampObligatoireCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_obligation_des_champs_d_adresse_suit_la_visibilite_du_bloc(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        Auth::guard('web')->login($client->user);
        session(['type_affaire' => 'COMMANDE']);

        $html = $this->get('/commande-en-adresse')->assertOk()->getContent();

        // Les champs restent obligatoires pour une livraison (lot 86)…
        $this->assertMatchesRegularExpression('/<select id="ville" required/', $html);
        $this->assertMatchesRegularExpression('/name="infoSup" type="text" required/', $html);
        // … et l'obligation est levée quand le bloc est masqué, dans les deux sens,
        // et dès le chargement si « Retrait » est déjà coché.
        $this->assertStringContainsString('function ajusterChampsObligatoires()', $html);
        $this->assertStringContainsString("form.style.display = 'none';\n            ajusterChampsObligatoires();", str_replace("\r\n", "\n", $html));
        $this->assertStringContainsString("form.style.display = 'block';\n            ajusterChampsObligatoires();", str_replace("\r\n", "\n", $html));
        $this->assertStringContainsString('if (recuperer.checked) {', $html);
    }
}
