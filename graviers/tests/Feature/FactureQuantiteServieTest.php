<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La facture porte la quantité réellement servie.
 *
 * Son MONTANT était déjà calculé sur `qte_servi`
 * (OrdersController::creerFacturePourEnlevements), mais le document affichait
 * la quantité DEMANDÉE et recalculait la ligne dessus : un bon servi
 * partiellement produisait un détail plus élevé que le total à payer inscrit
 * juste en dessous.
 */
class FactureQuantiteServieTest extends TestCase
{
    public function test_la_ligne_de_facture_suit_la_quantite_servie(): void
    {
        $enlevement = Enlevement::whereHas('livraison.detailCommande')->first();

        if (!$enlevement) {
            $this->markTestSkipped('Aucun enlèvement rattaché à une ligne de commande.');
        }

        $prix = (float) $enlevement->livraison->detailCommande->prix;

        if ($prix <= 0) {
            $this->markTestSkipped('La ligne de commande n\'a pas de prix.');
        }

        // La quantité est forcée le temps du rendu : la transaction est
        // annulée, la base retrouve son état d'origine.
        DB::beginTransaction();

        try {
            $enlevement->update(['qte' => 2, 'qte_servi' => 1]);

            $html = view('document.factureCommande', [
                'commande'    => $enlevement->livraison->detailCommande->commande,
                'image'       => config('constantes.logo'),
                'enlevements' => Enlevement::where('id', $enlevement->id)->get(),
                'facture'     => null,
                'config'      => \App\Models\Configuration::first(),
                'livraison'   => 1,
            ])->render();

            // Le montant de la ligne : une fois le prix, pas deux.
            $this->assertStringContainsString(
                number_format($prix, 0, '', ' '),
                $html
            );
            $this->assertStringNotContainsString(
                '<td class="col-montant">' . number_format(2 * $prix, 0, '', ' ') . '</td>',
                $html,
                'La ligne facture encore la quantité demandée.'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_le_document_n_utilise_plus_la_quantite_demandee(): void
    {
        $source = file_get_contents(resource_path('views/document/factureCommande.blade.php'));

        $this->assertStringContainsString('$env->quantiteAPayer()', $source);
        $this->assertStringNotContainsString('$env->qte * $prixUnitaire', $source);
    }
}
