<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Facture;
use App\Models\User;
use App\Services\FactureAvoir;
use App\Services\FacturationCommande;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Facture d'avoir certifiée FNE, AIRSI déclaré, établissement forçable (lot 92, 16/09/2026). */
class FactureDAvoirFneTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    /** Une facture de vente certifiée, telle que la DGI la renvoie (deux articles). */
    private function uneFactureCertifiee(float $airsi = 0): Facture
    {
        $commande = Commande::whereNotNull('client_id')->orderByDesc('id')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande.');
        }

        // numero_fne est unique : chaque facture de recette a la sienne.
        $ref = 'REF-' . strtoupper(uniqid());

        return Facture::create([
            'numero'               => \Help::genererNumeroUnique('facture'),
            'numero_fne'           => $ref,
            'user_id'              => $this->unAdmin()->id,
            'statut'               => 2,
            'service'              => \Help::$COMMANDE,
            'service_id'           => $commande->id,
            'client_id'            => $commande->client_id,
            'montant'              => 12980, // (10 × 1 000 + 2 × 500) × 1,18
            'airsi_applique'       => $airsi,
            'fne_status'           => 'certified',
            'fne_invoice_id'       => 'inv-origine',
            'fne_reference'        => $ref,
            'fne_certified_at'     => now(),
            'fne_request_payload'  => ['items' => [['taxes' => ['TVA']]]],
            'fne_response_payload' => ['reference' => $ref, 'invoice' => ['id' => 'inv-origine', 'items' => [
                ['id' => 'it-1', 'reference' => '01', 'description' => 'Gravier 0/5', 'quantity' => 10, 'amount' => 1000, 'measurementUnit' => 'T'],
                ['id' => 'it-2', 'reference' => '02', 'description' => 'Sable', 'quantity' => 2, 'amount' => 500, 'measurementUnit' => 'T'],
            ]]],
        ]);
    }

    private function fneActive(): void
    {
        config(['fne.enabled' => true, 'fne.api_key' => 'cle-de-recette', 'fne.base_url' => 'http://fne.recette/ws']);
    }

    public function test_l_avoir_est_certifie_puis_enregistre_en_negatif(): void
    {
        $this->fneActive();
        $origine = $this->uneFactureCertifiee();
        $dejaFacture = FacturationCommande::montantDejaFacture($origine->commande);
        Http::fake(['fne.recette/ws/external/invoices/inv-origine/refund' => Http::response([
            'ncc' => '1339220N', 'reference' => 'A1339220N26000000001',
            'token' => 'http://fne.recette/fr/verification/abc', 'warning' => false, 'balance_sticker' => 519,
        ], 201)]);

        $r = FactureAvoir::emettre($origine, ['it-1' => 4, 'it-2' => 0], 'Marchandise retournée', $this->unAdmin()->id);

        $this->assertTrue($r['success'], $r['message']);
        $avoir = $r['facture'];
        $this->assertTrue($avoir->estUnAvoir());
        $this->assertSame('A1339220N26000000001', $avoir->fne_reference);
        $this->assertSame('certified', $avoir->fne_status);
        $this->assertSame($origine->id, $avoir->facture_origine_id);
        // 4 × 1 000 sur 11 000 de HT : 12 980 × 4/11 = 4 720, en négatif.
        $this->assertSame(-4720.0, (float) $avoir->montant);
        $this->assertSame(0.0, $avoir->totalAPayer(), 'Un avoir ne se réclame pas.');
        $this->assertSame('Avoir', $avoir->statutCreance());
        $this->assertSame($dejaFacture - 4720.0, FacturationCommande::montantDejaFacture($origine->commande), 'Le déjà facturé se corrige de lui-même.');
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/external/invoices/inv-origine/refund')
            && $req['items'] === [['id' => 'it-1', 'quantity' => 4.0]]
            && $req->hasHeader('Authorization', 'Bearer cle-de-recette'));

        // Il reste 6 à créditer sur le premier article ; on ne dépasse pas.
        $origine->refresh();
        $restes = collect($origine->articlesCertifies())->pluck('reste', 'id');
        $this->assertSame(6.0, $restes['it-1']);
        $this->assertSame(2.0, $restes['it-2']);
        $r2 = FactureAvoir::emettre($origine, ['it-1' => 7], 'Trop', null);
        $this->assertFalse($r2['success']);
        $this->assertStringContainsString('supérieure', $r2['message']);

        // Le formulaire et le document.
        URL::forceRootUrl('');
        $admin = $this->unAdmin();
        $this->actingAs($admin)->get('/facture-avoir/' . $origine->id . '/nouveau')->assertOk()->assertSee('Gravier 0/5');
        $doc = $this->actingAs($admin)->get('/facture-avoir/' . $avoir->id . '/voir');
        $doc->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $doc->headers->get('content-type'));
        // Un avoir passe par son document même par les anciennes adresses.
        $this->actingAs($admin)->get('/facture-livraison/' . $avoir->id . '/voir')->assertOk();
        $this->actingAs($admin)->get('/factures-validees')->assertOk()->assertSee('A1339220N26000000001');
    }

    public function test_sans_certification_aucun_avoir_n_est_cree(): void
    {
        $this->fneActive();
        $origine = $this->uneFactureCertifiee();
        Http::fake(['fne.recette/*' => Http::response(['message' => 'Internal Server Error', 'statusCode' => 500], 500)]);
        $avant = Facture::where('facture_origine_id', $origine->id)->count();

        $r = FactureAvoir::emettre($origine, ['it-1' => 1], 'Erreur', null);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Internal Server Error', $r['message']);
        $this->assertSame($avant, Facture::where('facture_origine_id', $origine->id)->count());

        // Module désactivé : même refus, motif clair ; motif vide : refus avant tout appel.
        config(['fne.enabled' => false]);
        $this->assertFalse(FactureAvoir::emettre($origine, ['it-1' => 1], 'x', null)['success']);
        $this->assertStringContainsString('motif', FactureAvoir::emettre($origine, ['it-1' => 1], '  ', null)['message']);
    }

    public function test_l_airsi_est_declare_et_l_etablissement_est_forcable(): void
    {
        $origine = $this->uneFactureCertifiee(600);
        $payload = FneService::buildSalePayload($origine);
        $this->assertSame([['name' => 'AIRSI', 'amount' => \Help::tauxAirsi()]], $payload['customTaxes'] ?? null);

        $sans = FneService::buildSalePayload($this->uneFactureCertifiee(0));
        $this->assertArrayNotHasKey('customTaxes', $sans);

        config(['fne.defaults.establishment' => 'AFRICA PROJECT MANAGEMENT']);
        $this->assertSame('AFRICA PROJECT MANAGEMENT', FneService::buildSalePayload($origine)['establishment']);
        config(['fne.defaults.establishment' => '']);
        $this->assertSame((string) (\App\Models\Configuration::first()?->nom_etablissement ?: 'DALAKOUN'),
            FneService::buildSalePayload($origine)['establishment']);
    }
}
