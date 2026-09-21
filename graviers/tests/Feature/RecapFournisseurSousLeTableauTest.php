<?php

namespace Tests\Feature;

use App\Models\Fournisseur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PAGES DU FOURNISSEUR (09/09/2026) : le récapitulatif par produits s'affiche
 * SOUS le tableau des bons, sur toute la largeur — sur « Bons d'enlèvement en
 * attente » (/seller/bon) et sur « Bons d'enlèvement validés » (/seller/accepte),
 * où il compte les bons validés et la quantité réellement servie.
 *
 * Et sur /commande-en-adresse, le champ « Région » apparaît grisé : le grisé est
 * posé sur l'habillage Select2, pas seulement sur le <select> natif qu'il masque.
 */
class RecapFournisseurSousLeTableauTest extends TestCase
{
    use DatabaseTransactions;

    private function assertRecapSousLeTableau(string $html, string $titreRecap): void
    {
        $tableau = strpos($html, 'id="table"');
        $recap   = strpos($html, $titreRecap);
        $this->assertNotFalse($tableau, 'Le tableau des bons est absent.');
        $this->assertNotFalse($recap, "Le récapitulatif « {$titreRecap} » est absent.");
        $this->assertGreaterThan($tableau, $recap, 'Le récapitulatif doit venir APRÈS le tableau des bons.');

        // Le récapitulatif occupe une ligne à part, pleine largeur : plus de colonne de droite.
        $this->assertStringNotContainsString('col-lg-3 bloquerTopRem4', substr($html, $recap - 600, 600));
        $this->assertStringContainsString('id="recapEnlevements"', $html);
    }

    public function test_sur_les_bons_en_attente_le_recap_est_sous_le_tableau(): void
    {
        $fournisseur = Fournisseur::whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec un compte.');
        }
        Auth::guard('web')->login($fournisseur->user);
        $html = $this->get('/seller/bon')->assertOk()->getContent();
        $this->assertRecapSousLeTableau($html, 'Récap des enlèvements en attente par produits');
    }

    public function test_sur_les_bons_valides_le_recap_est_sous_le_tableau_et_compte_les_valides(): void
    {
        $fournisseur = Fournisseur::whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec un compte.');
        }
        Auth::guard('web')->login($fournisseur->user);
        $html = $this->get('/seller/accepte')->assertOk()->getContent();
        $this->assertRecapSousLeTableau($html, 'Récap des enlèvements validés par produits');
        $this->assertStringNotContainsString('Recap des enlevements en attente', $html);

        $source = file_get_contents(resource_path('views/fournisseur/accepte.blade.php'));
        $this->assertStringContainsString("\$items->sum('qte_servi')", $source, 'Le récap des bons validés doit compter la quantité servie.');
    }

    public function test_le_detail_d_un_bon_occupe_toute_la_largeur_avec_les_exports(): void
    {
        $fournisseur = Fournisseur::whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        $bon = $fournisseur ? \App\Models\Enlevement::where('fournisseur_id', $fournisseur->id)->whereNotNull('code_enleve')->first() : null;
        if (!$fournisseur || !$bon) {
            $this->markTestSkipped('Aucun bon pour un fournisseur avec un compte.');
        }
        Auth::guard('web')->login($fournisseur->user);
        $html = $this->get('/seller/bon/details/' . $bon->code_enleve)->assertOk()->getContent();

        // Les deux tableaux, chacun sur toute la largeur, avec leurs boutons d'export.
        $this->assertStringContainsString('id="tableProduitBon"', $html);
        $this->assertStringContainsString('id="tableLivreurBon"', $html);
        $this->assertStringNotContainsString('col-6 col-md-4', $html, 'Les colonnes d\'un tiers doivent avoir disparu.');
        $corps = substr($html, strpos($html, 'Enlevement :'));
        // Chaque composant d'export rend un bouton PDF (btn-danger) : un par tableau.
        $this->assertSame(2, substr_count($corps, 'btn btn-sm btn-danger'), 'Chaque tableau doit porter ses boutons d\'export.');
    }

    public function test_le_champ_region_est_grise_sur_l_habillage_select2(): void
    {
        $source = file_get_contents(resource_path('views/client/adresse.blade.php'));
        $this->assertStringContainsString("\$('#region').next('.select2-container').addClass('select2-fige');", $source);
        $this->assertStringContainsString('.select2-fige { pointer-events: none; }', $source);
        $this->assertStringContainsString("select2:opening', '#region'", $source);
    }
}
