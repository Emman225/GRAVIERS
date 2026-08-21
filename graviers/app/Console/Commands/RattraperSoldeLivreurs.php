<?php

namespace App\Console\Commands;

use App\Models\Livreur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet le solde des livreurs d'accord avec leurs pièces.
 *
 * `livreur.solde` est un cumul tenu à la main : crédité à la clôture de chaque
 * course (site ET application mobile), débité aux demandes de paiement. Il
 * dérive donc dès qu'un événement lui échappe.
 *
 * Règle appliquée, celle de Livreur::soldeCalcule() :
 *   + ce que rapportent les courses réellement LIVRÉES ;
 *   − ce qui a déjà été réglé ;
 *   − ce qui a été demandé et non refusé.
 *
 * MÊME PRÉCAUTION QUE POUR LES APPORTEURS.
 *
 * Une course supprimée — un vidage de base, par exemple — emporte la preuve
 * du gain, mais pas la demande de paiement qui l'avait suivi. Le calcul voit
 * alors des retraits sans recettes et conclut à zéro, alors que le livreur
 * avait bel et bien roulé. Baisser son solde sur cette base lui retirerait un
 * gain réel.
 *
 * Par défaut, cette commande n'AUGMENTE donc que les soldes : elle rend
 * l'argent avalé par les demandes qui ont échoué, sans jamais retirer ce
 * qu'elle ne sait pas voir. Baisser exige --baisser, en connaissance de cause.
 *
 *   php artisan livreur:rattraper-solde
 *   php artisan livreur:rattraper-solde --apply
 *   php artisan livreur:rattraper-solde --apply --baisser
 */
class RattraperSoldeLivreurs extends Command
{
    protected $signature = 'livreur:rattraper-solde
        {--apply : Appliquer réellement les corrections (sinon simulation)}
        {--baisser : Autoriser aussi la BAISSE des soldes (voir l\'avertissement)}';

    protected $description = "Recalcule le solde des livreurs depuis leurs courses livrées et leurs règlements.";

    public function handle()
    {
        $apply   = (bool) $this->option('apply');
        $baisser = (bool) $this->option('baisser');

        $hausses = [];
        $baisses = [];

        foreach (Livreur::with('user')->get() as $livreur) {
            $colonne = round((float) $livreur->solde);
            $calcule = $livreur->soldeCalcule();

            // Sous le franc, l'écart n'est qu'un arrondi : on ne réécrit pas.
            if (abs($calcule - $colonne) < 1) {
                continue;
            }

            $ligne = [
                'id'    => $livreur->id,
                'nom'   => $livreur->user?->nom_prenoms ?? '-',
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
            $this->info('Aucun solde à rattraper : tous les livreurs sont d\'accord avec leurs pièces.');
            return self::SUCCESS;
        }

        if ($hausses) {
            $this->newLine();
            $this->line('Soldes à RELEVER — de l\'argent manque au tableau de bord :');
            $this->afficher($hausses);
        }

        if ($baisses) {
            $this->newLine();
            $this->line('Soldes plus élevés que ce que les courses justifient :');
            $this->afficher($baisses);
            $this->newLine();
            $this->warn("Ces écarts peuvent être LÉGITIMES : une course supprimée emporte la");
            $this->warn("preuve du gain, mais pas la demande de paiement qui l'avait suivie.");
            $this->warn("Le calcul voit alors des retraits sans recettes et conclut a zéro.");
            $this->warn("Vérifiez au cas par cas avant d'utiliser --baisser.");
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
            foreach ($aEcrire as $l) {
                Livreur::where('id', $l['id'])->update(['solde' => $l['apres']]);
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
            ['Livreur', 'Nom', 'Solde affiché', 'Solde justifié', 'Écart'],
            array_map(fn ($l) => [
                $l['id'],
                $l['nom'],
                number_format($l['avant'], 0, ',', ' '),
                number_format($l['apres'], 0, ',', ' '),
                number_format($l['ecart'], 0, ',', ' '),
            ], $lignes)
        );
    }
}
