<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\TvaCommande;
use App\Models\User;
use App\Services\FacturationCommande;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LE CODE D'EXONÉRATION FNE CHOISI AU RETRAIT DE LA TVA (lot 82, 15/09/2026).
 */
class CodeExonerationFneParClientTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }

        return $admin;
    }

    public function test_la_base_porte_la_colonne(): void
    {
        $this->assertTrue(Schema::hasColumn('client', 'code_exoneration_fne'));
    }

    public function test_le_retrait_enregistre_le_code_et_le_retablissement_l_efface(): void
    {
        $client = Client::where('statut', 1)->first();
        $client->update(['applique_tva' => 1, 'code_exoneration_fne' => null]);

        $this->actingAs($this->admin())->get(route('show.appliqueTva', $client) . '?code=TVAC')->assertRedirect();
        $this->assertSame(0, (int) $client->fresh()->applique_tva);
        $this->assertSame('TVAC', $client->fresh()->code_exoneration_fne);
        $this->assertSame('Exonération conventionnelle (TVAC)', $client->fresh()->libelleExonerationFne());

        $this->actingAs($this->admin())->get(route('show.appliqueTva', $client))->assertRedirect();
        $this->assertSame(1, (int) $client->fresh()->applique_tva);
        $this->assertNull($client->fresh()->code_exoneration_fne);

        // Sans code (ancien lien) : le réglage par défaut.
        $this->actingAs($this->admin())->get(route('show.appliqueTva', $client))->assertRedirect();
        $this->assertSame(0, (int) $client->fresh()->applique_tva);
        $this->assertSame(config('fne.defaults.exempt_tax', 'TVAD'), $client->fresh()->code_exoneration_fne);

        // Un code inconnu retombe sur le défaut (remise à 1 en base : l'instance est périmée).
        Client::where('id', $client->id)->update(['applique_tva' => 1, 'code_exoneration_fne' => null]);
        $this->actingAs($this->admin())->get(route('show.appliqueTva', $client) . '?code=XXX')->assertRedirect();
        $this->assertSame(config('fne.defaults.exempt_tax', 'TVAD'), $client->fresh()->code_exoneration_fne);
    }

    public function test_la_facture_dgi_et_le_resume_suivent_le_code_du_client(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }
        $ligne = TvaCommande::where('commande_id', $commande->id)->first();
        if ($ligne) { $ligne->update(['montant' => 0]); }
        else { TvaCommande::create(['client_id' => $commande->client_id, 'commande_id' => $commande->id, 'montant' => 0, 'type_affaire' => \Help::$VENTE]); }
        Client::where('id', $commande->client_id)->update(['applique_tva' => 0, 'code_exoneration_fne' => 'TVAC']);

        $facture = Facture::create([
            'numero' => 'T' . substr((string) time(), -8), 'user_id' => $commande->client->user_id ?? null,
            'client_id' => $commande->client_id, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
            'montant' => round($commande->montantAPayer()), 'statut' => 2, 'fne_status' => 'pending',
        ]);
        $commande = Commande::find($commande->id);

        $charge = FneService::buildSalePayload($facture->fresh());
        $this->assertSame(['TVAC'], $charge['items'][0]['taxes']);

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, $commande))->render();
        $this->assertStringContainsString('TVA exo.conv. - Pas de TVA sur HT 00,00% - C', $html);

        Client::where('id', $commande->client_id)->update(['code_exoneration_fne' => 'TVAD']);
        $charge = FneService::buildSalePayload($facture->fresh());
        $this->assertSame(['TVAD'], $charge['items'][0]['taxes']);
        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, Commande::find($commande->id)))->render();
        $this->assertStringContainsString('TVA exo.lég - Pas de TVA sur HT 00,00% - D', $html);
    }

    public function test_la_liste_des_clients_propose_les_deux_motifs(): void
    {
        $client = Client::where('statut', 1)->where('type_client', 'PARTICULIER')->first() ?? Client::where('statut', 1)->first();
        $client->update(['applique_tva' => 1]);

        $this->actingAs($this->admin())->get(route('show.listClient'))->assertOk()
            ->assertSee('exonération légale (TVAD)')
            ->assertSee('exonération conventionnelle (TVAC)');
    }
}
