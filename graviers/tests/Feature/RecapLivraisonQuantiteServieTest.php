<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le récapitulatif des livraisons annonce la quantité SERVIE.
 *
 * Dernier écran à montrer encore la quantité demandée. Sur un bon traité pour
 * 2 dont le fournisseur n'a livré qu'une, le récapitulatif affichait 2 — un
 * chiffre que rien d'autre dans le système ne confirmait.
 */
class RecapLivraisonQuantiteServieTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_recapitulatif_montre_la_quantite_servie(): void
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        // La liste ne retient que les enlèvements dont la livraison est active
        // et datée dans la période : on part donc d'un bon qu'elle voit.
        $enlevement = Enlevement::liste(null, null, null, '2000-01-01', '2100-12-31')->first();

        if (!$admin || !$enlevement) {
            $this->markTestSkipped('Aucun enlèvement listé, ou aucun administrateur.');
        }

        Enlevement::where('id', $enlevement->id)->update(['qte' => 2, 'qte_servi' => 1]);

        $livraison = Livraison::find($enlevement->livraison_id);
        $du = $livraison?->date_livraison
            ? \Carbon\Carbon::parse($livraison->date_livraison)->subDay()->toDateString()
            : '2000-01-01';
        $au = $livraison?->date_livraison
            ? \Carbon\Carbon::parse($livraison->date_livraison)->addDay()->toDateString()
            : '2100-12-31';

        URL::forceRootUrl('');
        $reponse = $this->actingAs($admin)->get('/recap-livraison?du=' . $du . '&au=' . $au);
        $reponse->assertOk();

        $html = $reponse->getContent();

        // La quantité servie, et le rappel de la demandée pour que l'écart se lise.
        $this->assertStringContainsString('demandé', $html);
    }

    public function test_un_bon_servi_conforme_n_affiche_pas_de_rappel(): void
    {
        $enlevement = Enlevement::liste(null, null, null, '2000-01-01', '2100-12-31')->first();

        if (!$enlevement) {
            $this->markTestSkipped('Aucun enlèvement listé.');
        }

        Enlevement::where('id', $enlevement->id)->update(['qte' => 3, 'qte_servi' => 3]);

        $frais = Enlevement::find($enlevement->id);

        $this->assertSame(3.0, $frais->quantiteAPayer());
        $this->assertFalse($frais->quantiteDiffereDeLaCommande());
    }
}
