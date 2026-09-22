<?php

namespace App\Console\Commands;

use App\Models\EcritureComptable;
use App\Models\Facture;
use App\Services\Comptabilite\MoteurEcritures;
use App\Services\Comptabilite\MoteurTresorerie;
use Illuminate\Console\Command;

/**
 * Produit les écritures des factures normalisées qui n'en ont pas encore
 * (reprise de l'historique), et reprend celles qui sont en anomalie.
 *
 * Sans danger à rejouer : une facture n'a qu'une écriture vivante, et une
 * écriture exportée n'est jamais retouchée.
 *
 *   php artisan comptabilite:produire-ecritures
 *   php artisan comptabilite:produire-ecritures --du=2026-09-01 --au=2026-09-30
 *   php artisan comptabilite:produire-ecritures --simulation
 */
class ProduireEcrituresComptables extends Command
{
    protected $signature = 'comptabilite:produire-ecritures
                            {--du= : date de certification de début (AAAA-MM-JJ)}
                            {--au= : date de certification de fin (AAAA-MM-JJ)}
                            {--simulation : compte sans rien écrire}';

    protected $description = 'Produit les écritures comptables des factures certifiées par la DGI (reprise et anomalies).';

    public function handle(): int
    {
        $requete = Facture::where('fne_status', 'certified')->orderBy('fne_certified_at')->orderBy('id');
        if ($this->option('du')) {
            $requete->whereDate('fne_certified_at', '>=', $this->option('du'));
        }
        if ($this->option('au')) {
            $requete->whereDate('fne_certified_at', '<=', $this->option('au'));
        }

        $total = (clone $requete)->count();
        if ($this->option('simulation')) {
            $dejaEcrites = EcritureComptable::where('source_type', 'facture')
                ->whereIn('source_id', (clone $requete)->pluck('id'))->distinct()->count('source_id');
            $this->info("{$total} facture(s) certifiée(s) sur la période, dont {$dejaEcrites} ont déjà une écriture. Rien n'a été écrit.");
            $this->line('Les règlements, avances, décaissements et cautions de la période seront aussi repris.');

            return self::SUCCESS;
        }

        $bilan = ['a_exporter' => 0, 'anomalie' => 0, 'exportee' => 0, 'echec' => 0];
        $requete->chunkById(200, function ($factures) use (&$bilan) {
            foreach ($factures as $facture) {
                try {
                    $ecriture = MoteurEcritures::produirePourFacture($facture);
                    $bilan[match ($ecriture?->etat) {
                        EcritureComptable::ETAT_EXPORTEE => 'exportee',
                        EcritureComptable::ETAT_ANOMALIE => 'anomalie',
                        default                          => 'a_exporter',
                    }]++;
                } catch (\Throwable $e) {
                    $bilan['echec']++;
                    $this->error("Facture n° {$facture->numero} : " . $e->getMessage());
                }
            }
        });

        $this->info("{$total} facture(s) certifiée(s) traitée(s).");
        $this->line("  à exporter : {$bilan['a_exporter']}");
        $this->line("  en anomalie : {$bilan['anomalie']} (paramétrage incomplet ou écart de total : table anomalie_comptable, puis relancer la commande)");
        $this->line("  déjà exportées, laissées telles quelles : {$bilan['exportee']}");
        if ($bilan['echec']) {
            $this->warn("  en échec : {$bilan['echec']}");
        }

        // La trésorerie (lot 118) : encaissements, avances, décaissements, cautions — bornée sur la date de l'opération.
        $tresorerie = MoteurTresorerie::toutProduire($this->option('du'), $this->option('au'));
        $this->info('Trésorerie :');
        $this->line("  encaissements et imputations d'avances : {$tresorerie['encaissements']}");
        $this->line("  avances reçues : {$tresorerie['avances']}");
        $this->line("  décaissements (fournisseurs, livreurs, apporteurs) : {$tresorerie['decaissements']}");
        $this->line("  cautions reçues ou rendues : {$tresorerie['cautions']}");
        if ($tresorerie['echecs']) {
            $this->warn("  en échec : {$tresorerie['echecs']} (voir storage/logs)");
        }

        return ($bilan['echec'] || $tresorerie['echecs']) ? self::FAILURE : self::SUCCESS;
    }
}
