<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Services\FacturationCommande;
use Illuminate\Console\Command;

/**
 * Rattrapage des commandes déjà réglées mais jamais facturées.
 *
 * La facturation automatique ne se déclenche qu'à l'enregistrement d'un
 * règlement. Les commandes payées AVANT sa mise en service restent donc sans
 * facture — ce sont elles qui affichent « réglé d'avance » au tableau de bord
 * du client, pour de l'argent pourtant encaissé.
 *
 * Cette commande les parcourt une fois. Elle n'invente rien : elle applique la
 * même règle que le flux normal, et n'émet donc jamais plus que le dû ni ce qui
 * est déjà facturé.
 *
 *   php artisan gravier:facturer-commandes-reglees --dry-run   (liste, sans agir)
 *   php artisan gravier:facturer-commandes-reglees             (émet les factures)
 *
 * L'envoi des factures par courriel est DÉSACTIVÉ par défaut : réveiller des
 * dizaines de clients avec une facture pour une commande ancienne serait mal
 * compris. Ajouter --envoyer pour les transmettre.
 */
class FacturerCommandesReglees extends Command
{
    protected $signature = 'gravier:facturer-commandes-reglees
        {--dry-run : Liste les commandes concernées sans rien créer}
        {--envoyer : Transmet aussi chaque facture au client par courriel}';

    protected $description = "Émet les factures manquantes des commandes déjà réglées (rattrapage unique).";

    public function handle(): int
    {
        $essaiABlanc = (bool) $this->option('dry-run');
        $envoyer     = (bool) $this->option('envoyer');

        $commandes = Commande::whereNull('deleted_at')
            ->where('etat_commande', '<>', 'ANNULEE')
            ->orderBy('id')
            ->get();

        $concernees = [];

        foreach ($commandes as $commande) {
            $du          = round(FacturationCommande::montantDu($commande));
            $regle       = round(FacturationCommande::montantRegle($commande));
            $dejaFacture = round(FacturationCommande::montantDejaFacture($commande));
            $aFacturer   = min($du, $regle) - $dejaFacture;

            // Même seuil que le flux normal : un franc d'écart d'arrondi ne
            // justifie pas une facture.
            if ($aFacturer >= 1) {
                $concernees[] = [
                    'commande'  => $commande,
                    'du'        => $du,
                    'regle'     => $regle,
                    'facture'   => $dejaFacture,
                    'aFacturer' => $aFacturer,
                ];
            }
        }

        if (empty($concernees)) {
            $this->info('Aucune commande à rattraper : tout ce qui est réglé est déjà facturé.');
            return self::SUCCESS;
        }

        $this->info(count($concernees) . ' commande(s) à facturer :');
        $this->table(
            ['Commande', 'Dû', 'Réglé', 'Déjà facturé', 'À facturer'],
            array_map(fn ($c) => [
                $c['commande']->numero ?? ('ID#' . $c['commande']->id),
                number_format($c['du'], 0, ',', ' '),
                number_format($c['regle'], 0, ',', ' '),
                number_format($c['facture'], 0, ',', ' '),
                number_format($c['aFacturer'], 0, ',', ' '),
            ], $concernees)
        );

        $total = array_sum(array_column($concernees, 'aFacturer'));
        $this->line('Total à facturer : ' . number_format($total, 0, ',', ' ') . ' FCFA');

        if ($essaiABlanc) {
            $this->warn('Essai à blanc : aucune facture créée.');
            return self::SUCCESS;
        }

        $creees = 0;
        foreach ($concernees as $c) {
            $facture = FacturationCommande::facturerCeQuiEstRegle($c['commande'], $envoyer);
            if ($facture) {
                $creees++;
                $this->line('  ✓ ' . ($c['commande']->numero ?? $c['commande']->id)
                    . ' → facture ' . $facture->numero . ' (' . number_format($facture->montant, 0, ',', ' ') . ' FCFA)');
            } else {
                $this->warn('  ✗ ' . ($c['commande']->numero ?? $c['commande']->id)
                    . ' : aucune facture créée, voir le journal.');
            }
        }

        $this->info($creees . ' facture(s) créée(s).'
            . ($envoyer ? ' Transmission par courriel demandée.' : ' Aucun courriel envoyé (option --envoyer absente).'));

        return self::SUCCESS;
    }
}
