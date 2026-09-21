<?php

namespace Tests\Feature;

use App\Models\BlClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Produit;
use App\Services\FacturationCommande;
use App\Services\FneService;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LE NUMÉRO DE BON DE COMMANDE INTERNE (09/09/2026).
 *
 * 1. Sur la proforma (/recapitulatif-commande), une ENTREPRISE ne passe pas
 *    sans numéro de bon : le serveur le refuse, et le retient même sans pièce
 *    jointe.
 * 2. Le numéro va en colonne Réf, après le numéro de ligne — « 01 - BC-77 » — sur la
 *    proforma, le devis (qui le fige), la facture et la facture normalisée ; la
 *    désignation reste nue (13/09/2026).
 */
class BonDeCommandeDevantLaDesignationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_ecriture_du_prefixe(): void
    {
        $this->assertSame('BC n° 1234 — ', \Help::prefixeBonDeCommande(' 1234 '));
        // Depuis le 10/09/2026, le numéro va en colonne Réf : la référence est le numéro nu.
        $this->assertSame('1234', \Help::referenceBonDeCommande(' 1234 '));
        $this->assertSame('', \Help::referenceBonDeCommande(null));
        $this->assertSame('', \Help::prefixeBonDeCommande(null));
        $this->assertSame('', \Help::prefixeBonDeCommande('   '));
        // 13/09/2026 : « numéro de ligne - numéro de bon », le numéro de ligne seul sans bon.
        $this->assertSame('01 - NFJ154', \Help::referenceLigne(1, ' NFJ154 '));
        $this->assertSame('02', \Help::referenceLigne(2, null));
        $this->assertSame('12', \Help::referenceLigne(12, '   '));
    }

    public function test_la_proforma_refuse_une_entreprise_sans_numero_et_retient_le_numero_saisi(): void
    {
        $client = Client::where('type_client', 'ENTREPRISE')->where('statut', 1)
            ->whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        $produit = Produit::whereHas('uniteProduit')->first();
        if (!$client || !$produit) {
            $this->markTestSkipped('Il faut une entreprise active et un produit.');
        }
        Auth::guard('web')->login($client->user);
        Cart::add($produit->id, $produit->nom, 1, 100, ['unite' => 'U'])->associate(Produit::class);
        $session = ['0' => ['cout_livraison' => 0, 'tva_transport' => 0, 'tva' => 18, 'infoSup' => 'Angré'], 'remise' => 0];

        // Sans numéro : refus (erreur de validation sur numero_bon).
        $this->withSession($session)
            ->post('/recapitulatif-commande', ['type_livraison' => 1, 'date_livraison' => now()->addDay()->toDateString(), 'numero_bon' => ''])
            ->assertSessionHasErrors('numero_bon');

        // Avec un numéro, sans pièce jointe : retenu, et devant chaque désignation.
        $reponse = $this->withSession($session)
            ->post('/recapitulatif-commande', ['type_livraison' => 1, 'date_livraison' => now()->addDay()->toDateString(), 'numero_bon' => 'BC-77']);
        $reponse->assertOk();
        $this->assertSame('BC-77', session('numero_bon_commande'));
        // Le numéro est en colonne Réf, la désignation reste nue (10/09/2026).
        $this->assertStringContainsString('<td class="col-ref">01 - BC-77</td>', $reponse->getContent());
        $this->assertStringNotContainsString('BC n° BC-77 — ', $reponse->getContent());
    }

    public function test_la_facture_de_vente_porte_le_numero_devant_chaque_designation(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }
        BlClient::where('commande_id', $commande->id)->delete();
        BlClient::create(['numero' => 'BC-4521', 'client_id' => $commande->client_id, 'commande_id' => $commande->id, 'fichier' => 'x']);
        Facture::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->delete();
        $facture = Facture::create([
            'numero' => 'T' . substr((string) time(), -8), 'user_id' => $commande->client->user_id ?? 1,
            'client_id' => $commande->client_id, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
            'montant' => round($commande->montantAPayer()), 'statut' => 2, 'fne_status' => 'pending',
        ]);

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, $commande->fresh()))->render();
        $nom = \Help::phrase($commande->detailCommande->first()->produit->nom);
        $this->assertStringContainsString('<td class="col-ref">01 - BC-4521</td>', $html, 'La facture ne porte pas le numéro en colonne Réf.');
        // La facture normalisée porte la même référence (13/09/2026).
        $items = FneService::buildSalePayload($facture->fresh())['items'];
        $this->assertSame('01 - BC-4521', $items[0]['reference'], 'La facture normalisée ne porte pas « 01 - N° de bon » en référence.');
        $this->assertStringContainsString($nom, $html);
        $this->assertStringNotContainsString('BC n° BC-4521 — ', $html, 'Le numéro ne doit plus précéder la désignation.');
    }

    public function test_le_devis_fige_le_numero_et_le_porte_devant_chaque_designation(): void
    {
        $devis = Devis::whereHas('detailDevis')->whereHas('client.user')->first();
        if (!$devis || !$devis->detailDevis->first()?->produit) {
            $this->markTestSkipped('Aucun devis avec des lignes.');
        }
        $devis->numero_bon_commande = 'BC-9001';
        $devis->save();
        $this->assertSame('BC-9001', Devis::find($devis->id)->numero_bon_commande, 'La colonne devis.numero_bon_commande manque.');

        $nom = $devis->detailDevis->first()->produit->nom;
        $html = view('document.factureDevis', array_merge(
            ['devis' => $devis->fresh(), 'image' => config('constantes.logo'), 'config' => Configuration::first()],
            FneService::getDonneesFneDevis($devis, $devis->client)
        ))->render();
        $this->assertStringContainsString('<td class="col-ref">01 - BC-9001</td>', $html, 'Le devis ne porte pas le numéro en colonne Réf.');
        $this->assertStringNotContainsString('BC n° BC-9001 — ', $html);

        Auth::guard('web')->login($devis->client->user);
        $page = $this->get(route('client.devisValide', $devis))->assertOk()->getContent();
        $this->assertStringContainsString('<td class="col-ref">01 - BC-9001</td>', $page, 'La page « devis validé » ne porte pas le numéro en colonne Réf.');
    }
}
