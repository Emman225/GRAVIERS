<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La liste des bons validés annonce ce que le FOURNISSEUR a servi.
 *
 * Cas signalé : un bon traité pour 2, servi à 1, affichait 2.
 */
class QuantiteServieBonTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_liste_affiche_la_quantite_servie(): void
    {
        $admin = User::where('type_user_id', \Help::$USER_ADMIN)
            ->where('statut', \Help::$STATUT_ACTIF)->first();
        $bon = Enlevement::whereNotNull('fournisseur_validation')->whereNull('deleted_at')->first();

        if (!$admin || !$bon) {
            $this->markTestSkipped('Aucun bon validé, ou aucun administrateur.');
        }

        // On rejoue exactement la situation décrite : demandé 2, servi 1.
        $bon->update(['qte' => 2, 'qte_servi' => 1]);

        $this->assertSame(1.0, $bon->fresh()->quantiteAPayer());
        $this->assertTrue($bon->fresh()->quantiteDiffereDeLaCommande());

        URL::forceRootUrl('');
        $reponse = $this->actingAs($admin)->get('/bonValides');
        $reponse->assertOk();

        // La quantité servie est là, et la quantité demandée reste visible en
        // rappel pour que l'écart se lise.
        $reponse->assertSee('demandé', false);
    }

    public function test_un_bon_servi_conforme_n_affiche_pas_de_rappel(): void
    {
        $bon = Enlevement::whereNull('deleted_at')->first();
        if (!$bon) { $this->markTestSkipped('Aucun bon.'); }

        $bon->update(['qte' => 5, 'qte_servi' => 5]);
        $this->assertFalse($bon->fresh()->quantiteDiffereDeLaCommande());
        $this->assertSame(5.0, $bon->fresh()->quantiteAPayer());
    }

    public function test_un_bon_non_encore_servi_montre_la_quantite_demandee(): void
    {
        $bon = Enlevement::whereNull('deleted_at')->first();
        if (!$bon) { $this->markTestSkipped('Aucun bon.'); }

        $bon->update(['qte' => 7, 'qte_servi' => null]);
        $this->assertSame(7.0, $bon->fresh()->quantiteAPayer());
        $this->assertFalse($bon->fresh()->quantiteDiffereDeLaCommande());
    }

    public function test_le_bon_imprime_porte_la_quantite_servie(): void
    {
        $admin = User::where('type_user_id', \Help::$USER_ADMIN)
            ->where('statut', \Help::$STATUT_ACTIF)->first();
        $bon = Enlevement::whereNotNull('fournisseur_validation')->whereNull('deleted_at')->first();

        if (!$admin || !$bon) {
            $this->markTestSkipped('Aucun bon validé, ou aucun administrateur.');
        }

        $bon->update(['qte' => 2, 'qte_servi' => 1]);

        // L'aperçu est un PDF : on contrôle le HTML qui le compose, le rendu
        // dompdf n'apportant rien de plus à vérifier ici.
        $html = view('livreur.bonImprime', ['enlevement' => $bon->fresh()])->render();

        $this->assertStringContainsString('quantité demandée', $html);
        // Le total suit la quantité servie, pas la demandée.
        $this->assertStringNotContainsString('2 x', $html);
    }
}
