<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Facture;
use App\Models\TvaCommande;
use App\Services\FacturationCommande;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA TVA NON FACTURÉE D'UN CLIENT DISPENSÉ (lot 81, 15/09/2026).
 *
 * Demande du client : plus de « N° bon de commande client » en en-tête de la
 * facture FNE ; à sa place, pour un client dont la TVA est retirée (TVA = 0),
 * la mention « TVA NON FACTUREE : montant » — le montant que la TVA aurait
 * atteint —, portée aussi dans « Autres mentions » de la facture normalisée.
 *
 * Et la facture d'un tel client ne doit plus imprimer « TVA (18%) » sur des
 * lignes jamais taxées : le taux du document est celui de l'affaire figée.
 */
class TvaNonFactureeSurLaFactureTest extends TestCase
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

    private function uneFacture(Commande $commande): Facture
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

    /** Fige la TVA de la commande à un montant donné (0 = client dispensé). */
    private function figerLaTva(Commande $commande, float $montant): void
    {
        $ligne = TvaCommande::where('commande_id', $commande->id)->first();
        if ($ligne) {
            $ligne->montant = $montant;
            $ligne->save();
        } else {
            TvaCommande::create([
                'client_id'    => $commande->client_id,
                'commande_id'  => $commande->id,
                'montant'      => $montant,
                'type_affaire' => \Help::$VENTE,
            ]);
        }
        $commande->unsetRelation('TvaCommande');
    }

    public function test_le_calcul_de_la_tva_non_facturee(): void
    {
        $taux = (float) (Configuration::first()->tva ?? 0);
        if ($taux <= 0) {
            $this->markTestSkipped('Le paramétrage ne taxe pas.');
        }

        // Affaire taxée : rien à mentionner.
        $this->assertSame(0.0, \Help::tvaNonFacturee(100000, $taux));
        $this->assertSame('', \Help::mentionTvaNonFacturee(0));

        // Affaire sans TVA : le montant au taux du paramétrage, sur la base nette.
        $attendu = \Help::arrondiFranc(100000 * $taux / 100);
        $this->assertEqualsWithDelta($attendu, \Help::tvaNonFacturee(100000, 0), 0.01);
        $this->assertSame(
            'TVA NON FACTUREE : ' . number_format($attendu, 0, '', ' ') . ' FCFA',
            \Help::mentionTvaNonFacturee($attendu)
        );
        $this->assertSame(number_format($attendu, 0, '', ' ') . ' FCFA', \Help::montantTvaNonFacturee($attendu));
    }

    public function test_le_taux_du_document_est_celui_de_l_affaire(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $taux     = (float) (Configuration::first()->tva ?? 0);

        $this->figerLaTva($commande, 0);
        $this->assertSame(0.0, \Help::tauxTvaAffaire(Commande::find($commande->id)));

        $this->figerLaTva($commande, 1000);
        $this->assertEqualsWithDelta($taux, \Help::tauxTvaAffaire(Commande::find($commande->id)), 0.0001);

        // Sans affaire : le paramétrage.
        $this->assertEqualsWithDelta($taux, \Help::tauxTvaAffaire(null), 0.0001);
    }

    public function test_la_facture_d_un_client_dispense_porte_la_mention_et_pas_le_bon_de_commande(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $taux     = (float) (Configuration::first()->tva ?? 0);
        if ($taux <= 0) {
            $this->markTestSkipped('Le paramétrage ne taxe pas.');
        }

        $this->figerLaTva($commande, 0);
        $facture = $this->uneFacture($commande);
        $html    = view('document.factureCommande', FacturationCommande::donneesDocument($facture, Commande::find($commande->id)))->render();

        $base    = max(0, $commande->montantHT() - (float) ($commande->remise ?? 0));
        $attendu = \Help::tvaNonFacturee($base, 0, (float) ($commande->cout_livraison_client ?? 0), (float) ($commande->tva_transport ?? 0));

        $this->assertStringContainsString('TVA NON FACTUREE : <strong>' . number_format($attendu, 0, '', ' ') . ' FCFA</strong>', $html);
        $this->assertStringNotContainsString('N° bon de commande client', $html);
        $this->assertStringContainsString('TVA (0%)', $html, 'Les lignes d\'un client dispensé ne sont pas à 18 %.');
        $this->assertStringNotContainsString('TVA (' . $taux . '%)', $html);
        $this->assertStringContainsString('TVA exo.lég', $html, 'Le résumé fiscal dit l\'exonération.');

        // La charge utile DGI : lignes au code d'exonération, mention dans « Autres mentions ».
        $charge = FneService::buildSalePayload($facture->fresh());
        $this->assertSame([config('fne.defaults.exempt_tax', 'TVAD')], $charge['items'][0]['taxes']);
        $this->assertStringContainsString('TVA NON FACTUREE : ' . number_format($attendu, 0, '', ' ') . ' FCFA', $charge['commercialMessage']);
    }

    public function test_la_facture_d_un_client_taxe_ne_porte_pas_la_mention(): void
    {
        $commande = $this->uneCommandeAvecLignes();
        $taux     = (float) (Configuration::first()->tva ?? 0);
        if ($taux <= 0) {
            $this->markTestSkipped('Le paramétrage ne taxe pas.');
        }

        $this->figerLaTva($commande, \Help::arrondiFranc($commande->montantHT() * $taux / 100));
        $facture = $this->uneFacture($commande);
        $html    = view('document.factureCommande', FacturationCommande::donneesDocument($facture, Commande::find($commande->id)))->render();

        $this->assertStringNotContainsString('TVA NON FACTUREE', $html);
        $this->assertStringContainsString('TVA (' . $taux . '%)', $html);

        $charge = FneService::buildSalePayload($facture->fresh());
        $this->assertSame([config('fne.defaults.tax', 'TVA')], $charge['items'][0]['taxes']);
        $this->assertStringNotContainsString('TVA NON FACTUREE', $charge['commercialMessage']);
    }
}
