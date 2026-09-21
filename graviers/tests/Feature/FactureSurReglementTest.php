<?php

namespace Tests\Feature;

use App\Models\BlClient;
use App\Models\Commande;
use App\Models\Facture;
use App\Services\FacturationCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA FACTURE ENVOYÉE PAR COURRIEL N'EST PLUS À ZÉRO.
 *
 * Point 8 du 07/09/2026. Une facture établie sur règlement (le client a payé,
 * rien n'est encore enlevé) n'a aucun enlèvement rattaché : le gabarit, qui
 * n'imprimait que les enlèvements, sortait un tableau vide et tous les totaux
 * à 0. Les lignes viennent désormais de la commande, et l'écran comme le
 * courriel passent par le même jeu de données.
 *
 * Point 16 : le numéro de bon de commande du client figure sur la facture.
 */
class FactureSurReglementTest extends TestCase
{
    use DatabaseTransactions;

    private function uneCommandeAvecLignes(): Commande
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);

        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes en base.');
        }

        return $commande;
    }

    private function uneFactureSurReglement(Commande $commande): Facture
    {
        return Facture::create([
            'numero'                  => 'T' . substr((string) time(), -8),
            'user_id'                 => $commande->client->user_id ?? null,
            'client_id'               => $commande->client_id,
            'service'                 => \Help::$COMMANDE,
            'service_id'              => $commande->id,
            'montant'                 => round($commande->montantAPayer()),
            'remise_appliquee'        => (float) ($commande->remise ?? 0),
            'cout_livraison_applique' => (float) ($commande->cout_livraison_client ?? 0),
            'statut'                  => 2,
            'fne_status'              => 'pending',
        ]);
    }

    public function test_une_facture_sans_enlevement_imprime_les_lignes_de_la_commande(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $facture  = $this->uneFactureSurReglement($commande);

        $donnees = FacturationCommande::donneesDocument($facture, $commande);

        $this->assertTrue($donnees['enlevements']->isEmpty(), 'Le témoin : pas d\'enlèvement sur cette facture.');
        $this->assertCount($commande->detailCommande->count(), $donnees['lignesReglement']);
        $this->assertSame(1, $donnees['livraison'], 'Première facture : elle porte remise et livraison.');

        $html = view('document.factureCommande', $donnees)->render();

        $produit = $commande->detailCommande->first()->produit->nom;
        $this->assertStringContainsString(\Help::phrase($produit), $html);

        $totalHt = number_format(round($commande->montantHT()), 0, '', ' ');
        $this->assertStringContainsString($totalHt, $html,
            "Le TOTAL HT ($totalHt) n'apparaît pas : la facture sortirait à 0.");
    }

    public function test_une_facture_partielle_imprime_les_lignes_au_prorata(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $facture  = $this->uneFactureSurReglement($commande);
        $facture->montant = round($commande->montantAPayer() / 2);
        $facture->save();

        $donnees = FacturationCommande::donneesDocument($facture, $commande);

        $premiere = $commande->detailCommande->first();
        $this->assertEqualsWithDelta((float) $premiere->qte / 2, $donnees['lignesReglement'][0]['qte'], 0.011);
    }

    public function test_le_numero_de_bon_de_commande_du_client_figure_sur_la_facture(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $facture  = $this->uneFactureSurReglement($commande);

        BlClient::where('commande_id', $commande->id)->delete();
        BlClient::create([
            'numero'      => 'BC-TEST-4471',
            'client_id'   => $commande->client_id,
            'fichier'     => 'lesBons/test.png',
            'commande_id' => $commande->id,
        ]);
        $commande->unsetRelation('blClient');

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, $commande))->render();

        // 15/09/2026 : la mention d'en-tête a disparu ; le bon est en colonne Réf.
        $this->assertStringNotContainsString('N° bon de commande client', $html);
        $this->assertStringContainsString('01 - BC-TEST-4471', $html);
    }
}
