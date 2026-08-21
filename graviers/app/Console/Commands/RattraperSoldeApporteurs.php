<?php

namespace App\Console\Commands;

use App\Models\Apporteur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet le solde des apporteurs d'accord avec leurs pièces.
 *
 * UNE PRÉCAUTION PARTICULIÈRE S'IMPOSE ICI.
 *
 * Jusqu'à la migration `tracer_les_commissions_de_location`, une commission
 * gagnée sur une LOCATION créditait le solde SANS créer de commission. Ces
 * gains n'ont laissé aucune trace : ils sont irrécupérables, et le calcul ne
 * peut pas les compter. Un apporteur ayant travaillé sur des locations
 * paraîtra donc avoir un solde « trop haut » alors qu'il l'a bien gagné.
 *
 * Par défaut, cette commande ne fait donc qu'AUGMENTER les soldes — elle
 * rend l'argent avalé par les demandes qui ont échoué, sans jamais retirer un
 * gain qu'elle ne saurait pas voir. Baisser un solde exige --baisser, en
 * connaissance de cause.
 *
 *   php artisan apporteur:rattraper-solde
 *   php artisan apporteur:rattraper-solde --apply
 *   php artisan apporteur:rattraper-solde --apply --baisser
 */
class RattraperSoldeApporteurs extends Command
{
    protected $signature = 'apporteur:rattraper-solde
        {--apply : Appliquer réellement les corrections (sinon simulation)}
        {--baisser : Autoriser aussi la BAISSE des soldes (voir l\'avertissement)}';

    protected $description = "Recalcule le solde des apporteurs depuis leurs commissions et leurs règlements.";

    public function handle()
    {
        $apply   = (bool) $this->option('apply');
        $baisser = (bool) $this->option('baisser');

        $hausses = [];
        $baisses = [];

        foreach (Apporteur::with('user')->get() as $apporteur) {
            $colonne = round((float) $apporteur->solde);
            $calcule = $apporteur->soldeCalcule();

            if (abs($calcule - $colonne) < 1) {
                continue;
            }

            $ligne = [
                'id'    => $apporteur->id,
                'nom'   => $apporteur->user?->nom_prenoms ?? '-',
                'avant' => $colonne,
                'apres' => $calcule,
                'ecart' => $calcule - $colonne,
            ];

            if ($ligne['ecart'] > 0) {
                $hausses[] = $ligne;
            } else {
                $baisses[] = $ligne;
            }
        }

        if (empty($hausses) && empty($baisses)) {
            $this->info('Aucun solde à rattraper : tous les apporteurs sont d\'accord avec leurs pièces.');
            return self::SUCCESS;
        }

        if ($hausses) {
            $this->newLine();
            $this->line('Soldes à RELEVER — de l\'argent manque au tableau de bord :');
            $this->afficher($hausses);
        }

        if ($baisses) {
            $this->newLine();
            $this->line('Soldes plus élevés que ce que les commissions justifient :');
            $this->afficher($baisses);
            $this->newLine();
            $this->warn("Ces écarts peuvent être LÉGITIMES : les commissions gagnées sur des");
            $this->warn("LOCATIONS avant le correctif n'ont laissé aucune trace en base et ne");
            $this->warn("peuvent pas être comptées. Baisser ces soldes retirerait à l'apporteur");
            $this->warn("un gain réel. Vérifiez au cas par cas avant d'utiliser --baisser.");
        }

        $aEcrire = $baisser ? array_merge($hausses, $baisses) : $hausses;

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');

            if ($baisses && !$baisser) {
                $this->line('Sans --baisser, seuls les ' . count($hausses) . ' solde(s) à relever seraient écrits.');
            }

            return self::SUCCESS;
        }

        if (empty($aEcrire)) {
            $this->newLine();
            $this->info('Rien à écrire : seules des baisses ont été trouvées, et --baisser n\'a pas été demandé.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aEcrire) {
            foreach ($aEcrire as $a) {
                Apporteur::where('id', $a['id'])->update(['solde' => $a['apres']]);
            }
        });

        $this->newLine();
        $this->info(sprintf('Appliqué : %d solde(s) corrigé(s).', count($aEcrire)));

        if ($baisses && !$baisser) {
            $this->line(sprintf('%d baisse(s) laissée(s) de côté (relancer avec --baisser si voulu).', count($baisses)));
        }

        return self::SUCCESS;
    }

    private function afficher(array $lignes): void
    {
        $this->table(
            ['Apporteur', 'Nom', 'Solde affiché', 'Solde justifié', 'Écart'],
            array_map(fn ($a) => [
                $a['id'],
                $a['nom'],
                number_format($a['avant'], 0, ',', ' '),
                number_format($a['apres'], 0, ',', ' '),
                number_format($a['ecart'], 0, ',', ' '),
            ], $lignes)
        );
    }
}
