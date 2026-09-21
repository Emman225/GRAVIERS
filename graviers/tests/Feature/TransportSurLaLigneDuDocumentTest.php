<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Location;
use App\Models\User;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LE TRANSPORT EST UNE LIGNE DU TABLEAU, TAXÉE QUAND LE PARAMÉTRAGE LE DIT
 * (09/09/2026, demande du client sur une commande passée depuis l'application).
 *
 * Sur la facture de vente, la facture de location, le devis et la page
 * « commande validée », le coût de livraison figure dans le tableau des
 * articles (P.U, Qté 1, Forfait) avec la mention « TVA (18%) » lorsque la case
 * « Appliquer la TVA au transport » l'avait taxé ; le TOTAL HT reprend la
 * somme du tableau et une seule ligne « TVA » porte les deux TVA. Les anciennes
 * lignes des totaux (« Transport (HT) », « Coût livraison », « TVA sur
 * transport ») disparaissent. La facture normalisée (FNE) déclare le même code
 * de taxe que celui réellement facturé.
 */
class TransportSurLaLigneDuDocumentTest extends TestCase
{
    use DatabaseTransactions;

    private function ligneTransport(string $html): string
    {
        $p = strpos($html, 'Coût de livraison');
        $this->assertNotFalse($p, 'Aucune ligne « Coût de livraison » dans le tableau.');

        return substr($html, $p, 700);
    }

    private function assertAnciennesLignesAbsentes(string $html): void
    {
        // (transport taxé) plus de lignes de transport sous les totaux
        $this->assertStringNotContainsString('Transport (HT)', $html);
        $this->assertStringNotContainsString('TVA sur transport', $html);
        $this->assertStringNotContainsString('Coût livraison</td>', $html);
    }

    public function test_la_facture_de_location_porte_le_transport_en_ligne_taxee(): void
    {
        $location = Location::whereHas('detailLocation')->whereHas('client')->first();
        if (!$location) {
            $this->markTestSkipped('Aucune location avec des lignes.');
        }
        $location->cout_livraison_client = 4000;
        $location->tva_transport = 720;
        $location->save();

        $facture = Facture::create([
            'numero'     => 'T' . substr((string) time(), -8),
            'user_id'    => $location->client->user_id ?? User::value('id'),
            'client_id'  => $location->client_id,
            'service'    => \Help::$LOCATION,
            'service_id' => $location->id,
            'montant'    => round($location->montantAPayer()),
            'statut'     => 2,
            'fne_status' => 'pending',
        ]);

        $config = Configuration::first();
        $rendre = function () use ($location, $facture, $config) {
            $loc = Location::with('detailLocation.produit.uniteProduit', 'tvaLocation', 'adresseLivraison', 'client')->find($location->id);

            return view('document.factureLocation', array_merge(
                ['location' => $loc, 'facture' => $facture, 'config' => $config, 'livraison' => 1],
                FneService::getDonneesFne($facture, $facture->client)
            ))->render();
        };

        $html = $rendre();
        $ligne = $this->ligneTransport($html);
        $this->assertStringContainsString('TVA (' . $config->tva . '%)', $ligne, 'La ligne de transport ne porte pas la mention de TVA.');
        $this->assertStringContainsString('Forfait', $ligne);
        $this->assertAnciennesLignesAbsentes($html);

        // Le TOTAL HT est la somme du tableau ; la ligne TVA fond les deux TVA.
        $ht          = (float) $location->detailLocation->sum('prix');
        $remise      = \Help::arrondiFranc((float) ($location->remise ?? 0));
        $tvaArticles = \Help::arrondiFranc(max(0, $ht - $remise) * ($config->tva / 100));
        $this->assertStringContainsString(number_format($ht + 4000, 0, '', ' ') . '</td>', $html, 'Le TOTAL HT ne compte pas le transport.');
        $this->assertStringContainsString(number_format($tvaArticles + 720, 0, '', ' ') . '</td>', $html, 'La ligne TVA ne fond pas la TVA du transport.');

        // Sans taxation : présentation d'avant (décision du client, 09/09/2026) —
        // pas de ligne dans le tableau, le coût de livraison sous les totaux.
        $location->tva_transport = 0;
        $location->save();
        $html = $rendre();
        $this->assertStringNotContainsString('Coût de livraison', $html);
        $this->assertStringContainsString('Coût livraison</td>', $html);
        $this->assertStringContainsString(number_format($ht, 0, '', ' ') . '</td>', $html, 'Le TOTAL HT non taxé doit être celui des articles.');
    }

    public function test_le_devis_porte_le_transport_en_ligne_taxee(): void
    {
        $devis = Devis::whereHas('detailDevis')->first();
        if (!$devis) {
            $this->markTestSkipped('Aucun devis avec des lignes.');
        }
        $devis->cout_livraison = 4000;
        $devis->tva_transport = 720;
        $devis->save();

        $config = Configuration::first();
        $html = view('document.factureDevis', array_merge(
            ['devis' => $devis->fresh(), 'image' => config('constantes.logo'), 'config' => $config],
            FneService::getDonneesFneDevis($devis, $devis->client)
        ))->render();

        $ligne = $this->ligneTransport($html);
        $this->assertStringContainsString('TVA (' . $config->tva . '%)', $ligne);
        $this->assertAnciennesLignesAbsentes($html);
    }

    public function test_la_page_commande_validee_porte_le_transport_en_ligne_taxee(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client.user')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }
        $commande->cout_livraison_client = 4000;
        $commande->tva_transport = 720;
        $commande->save();

        Auth::guard('web')->login($commande->client->user);
        $html = $this->get(route('client.commandeValidee', $commande->numero))->assertOk()->getContent();

        $ligne = $this->ligneTransport($html);
        $this->assertStringContainsString('TVA (' . Configuration::first()->tva . '%)', $ligne);
        $this->assertAnciennesLignesAbsentes($html);
    }

    public function test_la_facture_normalisee_declare_la_taxe_reellement_facturee(): void
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->first();
        $location = Location::whereHas('detailLocation')->whereHas('client')->first();
        if (!$commande || !$location) {
            $this->markTestSkipped('Il faut une commande et une location avec des lignes.');
        }
        $codeTva  = config('fne.defaults.tax', 'TVA');
        $codeZero = config('fne.defaults.delivery_tax', 'TVAC');
        $transport = fn (array $payload) => collect($payload['items'])->firstWhere('reference', 'LIVRAISON');

        $facture = Facture::create([
            'numero' => 'T' . substr((string) time(), -8), 'user_id' => User::value('id'),
            'client_id' => $commande->client_id, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
            'montant' => 1, 'statut' => 2, 'fne_status' => 'pending',
            'cout_livraison_applique' => 4000, 'tva_transport_applique' => 720,
        ]);
        $this->assertSame([$codeTva], $transport(FneService::buildSalePayload($facture))['taxes'], 'Vente taxée : la DGI doit recevoir le code TVA.');

        $facture->tva_transport_applique = 0;
        $facture->save();
        $this->assertSame([$codeZero], $transport(FneService::buildSalePayload($facture->fresh()))['taxes'], 'Vente non taxée : code à 0 %.');

        $location->cout_livraison_client = 4000;
        $location->save();
        $factureLoc = Facture::create([
            'numero' => 'L' . substr((string) time(), -8), 'user_id' => User::value('id'),
            'client_id' => $location->client_id, 'service' => \Help::$LOCATION, 'service_id' => $location->id,
            'montant' => 1, 'statut' => 2, 'fne_status' => 'pending',
            'cout_livraison_applique' => 4000, 'tva_transport_applique' => 720,
        ]);
        $this->assertSame([$codeTva], $transport(FneService::buildLocationPayload($factureLoc))['taxes'], 'Location taxée : code TVA.');
    }
}
