<?php

namespace App\Console\Commands;

use App\Models\Fournisseur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet le solde des fournisseurs d'accord avec leurs pièces.
 *
 * `fournisseur.solde` est une colonne tenue à la main : créditée à la
 * validation d'un bon, débitée depuis cinq endroits différents. Elle dérive
 * donc dès qu'un événement échappe à ces chemins — un bon supprimé, un crédit
 * calculé par une version antérieure du code, ou un vidage de la base qui
 * efface les bons sans toucher aux soldes.
 *
 * Le fournisseur voit alors sur son tableau de bord un « Solde disponible »
 * que plus aucun bon ne justifie, et il peut en demander le paiement.
 *
 * Règle appliquée, celle de Fournisseur::soldeCalcule() :
 *   + ce qui est dû sur les bons VALIDÉS (Enlevement::montantDu) ;
 *   − ce qui a déjà été réglé (« Dettes fournisseurs ») ;
 *   − ce qui a été demandé et non refusé (versé, ou réservé en attente).
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan fournisseur:rattraper-solde
 *   php artisan fournisseur:rattraper-solde --apply
 */
class RattraperSoldeFournisseurs extends Command
{
    protected $signature = 'fournisseur:rattraper-solde {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Recalcule le solde des fournisseurs depuis leurs bons et leurs règlements.";

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        $aCorriger = [];

        foreach (Fournisseur::with('user')->get() as $fournisseur) {
            $colonne = round((float) $fournisseur->solde);
            $calcule = $fournisseur->soldeCalcule();

            // Sous le franc, l'écart n'est qu'un arrondi : on ne réécrit pas.
            if (abs($calcule - $colonne) < 1) {
                continue;
            }

            $aCorriger[] = [
                'id'      => $fournisseur->id,
                'nom'     => $fournisseur->nom_prenoms ?: ($fournisseur->user?->nom_prenoms ?? '-'),
                'avant'   => $colonne,
                'apres'   => $calcule,
                'ecart'   => $calcule - $colonne,
            ];
        }

        if (empty($aCorriger)) {
            $this->info('Aucun solde à rattraper : tous les fournisseurs sont d\'accord avec leurs pièces.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Soldes fournisseurs à corriger :');
        $this->table(
            ['Fournisseur', 'Nom', 'Solde affiché', 'Solde justifié', 'Écart'],
            array_map(fn ($f) => [
                $f['id'],
                $f['nom'],
                number_format($f['avant'], 0, ',', ' '),
                number_format($f['apres'], 0, ',', ' '),
                number_format($f['ecart'], 0, ',', ' '),
            ], $aCorriger)
        );

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aCorriger) {
            foreach ($aCorriger as $f) {
                Fournisseur::where('id', $f['id'])->update(['solde' => $f['apres']]);
            }
        });

        $this->newLine();
        $this->info(sprintf('Appliqué : %d solde(s) corrigé(s).', count($aCorriger)));
        $this->line('Le « Solde disponible » du tableau de bord fournisseur est désormais justifié par ses bons.');

        return self::SUCCESS;
    }
}
