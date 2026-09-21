<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Services\FacturesDuClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Les factures DGI du client pour l'application, et la prévisualisation web (lot 95, 16/09/2026). */
class FacturesDgiPourLApplicationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_liste_et_le_pdf_passent_par_le_jeton(): void
    {
        $commande = \App\Models\Commande::whereNotNull('client_id')->orderByDesc('id')->get()->first(fn ($c) => $c->client);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec client.');
        }
        $client = $commande->client;
        $facture = Facture::create([
            'numero' => \Help::genererNumeroUnique('facture'), 'numero_fne' => 'REF-' . strtoupper(uniqid()),
            'user_id' => \App\Models\User::first()?->id, 'statut' => 2, 'service' => \Help::$COMMANDE,
            'service_id' => $commande->id, 'client_id' => $client->id, 'montant' => 11800, 'fne_status' => 'pending',
        ]);
        config(['constantes.jeton_interne' => 'jeton-de-recette']);

        $lignes = FacturesDuClient::pourLApplication($client);
        $ligne = collect($lignes)->firstWhere('id', $facture->id);
        $this->assertNotNull($ligne);
        $this->assertSame('FACTURE', $ligne['type_document']);
        $this->assertSame($facture->commande->numero, $ligne['num_affaire']);
        $this->assertArrayHasKey('certifiee', $ligne);

        $this->postJson('/api/interne/client/' . $client->id . '/factures', ['jeton' => 'faux'])->assertStatus(403);
        $r = $this->postJson('/api/interne/client/' . $client->id . '/factures', ['jeton' => 'jeton-de-recette'])->assertOk();
        $this->assertSame(count($lignes), count($r->json('data')));

        $pdf = $this->post('/api/interne/facture/' . $facture->id . '/pdf', ['jeton' => 'jeton-de-recette']);
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->post('/api/interne/facture/' . $facture->id . '/pdf', ['jeton' => 'faux'])->assertStatus(403);
    }

    public function test_le_document_de_mon_compte_se_previsualise_et_se_telecharge(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user && $c->commande->isNotEmpty());
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte et commande.');
        }
        $commande = $client->commande->first();
        Auth::guard('web')->login($client->user);

        $voir = $this->get('/commande-' . $commande->numero . '-document-pdf');
        $voir->assertOk();
        $this->assertStringContainsString('inline', (string) $voir->headers->get('content-disposition'));
        $fichier = $this->get('/commande-' . $commande->numero . '-document-pdf?action=telecharger');
        $fichier->assertOk();
        $this->assertStringContainsString('attachment', (string) $fichier->headers->get('content-disposition'));
    }
}
