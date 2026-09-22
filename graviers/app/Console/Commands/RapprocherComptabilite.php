<?php

namespace App\Console\Commands;

use App\Services\Comptabilite\JournalDesEcritures;
use App\Services\Comptabilite\RapportsComptables;
use Illuminate\Console\Command;

/**
 * LE RAPPROCHEMENT DE LA PHASE 5 : « total des écritures = total des factures
 * normalisées », sur une période réelle — celui que le rapport (section 4,
 * phase 5) demande de faire avec le comptable avant la mise en production.
 *
 * Reprend le même contrôle que le rapport web « Rapprochement FNE /
 * écritures » (RapportsComptables::rapprochementFne), mais en ligne de
 * commande : un code de sortie non nul dès qu'une facture ne se retrouve pas
 * exactement dans ses écritures, pour qu'on puisse l'exécuter à chaque
 * clôture de mois et pas seulement une fois à la recette.
 *
 *   php artisan comptabilite:rapprocher --du=2026-09-01 --au=2026-09-30
 *   php artisan comptabilite:rapprocher --mois=2026-09
 *   php artisan comptabilite:rapprocher --mois=2026-09 --fichier=stockage/rapprochement-2026-09.txt
 */
class RapprocherComptabilite extends Command
{
    protected $signature = 'comptabilite:rapprocher
                            {--du= : date de certification de début (AAAA-MM-JJ)}
                            {--au= : date de certification de fin (AAAA-MM-JJ)}
                            {--mois= : raccourci pour --du/--au, au format AAAA-MM}
                            {--fichier= : enregistre aussi le détail dans ce fichier (chemin absolu ou relatif au projet)}';

    protected $description = "Rapproche les factures normalisées certifiées et leurs écritures : la vérification de la phase 5 (recette).";

    public function handle(): int
    {
        // Un mois explicite prime ; sans lui, des dates explicites ; sans rien, le mois en cours.
        if ($this->option('mois')) {
            $periode = JournalDesEcritures::periode(['mode_periode' => 'MOIS', 'periode' => $this->option('mois')]);
        } elseif ($this->option('du') || $this->option('au')) {
            $periode = JournalDesEcritures::periode(['mode_periode' => 'DATES', 'du' => $this->option('du'), 'au' => $this->option('au')]);
        } else {
            $periode = JournalDesEcritures::periode(['mode_periode' => 'MOIS']);
        }

        $lignes = RapportsComptables::rapprochementFne($periode['du'], $periode['au']);

        $parEtat = ['CONFORME' => [], 'ECART' => [], 'ANOMALIE' => [], 'ABSENTE' => []];
        foreach ($lignes as $ligne) {
            $parEtat[$ligne['etat']][] = $ligne;
        }

        $totalFactures  = array_sum(array_column($lignes, 'montant'));
        $totalEcritures = array_sum(array_map(fn ($l) => (float) ($l['ecriture']?->total_debit ?? 0), $lignes));
        $ecartGlobal    = round($totalFactures - $totalEcritures, 2);

        $rapport = [];
        $rapport[] = 'Rapprochement comptable — du ' . $periode['du']->format('d/m/Y') . ' au ' . $periode['au']->format('d/m/Y') . ' (édité le ' . now()->format('d/m/Y à H:i') . ')';
        $rapport[] = str_repeat('-', 78);
        $rapport[] = sprintf('%-40s %6d facture(s)', 'Factures normalisées certifiées', count($lignes));
        $rapport[] = sprintf('%-40s %6d facture(s)', '  … conformes', count($parEtat['CONFORME']));
        $rapport[] = sprintf('%-40s %6d facture(s)', '  … avec un écart de montant', count($parEtat['ECART']));
        $rapport[] = sprintf('%-40s %6d facture(s)', '  … dont l\'écriture est en anomalie', count($parEtat['ANOMALIE']));
        $rapport[] = sprintf('%-40s %6d facture(s)', '  … sans écriture du tout', count($parEtat['ABSENTE']));
        $rapport[] = '';
        $rapport[] = sprintf('%-40s %14s F', 'Total des factures normalisées', number_format($totalFactures, 0, ',', ' '));
        $rapport[] = sprintf('%-40s %14s F', 'Total des écritures produites', number_format($totalEcritures, 0, ',', ' '));
        $rapport[] = sprintf('%-40s %14s F', 'Écart', number_format($ecartGlobal, 0, ',', ' '));
        $rapport[] = '';

        $conforme = count($lignes) > 0 && empty($parEtat['ECART']) && empty($parEtat['ANOMALIE']) && empty($parEtat['ABSENTE']) && abs($ecartGlobal) < 1;

        foreach (['ECART' => 'Écarts de montant', 'ANOMALIE' => 'Écritures en anomalie', 'ABSENTE' => 'Sans écriture'] as $cle => $titre) {
            if (empty($parEtat[$cle])) {
                continue;
            }
            $rapport[] = $titre . ' :';
            foreach ($parEtat[$cle] as $ligne) {
                $rapport[] = sprintf('  - %-14s (%s) réf. FNE %s — %s F', $ligne['facture'], $ligne['type'],
                    $ligne['reference'] ?: '—', number_format($ligne['montant'], 0, ',', ' '));
            }
            $rapport[] = '';
        }

        if ($conforme) {
            $verdict = 'RAPPROCHEMENT CONFORME : le total des écritures égale le total des factures normalisées.';
        } elseif (count($lignes) === 0) {
            $verdict = 'Aucune facture normalisée certifiée sur cette période : rien à rapprocher.';
        } else {
            $verdict = 'RAPPROCHEMENT NON CONFORME : voir le détail ci-dessus, ou le rapport « Rapprochement FNE / écritures » du back-office.';
        }
        $rapport[] = $verdict;

        $texte = implode("\n", $rapport);
        $this->line(implode("\n", array_slice($rapport, 0, -1)));

        if ($conforme) {
            $this->info($verdict);
        } elseif (count($lignes) === 0) {
            $this->warn($verdict);
        } else {
            $this->error($verdict);
        }

        if ($this->option('fichier')) {
            $chemin = str_starts_with($this->option('fichier'), '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $this->option('fichier'))
                ? $this->option('fichier') : base_path($this->option('fichier'));
            @file_put_contents($chemin, $texte . "\n");
            $this->line('Détail enregistré : ' . $chemin);
        }

        return ($conforme || count($lignes) === 0) ? self::SUCCESS : self::FAILURE;
    }
}
