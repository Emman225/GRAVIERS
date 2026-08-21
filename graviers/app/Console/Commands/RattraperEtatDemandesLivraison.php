<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use Help;

/**
 * Rattrapage des demandes de livraison restées bloquées « EN TRAITEMENT ».
 *
 * L'API qui clôture une livraison aiguillait sur sa provenance et ne traitait
 * que COMMANDE et LOCATION : le cas LIVRAISON manquait. La livraison passait
 * donc bien à LIVREE, mais ni la ligne de la demande ni la demande elle-même
 * n'étaient mises à jour. Résultat : des demandes livrées restaient
 * indéfiniment « EN TRAITEMENT » chez le gestionnaire comme chez le client.
 *
 * Le correctif empêche que cela se reproduise ; cette commande répare ce qui
 * a déjà été écrit.
 *
 * Elle applique EXACTEMENT la règle du correctif : une demande est TERMINEE
 * quand la totalité des quantités demandées a été livrée.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan demande-livraison:rattraper-etat
 *   php artisan demande-livraison:rattraper-etat --apply
 */
class RattraperEtatDemandesLivraison extends Command
{
    protected $signature = 'demande-livraison:rattraper-etat {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Clôture les demandes de livraison dont toutes les quantités ont été livrées mais qui sont restées EN TRAITEMENT.";

    public function handle()
    {
        $apply  = (bool) $this->option('apply');
        $LIVREE = Help::$LIVRAISON_LIVREE;

        $demandes = DemandeLivraison::with('detailLivraison')->get();

        $lignesACorriger  = [];
        $demandesACloturer = [];

        foreach ($demandes as $demande) {
            $idsLignes = $demande->detailLivraison->pluck('id');

            if ($idsLignes->isEmpty()) {
                continue; // Demande sans marchandise : rien à clôturer.
            }

            // Lignes entièrement servies mais pas marquées LIVREE.
            foreach ($demande->detailLivraison as $ligne) {
                $livreeLigne = (float) Livraison::where('detail_livraison_id', $ligne->id)
                    ->where('etat_livraison', $LIVREE)
                    ->sum('qte');

                if ($livreeLigne >= (float) $ligne->qte && $ligne->etat_livraison !== $LIVREE) {
                    $lignesACorriger[] = [
                        'id'      => $ligne->id,
                        'demande' => $demande->numero,
                        'produit' => $ligne->nom_produit,
                        'avant'   => $ligne->etat_livraison,
                    ];
                }
            }

            // Demande entièrement servie mais pas TERMINEE.
            $qteADemander = (float) $demande->detailLivraison->sum('qte');
            $qteLivree    = (float) Livraison::whereIn('detail_livraison_id', $idsLignes)
                ->where('etat_livraison', $LIVREE)
                ->sum('qte');

            if ($qteLivree >= $qteADemander && $demande->etat_commande !== Help::$COMMANDE_TERMINE) {
                $demandesACloturer[] = [
                    'id'       => $demande->id,
                    'numero'   => $demande->numero,
                    'client'   => $demande->client?->display_name ?? '',
                    'avant'    => $demande->etat_commande,
                    'demande'  => $qteADemander,
                    'livree'   => $qteLivree,
                ];
            }
        }

        if (empty($lignesACorriger) && empty($demandesACloturer)) {
            $this->info('Aucune demande de livraison à rattraper : tout est cohérent.');
            return self::SUCCESS;
        }

        if (!empty($lignesACorriger)) {
            $this->newLine();
            $this->line('Lignes entièrement livrées mais non marquées LIVREE :');
            $this->table(
                ['Ligne', 'Demande', 'Produit', 'État actuel'],
                array_map(fn ($l) => [$l['id'], $l['demande'], $l['produit'], $l['avant']], $lignesACorriger)
            );
        }

        if (!empty($demandesACloturer)) {
            $this->newLine();
            $this->line('Demandes entièrement livrées mais non clôturées :');
            $this->table(
                ['ID', 'Numéro', 'Client', 'État actuel', 'Qté demandée', 'Qté livrée'],
                array_map(fn ($d) => [$d['id'], $d['numero'], $d['client'], $d['avant'], $d['demande'], $d['livree']], $demandesACloturer)
            );
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($lignesACorriger, $demandesACloturer, $LIVREE) {
            foreach ($lignesACorriger as $l) {
                DetailLivraison::where('id', $l['id'])->update(['etat_livraison' => $LIVREE]);
            }
            foreach ($demandesACloturer as $d) {
                DemandeLivraison::where('id', $d['id'])->update(['etat_commande' => Help::$COMMANDE_TERMINE]);
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Appliqué : %d ligne(s) passée(s) à LIVREE, %d demande(s) passée(s) à TERMINEE.',
            count($lignesACorriger),
            count($demandesACloturer)
        ));

        // Le gain du livreur est une autre affaire : il se rattrape avec
        // « php artisan livreur:recalc-cout », qui recrédite les soldes par
        // différence pour les livraisons déjà finalisées.
        $this->line('Pour la rémunération des livreurs, enchaîner avec : php artisan livreur:recalc-cout');

        return self::SUCCESS;
    }
}
