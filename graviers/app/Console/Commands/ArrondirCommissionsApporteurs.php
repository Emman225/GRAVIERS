<?php

namespace App\Console\Commands;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arrondit au franc les commissions d'apporteur enregistrées avec des centimes.
 *
 * Le franc CFA n'a pas de décimales. Or trois des quatre circuits qui créent
 * une commission stockaient le résultat brut du pourcentage : 4,72 F, 1,06 F,
 * 1,53 F… Le quatrième — le paiement en ligne — arrondissait déjà.
 *
 * Conséquence visible : le solde de l'apporteur, lui, est arrondi. Son tableau
 * de bord annonçait donc 104 F pendant que le formulaire « Enregistrer un
 * paiement de commission » proposait 103,72 F. Deux chiffres pour la même
 * dette, et un reliquat de 0,28 F que personne ne pourra jamais payer.
 *
 * Le correctif empêche que cela se reproduise ; cette commande répare ce qui a
 * déjà été écrit.
 *
 * LE SOLDE DE L'APPORTEUR EST AJUSTÉ EN CONSÉQUENCE : il est recalculé depuis
 * les commissions arrondies, sinon la correction déplacerait simplement l'écart
 * de la commission vers le solde.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan apporteur:arrondir-commissions
 *   php artisan apporteur:arrondir-commissions --apply
 */
class ArrondirCommissionsApporteurs extends Command
{
    protected $signature = 'apporteur:arrondir-commissions {--apply : Appliquer réellement (sinon simulation)}';
    protected $description = "Arrondit au franc les commissions d'apporteur qui portent des centimes.";

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        $aCorriger = [];

        foreach (CommissionApporteur::with('apporteur.user')->whereNull('deleted_at')->get() as $com) {
            $montant = (float) $com->montant;
            $arrondi = round($montant);

            // Déjà au franc : rien à faire.
            if (abs($arrondi - $montant) < 0.005) {
                continue;
            }

            $aCorriger[] = [
                'id'        => $com->id,
                'apporteur' => $com->apporteur?->user?->nom_prenoms ?? '-',
                'affaire'   => $com->type_affaire,
                'avant'     => $montant,
                'apres'     => $arrondi,
            ];
        }

        if (empty($aCorriger)) {
            $this->info('Aucune commission à arrondir : toutes sont déjà au franc entier.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Commissions portant des centimes :');
        $this->table(
            ['Commission', 'Apporteur', 'Affaire', 'Montant actuel', 'Arrondi'],
            array_map(fn ($c) => [
                $c['id'],
                $c['apporteur'],
                $c['affaire'],
                number_format($c['avant'], 2, ',', ' '),
                number_format($c['apres'], 0, ',', ' '),
            ], $aCorriger)
        );

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            $this->line('Le solde des apporteurs concernés sera recalculé depuis leurs commissions.');
            return self::SUCCESS;
        }

        $apporteurs = collect();

        DB::transaction(function () use ($aCorriger, &$apporteurs) {
            foreach ($aCorriger as $c) {
                $com = CommissionApporteur::find($c['id']);

                if (!$com) {
                    continue;
                }

                $com->update(['montant' => $c['apres']]);

                if ($com->apporteur_id) {
                    $apporteurs->push($com->apporteur_id);
                }
            }

            // Le solde suit : sans cela, la correction ne ferait que déplacer
            // l'écart de la commission vers le solde.
            foreach ($apporteurs->unique() as $id) {
                $apporteur = Apporteur::find($id);

                if ($apporteur) {
                    $apporteur->update(['solde' => $apporteur->soldeCalcule()]);
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Appliqué : %d commission(s) arrondie(s), %d solde(s) recalculé(s).',
            count($aCorriger),
            $apporteurs->unique()->count()
        ));
        $this->line('Le solde de l\'apporteur et la commission à payer annoncent désormais le même montant.');

        return self::SUCCESS;
    }
}
