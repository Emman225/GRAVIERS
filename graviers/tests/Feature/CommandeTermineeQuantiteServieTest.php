<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Livraison;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Une commande entièrement servie quitte la file de traitement.
 *
 * Le passage à TERMINEE comparait la quantité commandée à la somme des
 * quantités des LIVRAISONS — la quantité DEMANDÉE. Dès qu'un fournisseur sert
 * moins que son bon, un reliquat est créé et cette somme dépasse la commande :
 * l'égalité devenait impossible et la commande restait indéfiniment dans
 * « Commandes en attente de traitement ».
 */
class CommandeTermineeQuantiteServieTest extends TestCase
{
    /**
     * Monte une commande de 4 tonnes servie en trois bons : 2 demandées pour 1
     * servie, puis le reliquat de 1, puis 2. Total demandé 5, total servi 4.
     *
     * @return array{0: Commande, 1: DetailCommande}
     */
    private function commandeServieAvecReliquat(): array
    {
        $modele = Livraison::with('detailCommande.commande', 'enlevement')
            ->whereHas('enlevement')
            ->whereHas('detailCommande.commande')
            ->first();

        if (!$modele) {
            $this->markTestSkipped('Aucune livraison rattachée à un bon et à une commande.');
        }

        $commande = $modele->detailCommande->commande;
        $ligne    = $modele->detailCommande;

        DetailCommande::where('commande_id', $commande->id)
            ->where('id', '!=', $ligne->id)->delete();
        $ligne->update(['qte' => 4, 'qte_livree' => 4]);

        Livraison::where('detail_commande_id', $ligne->id)
            ->where('id', '!=', $modele->id)->delete();

        $gabarit    = $modele->getAttributes();
        $gabaritBon = $modele->enlevement->getAttributes();
        unset($gabarit['id'], $gabaritBon['id']);

        $modele->update([
            'qte'            => 2,
            'etat_livraison' => \Help::$LIVRAISON_LIVREE,
            'livre_par'      => 2,
        ]);
        $modele->enlevement->update(['qte' => 2, 'qte_servi' => 1]);

        foreach ([[1, 1], [2, 2]] as $i => [$demandee, $servie]) {
            $livraison = Livraison::create(array_merge($gabarit, [
                'numero'             => 'TEST' . $i . uniqid(),
                'qte'                => $demandee,
                'etat_livraison'     => \Help::$LIVRAISON_LIVREE,
                'livre_par'          => 2,
                'detail_commande_id' => $ligne->id,
            ]));

            Enlevement::create(array_merge($gabaritBon, [
                'livraison_id' => $livraison->id,
                'qte'          => $demandee,
                'qte_servi'    => $servie,
                'code_enleve'  => 'TEST' . $i . uniqid(),
            ]));
        }

        $commande->update(['etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT]);

        return [$commande->fresh(), $ligne];
    }

    public function test_le_rattrapage_sort_la_commande_de_la_file(): void
    {
        DB::beginTransaction();

        try {
            [$commande] = $this->commandeServieAvecReliquat();

            // L'ancien calcul : la somme des quantités demandées vaut 5 pour
            // une commande de 4. C'est bien lui qui bloquait.
            $demandee = (float) Livraison::whereIn(
                'detail_commande_id',
                DetailCommande::where('commande_id', $commande->id)->pluck('id')
            )->sum('qte');
            $this->assertSame(5.0, $demandee);

            Artisan::call('commande:rattraper-etat-commandes', ['--apply' => true]);

            $this->assertSame(
                \Help::$COMMANDE_TERMINE,
                $commande->fresh()->etat_commande,
                "La commande entièrement servie doit quitter la file de traitement."
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_le_rattrapage_epargne_une_commande_pas_entierement_servie(): void
    {
        DB::beginTransaction();

        try {
            [$commande, $ligne] = $this->commandeServieAvecReliquat();

            // Il manque une tonne : la commande n'est pas terminée.
            $ligne->update(['qte' => 5]);

            Artisan::call('commande:rattraper-etat-commandes', ['--apply' => true]);

            $this->assertSame(
                \Help::$COMMANDE_EN_TRAITEMENT,
                $commande->fresh()->etat_commande,
                "Une commande incomplète ne doit pas être close."
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_le_rattrapage_epargne_une_commande_dont_une_course_est_en_route(): void
    {
        DB::beginTransaction();

        try {
            [$commande] = $this->commandeServieAvecReliquat();

            // Une livraison encore en cours : la marchandise n'est pas arrivée.
            Livraison::whereIn(
                'detail_commande_id',
                DetailCommande::where('commande_id', $commande->id)->pluck('id')
            )->limit(1)->update(['etat_livraison' => \Help::$LIVRAISON_EN_COURS]);

            Artisan::call('commande:rattraper-etat-commandes', ['--apply' => true]);

            $this->assertSame(
                \Help::$COMMANDE_EN_TRAITEMENT,
                $commande->fresh()->etat_commande,
                "Une commande dont une course est en route ne doit pas être close."
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_le_controleur_ne_compte_plus_les_quantites_demandees(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SellerController.php'));

        $this->assertStringContainsString('$qteServie', $source);
        $this->assertStringContainsString('quantiteAPayer()', $source);
        $this->assertStringNotContainsString('$totalQteALivrer == $qteLivree', $source);
    }
}
