<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Livraison;
use App\Models\Vehicule;
use Help;

/**
 * Rend disponibles les véhicules restés bloqués après leurs courses.
 *
 * L'affectation d'une demande de livraison passe « vehicule.disponible » à 0,
 * mais rien ne le remettait à 1 une fois la course terminée. Un véhicule
 * disparaissait donc de la liste proposée au gestionnaire après sa PREMIÈRE
 * course, définitivement : plus aucun véhicule à affecter, alors même que les
 * livreurs avaient fini.
 *
 * Le correctif libère désormais le véhicule à la clôture. Cette commande
 * répare ceux qui sont déjà bloqués.
 *
 * Un véhicule n'est libéré que s'il ne reste AUCUNE course en cours sur lui.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan vehicule:liberer
 *   php artisan vehicule:liberer --apply
 */
class LibererVehiculesTermines extends Command
{
    protected $signature = 'vehicule:liberer {--apply : Appliquer réellement les corrections (sinon simulation)}';
    protected $description = "Rend disponibles les véhicules marqués indisponibles alors qu'ils n'ont plus aucune course en cours.";

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        $bloques = Vehicule::where('disponible', 0)->get();

        if ($bloques->isEmpty()) {
            $this->info('Aucun véhicule indisponible : rien à libérer.');
            return self::SUCCESS;
        }

        $aLiberer = [];
        $occupes  = [];
        $lignes   = [];

        foreach ($bloques as $vehicule) {
            $coursesEnCours = Livraison::where('vehicule_id', $vehicule->id)
                ->where('statut', Help::$STATUT_ACTIF)
                ->where('etat_livraison', '!=', Help::$LIVRAISON_LIVREE)
                ->count();

            $ligne = [
                $vehicule->id,
                $vehicule->immatriculation,
                $vehicule->livreur?->user?->nom_prenoms ?? '-',
                $coursesEnCours,
            ];

            if ($coursesEnCours === 0) {
                $aLiberer[] = $vehicule->id;
                $ligne[] = 'à libérer';
            } else {
                $occupes[] = $vehicule->id;
                $ligne[] = 'occupé — laissé tel quel';
            }

            $lignes[] = $ligne;
        }

        $this->newLine();
        $this->table(['ID', 'Immatriculation', 'Livreur', 'Courses en cours', 'Décision'], $lignes);

        if (empty($aLiberer)) {
            $this->info('Tous les véhicules indisponibles ont une course en cours : rien à libérer.');
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->newLine();
            $this->warn(sprintf(
                'SIMULATION — rien n\'a été modifié. %d véhicule(s) seraient libéré(s). Relancer avec --apply.',
                count($aLiberer)
            ));
            return self::SUCCESS;
        }

        DB::transaction(function () use ($aLiberer) {
            Vehicule::whereIn('id', $aLiberer)->update(['disponible' => 1]);
        });

        $this->newLine();
        $this->info(sprintf('Appliqué : %d véhicule(s) rendu(s) disponible(s).', count($aLiberer)));

        if (!empty($occupes)) {
            $this->line(sprintf('%d véhicule(s) laissé(s) indisponible(s), car ils ont une course en cours.', count($occupes)));
        }

        // Un véhicule peut aussi avoir été marqué indisponible À LA MAIN (panne,
        // entretien) depuis la liste des véhicules ou l'application livreur.
        // Cette commande ne fait pas la différence : elle ne voit que l'absence
        // de course. Vérifier la liste ci-dessus avant d'appliquer.
        $this->line('Rappel : un véhicule mis indisponible à la main (panne, entretien) est lui aussi libéré. Relire le tableau.');

        return self::SUCCESS;
    }
}
