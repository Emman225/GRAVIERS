<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\User;
use App\Services\PlanningFournisseur;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PLANNING LIVRAISON, HISTORIQUE, RÉCAP PRODUITS DU FOURNISSEUR (lot 83, 15/09/2026),
 * d'après le classeur Planning_Enlevements.xlsx du client.
 */
class PlanningFournisseurTest extends TestCase
{
    use DatabaseTransactions;

    private function unFournisseur(): array
    {
        $frn = Fournisseur::whereNotNull('user_id')->whereHas('produits')->first() ?? Fournisseur::whereNotNull('user_id')->first();
        $u = $frn ? User::find($frn->user_id) : null;
        if (!$frn || !$u) {
            $this->markTestSkipped('Aucun fournisseur rattaché à un compte.');
        }

        return [$frn, $u];
    }

    public function test_les_trois_pages_repondent_avec_leur_export(): void
    {
        [$frn, $u] = $this->unFournisseur();
        $this->actingAs($u);

        $this->get(route('sellers.planningLivraison'))->assertOk()
            ->assertSee('Planning livraison')->assertSee('TOTAL PÉRIODE')->assertSee('Nb bons')->assertSee('GravierExport.toExcel');
        $this->get(route('sellers.historiqueEnlevements') . '?debut=2026-09-01')->assertOk()
            ->assertSee('Historique des enlèvements')->assertSee('01/09/2026')->assertSee('01/10/2026');
        $this->get(route('sellers.recapProduits'))->assertOk()
            ->assertSee('Quantité totale à enlever')->assertSee('Reste à enlever')->assertSee('Nb bons prévus');

        // Le menu du fournisseur propose les trois entrées.
        $this->get(route('sellers.home'))->assertOk()
            ->assertSee('Planning livraison')->assertSee('Historique')->assertSee('Récap produits');
    }

    public function test_une_date_fausse_retombe_sur_aujourd_hui(): void
    {
        [$frn, $u] = $this->unFournisseur();
        $this->actingAs($u)->get(route('sellers.planningLivraison') . '?debut=n-importe-quoi')
            ->assertOk()->assertSee(now()->format('d/m/Y'));
    }

    public function test_la_grille_compte_les_bons_du_jour_et_de_la_periode(): void
    {
        // Un fournisseur qui a déjà servi des bons, de préférence.
        $frn = Enlevement::whereNotNull('qte_servi')->whereNotNull('fournisseur_id')->orderByDesc('id')->first()?->fournisseur
            ?? $this->unFournisseur()[0];
        $bons = PlanningFournisseur::bonsEnleves($frn);
        if ($bons->isEmpty()) {
            $this->markTestSkipped('Aucun bon servi pour ce fournisseur.');
        }

        $premier = $bons->first();
        $date    = PlanningFournisseur::dateDuBon($premier, true);
        $grille  = PlanningFournisseur::grille($frn, $bons, $date->copy()->subDays(3), true);

        $this->assertCount(PlanningFournisseur::JOURS, $grille['jours']);
        $jour = collect($grille['jours'])->first(fn ($j) => $j['date']->isSameDay($date));
        $attenduNb  = $bons->filter(fn ($b) => PlanningFournisseur::dateDuBon($b, true)?->isSameDay($date))->count();
        $attenduQte = $bons->filter(fn ($b) => PlanningFournisseur::dateDuBon($b, true)?->isSameDay($date))
            ->sum(fn ($b) => PlanningFournisseur::quantiteDuBon($b, true));
        $this->assertSame($attenduNb, $jour['nb']);
        $this->assertEqualsWithDelta($attenduQte, $jour['total'], 0.001);
        $this->assertEqualsWithDelta(array_sum($grille['totaux']), $grille['totalGeneral'], 0.001, 'La colonne Total quantités = somme des produits.');
        $this->assertSame(array_sum(array_column($grille['jours'], 'nb')), $grille['nbTotal']);
    }

    public function test_le_recap_additionne_enleve_prevu_et_stock(): void
    {
        [$frn] = $this->unFournisseur();
        foreach (PlanningFournisseur::recap($frn) as $l) {
            $this->assertEqualsWithDelta($l['stock'] + $l['prevu'], $l['reste'], 0.001);
            $this->assertEqualsWithDelta($l['reste'] + $l['enleve'], $l['total'], 0.001);
            if ($l['total'] > 0) {
                $this->assertEqualsWithDelta(round($l['enleve'] / $l['total'] * 100, 1), $l['pourcentage'], 0.001);
            }
        }
        $this->assertTrue(true);
    }
}
