<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L'écran de détail d'une commande compte ce qui a été SERVI.
 *
 * « Qté livrée » additionnait la quantité des livraisons — celle qui a été
 * DEMANDÉE. Quand le fournisseur sert moins, seul l'enlèvement porte la
 * quantité servie : la ligne s'affichait donc « Livrée totale » avec un
 * reste à traiter nul, alors qu'il manquait de la marchandise.
 */
class DetailsCommandeQuantiteServieTest extends TestCase
{
    public function test_la_quantite_livree_est_celle_servie_par_le_fournisseur(): void
    {
        $enlevement = Enlevement::whereHas('livraison.detailCommande.commande')->first();
        $admin = User::where('type_user_id', \Help::$USER_ADMIN)
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$enlevement || !$admin) {
            $this->markTestSkipped('Aucun enlèvement rattaché à une commande, ou aucun administrateur.');
        }

        $livraison = $enlevement->livraison;
        $detail    = $livraison->detailCommande;
        $commande  = $detail->commande;

        DB::beginTransaction();

        try {
            // Deux tonnes commandées, la livraison est faite, mais le
            // fournisseur n'en a servi qu'une.
            $detail->update(['qte' => 2, 'qte_livree' => 2]);
            $livraison->update(['qte' => 2, 'etat_livraison' => \Help::$LIVRAISON_LIVREE]);
            $enlevement->update(['qte' => 2, 'qte_servi' => 1]);

            $reponse = $this->actingAs($admin)->get('/orders-details/' . $commande->numero);
            $reponse->assertOk();

            $html = $reponse->getContent();

            // La ligne ne peut pas être annoncée comme entièrement livrée.
            $this->assertStringNotContainsString('Livrée totale', $html);
            $this->assertStringContainsString('Livraison partielle', $html);
        } finally {
            DB::rollBack();
        }
    }
}
