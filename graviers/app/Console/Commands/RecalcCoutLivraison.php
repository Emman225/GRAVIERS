<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Configuration;
use App\Models\AdresseLivraison;
use Help;

/**
 * Correction historique du coût de livraison (gain livreur).
 *
 * Recalcule cout_livraison des livraisons AVEC livreur dont le coût est nul/0,
 * selon la tarification CONFIGURÉE du livreur (Livreur::gainPourLivraison) :
 *   - mode 'km'   : tarif_km × distance
 *   - mode 'base' : tarif de base (forfait)
 *   - non configuré : repli sur le coût global (distance × cout_liv_fixe × voyages)
 *
 * Recrédit du solde par DELTA et UNIQUEMENT pour les livraisons déjà finalisées
 * (etat = LIVREE) : on ajoute (nouveau − ancien) au solde du livreur — le crédit
 * qui manquait à la finalisation. Les livraisons non finalisées ne touchent pas au
 * solde (elles seront créditées correctement à leur finalisation).
 *
 * PRÉREQUIS : avoir d'abord configuré la tarification de chaque livreur (profil),
 * sinon le repli global vaut 0 (cout_liv_fixe = 0) et rien ne change.
 *
 * Simulation par défaut (aucune écriture). Utiliser --apply pour appliquer.
 *
 *   php artisan livreur:recalc-cout            (simulation)
 *   php artisan livreur:recalc-cout --apply    (applique, transactionnel)
 */
class RecalcCoutLivraison extends Command
{
    protected $signature = 'livreur:recalc-cout {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Recalcule le cout_livraison des livraisons à 0 selon la tarification du livreur et recrédite les soldes (delta).";

    public function handle()
    {
        $apply        = (bool) $this->option('apply');
        $conf         = Configuration::first();
        $coutFixe     = (float) ($conf->cout_liv_fixe ?? 0);
        $tonneMoyenne = max(1, (float) ($conf->tonne_moyenne ?? 40));
        $LIVREE       = Help::$LIVRAISON_LIVREE;

        // forfait_base/frais_km sont NOT NULL DEFAULT 0 : on ne peut pas détecter
        // "non renseigné" par NULL. On récupère donc toutes les livraisons avec livreur
        // et on décide dans la boucle (cohérence cout vs décomposition).
        $livraisons = Livraison::whereNotNull('livreur_id')->get();

        if ($livraisons->isEmpty()) {
            $this->info('Aucune livraison à corriger (avec livreur et coût à 0).');
            return self::SUCCESS;
        }

        $updates     = [];   // [livraison_id => nouveau_cout]
        $soldeDeltas = [];    // [livreur_id => delta_solde]
        $rows        = [];

        foreach ($livraisons as $l) {
            $livreur = Livreur::find($l->livreur_id);
            if (!$livreur) {
                continue;
            }

            // Distance (adresse de la livraison -> région). Inutile en mode 'base'.
            $distance = 0;
            if ($l->adresse_livraison_id) {
                $adr = AdresseLivraison::find($l->adresse_livraison_id);
                if ($adr && $adr->ville && $adr->ville->region) {
                    $distance = Help::distance(
                        $adr->longitude, $adr->latitude,
                        $adr->ville->region->long, $adr->ville->region->lat
                    );
                }
            }

            $capacite   = (float) (optional($l->vehicule)->capacite ?: $tonneMoyenne);
            $voyages    = ((float) $l->qte) / max(1, $capacite);
            $coutGlobal = (float) $distance * $coutFixe * $voyages;

            $tarif = $livreur->tarificationLivraison((float) $distance, (float) $coutGlobal);
            $new   = (float) $tarif['total'];
            $old   = (float) ($l->cout_livraison ?? 0);
            $oldBreakdown = (float) ($l->forfait_base ?? 0) + (float) ($l->frais_km ?? 0);

            // Rien à faire si le total est nul, OU déjà cohérent (cout == total ET
            // décomposition == total). forfait_base/frais_km étant NOT NULL DEFAULT 0,
            // on compare les valeurs (pas le NULL).
            if ($new <= 0) {
                continue;
            }
            if (abs($new - $old) < 0.01 && abs($oldBreakdown - $new) < 0.01) {
                continue;
            }

            $dejaCredite = ($l->etat_livraison === $LIVREE);
            $rows[] = [
                $l->id,
                $livreur->id,
                $l->etat_livraison,
                number_format($old, 0, '.', ' '),
                number_format($new, 0, '.', ' '),
                number_format($distance, 2, '.', ' '),
                $dejaCredite ? 'oui (solde +)' : 'non',
            ];

            $updates[$l->id] = [
                'cout_livraison' => $new,
                'forfait_base'   => $tarif['forfait_base'],
                'frais_km'       => $tarif['frais_km'],
                'distance_km'    => round((float) $distance, 2),
            ];
            if ($dejaCredite) {
                $soldeDeltas[$livreur->id] = ($soldeDeltas[$livreur->id] ?? 0) + ($new - $old);
            }
        }

        if (empty($updates)) {
            $this->info('Rien à corriger : aucune tarification ne produit un coût > 0 (configurez le tarif des livreurs d\'abord).');
            return self::SUCCESS;
        }

        $this->line('');
        $this->table(['Livraison', 'Livreur', 'État', 'Ancien', 'Nouveau', 'Distance(km)', 'Crédité solde'], $rows);

        $soldeRows = [];
        foreach ($soldeDeltas as $livreurId => $delta) {
            $liv   = Livreur::find($livreurId);
            $avant = (float) $liv->solde;
            $soldeRows[] = [$livreurId, number_format($avant, 0, '.', ' '), '+' . number_format($delta, 0, '.', ' '), number_format($avant + $delta, 0, '.', ' ')];
        }
        if ($soldeRows) {
            $this->line('Recrédit des soldes (livraisons déjà finalisées) :');
            $this->table(['Livreur', 'Solde avant', 'Delta', 'Solde après'], $soldeRows);
        }

        if (!$apply) {
            $this->warn('SIMULATION — aucune écriture. Relancez avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates, $soldeDeltas) {
            foreach ($updates as $livId => $data) {
                Livraison::where('id', $livId)->update($data);
            }
            foreach ($soldeDeltas as $livreurId => $delta) {
                $liv = Livreur::find($livreurId);
                if ($liv) {
                    $liv->solde = (float) $liv->solde + (float) $delta;
                    $liv->save();
                }
            }
        });

        $this->info('✅ Appliqué : ' . count($updates) . ' livraison(s) corrigée(s), ' . count($soldeDeltas) . ' solde(s) recrédité(s).');
        return self::SUCCESS;
    }
}
