<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Livraison;
use Help;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattrapage des commandes entièrement servies restées « EN TRAITEMENT ».
 *
 * À la validation d'un bon par le fournisseur, la commande passait à TERMINEE
 * quand la somme des quantités des LIVRAISONS égalait la quantité commandée.
 * Or cette quantité est celle qui a été DEMANDÉE : dès qu'un fournisseur sert
 * moins que son bon, un reliquat est créé, et la somme des demandes dépasse
 * alors la commande. Une commande de 4 tonnes servie en trois bons (2 demandées
 * pour 1 servie, puis 1, puis 2) totalisait 5 : l'égalité était fausse et la
 * commande restait dans la liste des commandes en attente de traitement, alors
 * que le client avait tout reçu.
 *
 * Le correctif empêche que cela se reproduise ; cette commande répare ce qui a
 * déjà été écrit.
 *
 * Règle appliquée, exactement celle du correctif : la commande est terminée
 * quand TOUTES ses livraisons sont LIVREE et que la quantité réellement SERVIE
 * couvre la quantité commandée. Exiger que toutes les livraisons soient LIVREE
 * interdit de clore une commande dont une course est encore en route.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan commande:rattraper-etat-commandes
 *   php artisan commande:rattraper-etat-commandes --apply
 */
class RattraperEtatCommandes extends Command
{
    protected $signature = 'commande:rattraper-etat-commandes {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Passe à TERMINEE les commandes entièrement servies restées EN TRAITEMENT.";

    public function handle()
    {
        $apply  = (bool) $this->option('apply');
        $LIVREE = Help::$LIVRAISON_LIVREE;

        $commandes = Commande::where('etat_commande', Help::$COMMANDE_EN_TRAITEMENT)->get();

        $aCorriger = [];

        foreach ($commandes as $commande) {
            $lignes = DetailCommande::where('commande_id', $commande->id)->get();

            if ($lignes->isEmpty()) {
                continue;
            }

            $livraisons = Livraison::with('enlevement')
                ->whereIn('detail_commande_id', $lignes->pluck('id'))
                ->get();

            // Aucune livraison : la commande n'a pas encore été traitée.
            if ($livraisons->isEmpty()) {
                continue;
            }

            // Les courses REFUSÉES sortent du calcul : elles n'ont rien
            // transporté et ne seront jamais livrées. Les garder condamnait la
            // commande à rester « en attente » — la commande de rattrapage ne
            // pouvait pas davantage la débloquer que l'écran.
            $livraisons = $livraisons->filter(fn ($l) => (int) $l->accepte !== 3)->values();

            if ($livraisons->isEmpty()) {
                continue;
            }

            // Une course encore en route interdit de clore la commande.
            $enAttente = $livraisons->filter(fn ($l) => $l->etat_livraison !== $LIVREE)->count();

            if ($enAttente > 0) {
                continue;
            }

            $commandee = (float) $lignes->sum('qte');
            $servie = (float) $livraisons->sum(fn ($l) => $l->enlevement
                ? $l->enlevement->quantiteAPayer()
                : (float) $l->qte);

            if ($servie < $commandee) {
                continue;
            }

            $aCorriger[] = [
                'id'        => $commande->id,
                'numero'    => $commande->numero,
                'commandee' => $commandee,
                'servie'    => $servie,
                // Ce que calculait l'ancien test : c'est cet écart qui bloquait.
                'demandee'  => (float) $livraisons->sum('qte'),
                'bons'      => $livraisons->count(),
            ];
        }

        if (empty($aCorriger)) {
            $this->info('Aucune commande à rattraper : rien n\'est resté EN TRAITEMENT à tort.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Commandes entièrement servies mais restées EN TRAITEMENT :');
        $this->table(
            ['Commande', 'Nº', 'Qté commandée', 'Qté servie', 'Qté demandée (ancien calcul)', 'Bons'],
            array_map(fn ($c) => [
                $c['id'], $c['numero'], $c['commandee'], $c['servie'], $c['demandee'], $c['bons'],
            ], $aCorriger)
        );

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aCorriger) {
            foreach ($aCorriger as $c) {
                Commande::where('id', $c['id'])->update([
                    'etat_commande' => Help::$COMMANDE_TERMINE,
                ]);
            }
        });

        $this->newLine();
        $this->info(sprintf('Appliqué : %d commande(s) passée(s) à TERMINEE.', count($aCorriger)));
        $this->line('Elles quittent « Commandes en attente » pour « Commandes traitées ».');

        return self::SUCCESS;
    }
}
