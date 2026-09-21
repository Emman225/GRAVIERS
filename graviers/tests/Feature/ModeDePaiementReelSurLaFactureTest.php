<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Services\FacturationCommande;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE MODE DE PAIEMENT RÉEL SUR LA FACTURE (lot 86, 15/09/2026) : celui des
 * règlements validés (agence, avance imputée avec le mode du dépôt), plus « N/A ».
 */
class ModeDePaiementReelSurLaFactureTest extends TestCase
{
    use DatabaseTransactions;

    private function uneCommande(): Commande
    {
        $commande = Commande::whereHas('detailCommande')->whereHas('client')->get()
            ->first(fn (Commande $c) => $c->montantHT() > 0 && $c->detailCommande->first()?->produit);
        if (!$commande) {
            $this->markTestSkipped('Aucune commande avec des lignes.');
        }
        Paiement::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->delete();

        return $commande;
    }

    private function unReglement(Commande $commande, ModePaiement $mode, ?string $moyen = null, ?string $reference = null): Paiement
    {
        $p = Paiement::create([
            'client_id' => $commande->client_id, 'code' => 'T' . substr((string) (microtime(true) * 1000), -9),
            'libelle' => 'Règlement (recette)', 'montant_total' => 1000, 'montant_restant' => 0, 'statut' => 1,
            'service' => \Help::$COMMANDE, 'service_id' => $commande->id, 'agence_id' => 1, 'caissier_id' => 1,
        ]);
        LignePaiement::create([
            'paiement_id' => $p->id, 'mode_paiement_id' => $mode->id, 'moyen_paiement' => $moyen ?? $mode->libelle,
            'reference' => $reference, 'date_paiement' => now(), 'montant' => 1000, 'statut' => 1,
            'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
        ]);

        return $p;
    }

    public function test_sans_reglement_le_repli_puis_na(): void
    {
        $commande = $this->uneCommande();
        $this->assertSame('Chèque', \Help::modePaiementDeLAffaire(\Help::$COMMANDE, $commande->id, 'Chèque'));
        $this->assertSame('N/A', \Help::modePaiementDeLAffaire(\Help::$COMMANDE, $commande->id, null));
        $this->assertSame('N/A', \Help::modePaiementDeLAffaire(\Help::$COMMANDE, 0, ''));
    }

    public function test_les_modes_des_reglements_valides_et_l_avance_avec_le_mode_du_depot(): void
    {
        $commande = $this->uneCommande();
        $modes = ModePaiement::where('statut', 1)->orderBy('id')->take(2)->get();
        if ($modes->count() < 2) {
            $this->markTestSkipped('Il faut deux modes de paiement.');
        }
        [$espece, $autre] = [$modes[0], $modes[1]];

        $this->unReglement($commande, $espece);
        $this->assertSame($espece->libelle, \Help::modePaiementDeLAffaire(\Help::$COMMANDE, $commande->id, 'Devis'));

        // Une avance imputée : le mode du dépôt, avec l'origine.
        $this->unReglement($commande, $autre, 'Avance client (reçu AV-2026-007)', 'AV-2026-007');
        $texte = \Help::modePaiementDeLAffaire(\Help::$COMMANDE, $commande->id);
        $this->assertSame($espece->libelle . ', ' . $autre->libelle . ' (avance AV-2026-007)', $texte);

        // Un règlement non validé ne compte pas.
        Paiement::where('service', \Help::$COMMANDE)->where('service_id', $commande->id)->update(['statut' => 0]);
        $this->assertSame('N/A', \Help::modePaiementDeLAffaire(\Help::$COMMANDE, $commande->id));
    }

    public function test_la_facture_et_la_dgi_disent_le_mode_reel(): void
    {
        $commande = $this->uneCommande();
        $espece = ModePaiement::where('libelle', 'like', '%sp_ce%')->first() ?? ModePaiement::where('statut', 1)->first();
        if (!$espece) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }
        $this->unReglement($commande, $espece);
        $facture = Facture::create([
            'numero' => 'T' . substr((string) time(), -8), 'user_id' => $commande->client->user_id ?? null,
            'client_id' => $commande->client_id, 'service' => \Help::$COMMANDE, 'service_id' => $commande->id,
            'montant' => round($commande->montantAPayer()), 'statut' => 2, 'fne_status' => 'pending',
        ]);

        $html = view('document.factureCommande', FacturationCommande::donneesDocument($facture, Commande::find($commande->id)))->render();
        $this->assertStringContainsString('Mode de paiement : ' . e($espece->libelle), $html);
        $this->assertStringNotContainsString('Mode de paiement : N/A', $html);

        $charge = FneService::buildSalePayload($facture->fresh());
        $this->assertSame(FneService::mapPaymentMethod($espece->libelle), $charge['paymentMethod']);
    }
}
