<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Les factures DGI de l'application sont celles du site (lot 95, 16/09/2026). */
class FacturesDgiMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function unClientAvecFacture(): array
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => User::find($c->user_id));
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        $id = \Illuminate\Support\Facades\DB::table('facture')->insertGetId([
            'numero' => (string) random_int(100000, 999999), 'numero_fne' => 'REF-' . strtoupper(uniqid()),
            'user_id' => User::find($client->user_id)->id, 'statut' => 2, 'service' => 'COMMANDE', 'service_id' => 1,
            'client_id' => $client->id, 'montant' => 11800, 'fne_status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [Facture::find($id), $client, User::find($client->user_id)];
    }

    public function test_la_liste_et_le_pdf_du_site_sont_relayes(): void
    {
        [$facture, $client, $user] = $this->unClientAvecFacture();
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);
        Http::fake([
            'site.recette/api/interne/client/*/factures' => Http::response(['code' => 200, 'data' => [
                ['id' => $facture->id, 'numero' => $facture->numero, 'reference' => 'REF', 'type_document' => 'FACTURE', 'libelle' => 'Facture de vente',
                 'service' => 'COMMANDE', 'service_id' => 1, 'num_affaire' => '123456', 'montant' => 14224, 'date' => '16/09/2026 10:00:00',
                 'certifiee' => true, 'motif_avoir' => null, 'origine_numero' => null, 'lien_verification' => 'http://v'],
            ]], 200),
            'site.recette/api/interne/facture/*/pdf' => Http::response('%PDF-1.7 recette', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $acces = Crypt::encryptString((string) $user->id);

        $r = $this->postJson('/mon_gravier/liste-factures-dgi', ['access' => $acces, 'type' => 4])->assertOk()->assertJson(['code' => 200]);
        $this->assertSame('123456', $r->json('data.0.num_affaire'));

        $pdf = $this->post('/mon_gravier/facture-dgi-pdf', ['access' => $acces, 'type' => 4, 'idFacture' => $facture->id]);
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // La facture d'un autre client est refusée.
        $autre = Facture::where('client_id', '!=', $client->id)->whereNotNull('client_id')->first();
        if ($autre) {
            $this->post('/mon_gravier/facture-dgi-pdf', ['access' => $acces, 'type' => 4, 'idFacture' => $autre->id])
                ->assertOk()->assertJson(['code' => 404]);
        }
    }

    public function test_sans_site_le_motif_est_dit(): void
    {
        [$facture, $client, $user] = $this->unClientAvecFacture();
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);
        Http::fake(['site.recette/*' => Http::response(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403)]);
        $acces = Crypt::encryptString((string) $user->id);

        $this->postJson('/mon_gravier/liste-factures-dgi', ['access' => $acces, 'type' => 4])->assertOk()->assertJson(['code' => 503]);
        $r = $this->post('/mon_gravier/facture-dgi-pdf', ['access' => $acces, 'type' => 4, 'idFacture' => $facture->id])->assertOk()->assertJson(['code' => 503]);
        $this->assertStringContainsString('HTTP 403', $r->json('message'));
    }
}
