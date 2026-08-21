<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Livraison;
use Help;

/**
 * Rattrapage des lignes de commande livrées mais restées « EN COURS LIVRAISON ».
 *
 * Quand le livreur clôture sa course depuis l'APPLICATION MOBILE, l'API mettait
 * à jour la quantité livrée de la ligne et l'état de la commande, mais pas
 * l'état de la LIGNE elle-même. L'écran de validation du site, lui, le faisait.
 *
 * Conséquence visible : la page « Retour de produit » du client ne liste que les
 * lignes en LIVREE. Une commande livrée par un livreur via le mobile n'y
 * apparaissait donc jamais et le client ne pouvait pas demander de retour ;
 * seules les commandes retirées sur place y figuraient.
 *
 * Le correctif empêche que cela se reproduise ; cette commande répare ce qui a
 * déjà été écrit.
 *
 * Règle appliquée, exactement celle du correctif : une ligne passe à LIVREE
 * quand la quantité livrée couvre la quantité commandée. La quantité livrée est
 * recalculée depuis les livraisons réellement marquées LIVREE, et non depuis la
 * colonne qte_livree, qui peut elle-même être en retard.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan commande:rattraper-etat-lignes
 *   php artisan commande:rattraper-etat-lignes --apply
 */
class RattraperEtatLignesCommande extends Command
{
    protected $signature = 'commande:rattraper-etat-lignes {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Passe à LIVREE les lignes de commande entièrement livrées restées dans un autre état.";

    public function handle()
    {
        $apply  = (bool) $this->option('apply');
        $LIVREE = Help::$LIVRAISON_LIVREE;

        // Quantité réellement livrée par ligne, d'après les livraisons closes.
        $livreeParLigne = Livraison::where('etat_livraison', $LIVREE)
            ->whereNotNull('detail_commande_id')
            ->groupBy('detail_commande_id')
            ->selectRaw('detail_commande_id, SUM(qte) AS qte_livree')
            ->pluck('qte_livree', 'detail_commande_id');

        if ($livreeParLigne->isEmpty()) {
            $this->info('Aucune livraison close : rien à rattraper.');
            return self::SUCCESS;
        }

        $lignes = DetailCommande::whereIn('id', $livreeParLigne->keys())
            ->where('etat_livraison', '!=', $LIVREE)
            ->get();

        $aCorriger = [];

        foreach ($lignes as $ligne) {
            $livree = (float) $livreeParLigne[$ligne->id];

            if ($livree < (float) $ligne->qte) {
                continue; // Livraison partielle : la ligne reste en cours.
            }

            $commande = Commande::find($ligne->commande_id);

            $aCorriger[] = [
                'id'       => $ligne->id,
                'commande' => $commande?->numero ?? '?',
                'produit'  => $ligne->produit?->nom ?? '?',
                'avant'    => $ligne->etat_livraison,
                'commande_qte' => (float) $ligne->qte,
                'livree'   => $livree,
                // qte_livree peut être restée à 0 sur les commandes anciennes :
                // on la réaligne en même temps, sinon la fiche commande continue
                // d'afficher « Non livrée ».
                'qte_livree_avant' => (float) ($ligne->qte_livree ?? 0),
            ];
        }

        if (empty($aCorriger)) {
            $this->info('Aucune ligne de commande à rattraper : tout est cohérent.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Lignes entièrement livrées mais non marquées LIVREE :');
        $this->table(
            ['Ligne', 'Commande', 'Produit', 'État actuel', 'Qté commandée', 'Qté livrée', 'qte_livree en base'],
            array_map(fn ($l) => [
                $l['id'], $l['commande'], $l['produit'], $l['avant'],
                $l['commande_qte'], $l['livree'], $l['qte_livree_avant'],
            ], $aCorriger)
        );

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aCorriger, $LIVREE) {
            foreach ($aCorriger as $l) {
                DetailCommande::where('id', $l['id'])->update([
                    'etat_livraison' => $LIVREE,
                    'qte_livree'     => min($l['commande_qte'], $l['livree']),
                ]);
            }
        });

        $this->newLine();
        $this->info(sprintf('Appliqué : %d ligne(s) passée(s) à LIVREE.', count($aCorriger)));
        $this->line('Les produits concernés apparaissent désormais dans « Retour de produit » chez le client.');

        return self::SUCCESS;
    }
}
