<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Le reçu de l'application est celui du site (lot 88, 15/09/2026). */
class RecuPdfMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function unPaiementEtSonClient(): array
    {
        $paiement = Paiement::where('statut', 1)->whereNotNull('client_id')->orderByDesc('id')->get()
            ->first(fn ($p) => Client::find($p->client_id)?->user_id && User::find(Client::find($p->client_id)->user_id));
        if (!$paiement) {
            $this->markTestSkipped('Aucun règlement validé rattaché à un client avec compte.');
        }
        $client = Client::find($paiement->client_id);

        return [$paiement, User::find($client->user_id), $client];
    }

    public function test_le_pdf_du_site_est_relaye_a_l_application(): void
    {
        [$paiement, $user] = $this->unPaiementEtSonClient();
        Http::fake(['*/api/interne/recu-paiement/*' => Http::response('%PDF-1.4 recette', 200, ['Content-Type' => 'application/pdf'])]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $r = $this->post('/mon_gravier/recu-paiement-pdf', [
            'access' => Crypt::encryptString((string) $user->id), 'type' => 4, 'niveau' => 2, 'idPaiement' => $paiement->id,
        ]);
        $r->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
        $this->assertSame('%PDF-1.4 recette', $r->getContent());
        Http::assertSent(fn ($req) => $req->url() === 'http://site.recette/api/interne/recu-paiement/' . $paiement->id . '/pdf');
    }

    public function test_sans_site_un_json_503_et_l_application_garde_son_recu(): void
    {
        [$paiement, $user] = $this->unPaiementEtSonClient();
        Http::fake(['*/api/interne/recu-paiement/*' => Http::response('Erreur', 500)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);

        $this->postJson('/mon_gravier/recu-paiement-pdf', [
            'access' => Crypt::encryptString((string) $user->id), 'type' => 4, 'niveau' => 2, 'idPaiement' => $paiement->id,
        ])->assertOk()->assertJson(['code' => 503])->assertJsonFragment(['message' => 'Le reçu du site n\'est pas disponible : le site a répondu HTTP 500']);

    }

    public function test_un_jeton_refuse_par_le_site_est_dit_a_l_application(): void
    {
        [$paiement, $user] = $this->unPaiementEtSonClient();
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);
        Http::fake(['*/api/interne/recu-paiement/*' => Http::response(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403)]);
        $r = $this->postJson('/mon_gravier/recu-paiement-pdf', [
            'access' => Crypt::encryptString((string) $user->id), 'type' => 4, 'niveau' => 2, 'idPaiement' => $paiement->id,
        ])->assertOk()->assertJson(['code' => 503]);
        $this->assertStringContainsString('HTTP 403', $r->json('message'));
        $this->assertStringContainsString('JETON_INTERNE', $r->json('message'));
    }

    public function test_le_reglement_d_un_autre_client_est_refuse(): void
    {
        [$paiement, $user, $client] = $this->unPaiementEtSonClient();
        $autre = Client::where('id', '!=', $client->id)->whereNotNull('user_id')->get()->first(fn ($c) => User::find($c->user_id));
        if (!$autre) {
            $this->markTestSkipped('Un seul client.');
        }
        Http::fake();
        $this->postJson('/mon_gravier/recu-paiement-pdf', [
            'access' => Crypt::encryptString((string) $autre->user_id), 'type' => 4, 'niveau' => 2, 'idPaiement' => $paiement->id,
        ])->assertOk()->assertJson(['code' => 404]);
        Http::assertNothingSent();
    }
}
