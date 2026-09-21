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
    protected $signature = 'vehicule:liberer
        {--apply : Appliquer réellement les corrections (sinon simulation)}
        {--vehicule= : Immatriculation ou identifiant d\'un véhicule : montre TOUTES ses courses, sans rien modifier}';
    protected $description = "Rend disponibles les véhicules marqués indisponibles alors qu'ils n'ont plus aucune course en cours.";

    public function handle()
    {
        // MODE ENQUÊTE : toutes les courses d'un camion, telles qu'elles sont
        // en base, sans filtre et sans interprétation.
        //
        // Le tableau de décision ne montre que les courses RETENUES par la
        // règle. Quand un camion reste bloqué sans qu'on comprenne pourquoi,
        // c'est justement ce que la règle écarte qu'il faut voir : une course
        // au statut inattendu, un état mal écrit, un refus mal enregistré.
        if ($this->option('vehicule')) {
            return $this->enqueter($this->option('vehicule'));
        }

        $apply = (bool) $this->option('apply');

        $bloques = Vehicule::where('disponible', 0)->get();

        if ($bloques->isEmpty()) {
            $this->info('Aucun véhicule indisponible : rien à libérer.');
            return self::SUCCESS;
        }

        $aLiberer = [];
        $occupes  = [];
        $lignes   = [];
        $detail   = [];

        foreach ($bloques as $vehicule) {
            // Refus exclus, comme dans Vehicule::libererSiPlusAucuneCourse.
            // Une course refusee n'a rien transporte et ne sera jamais livree :
            // comptee ici, elle rendait ce depannage inoperant precisement sur
            // les camions qu'il devait debloquer.
            //
            // On garde les courses elles-memes, et pas seulement leur nombre :
            // « occupe » sans dire PAR QUOI oblige a fouiller la base pour
            // comprendre, ce qui est precisement le travail que cette commande
            // doit epargner.
            $lesCourses = Livraison::where('vehicule_id', $vehicule->id)
                ->where('statut', Help::$STATUT_ACTIF)
                ->where('accepte', '!=', Livraison::REFUSEE)
                ->where('etat_livraison', '!=', Help::$LIVRAISON_LIVREE)
                ->orderBy('id')
                ->get();

            $coursesEnCours = $lesCourses->count();

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

                $detail[$vehicule->immatriculation] = $lesCourses;
            }

            $lignes[] = $ligne;
        }

        $this->newLine();
        $this->table(['ID', 'Immatriculation', 'Livreur', 'Courses en cours', 'Décision'], $lignes);

        // CE QUI RETIENT CHAQUE CAMION, nommément. Un camion « occupé » dont on
        // ne sait pas par quoi laisse le gestionnaire sans recours : il ne peut
        // ni clôturer la course qui bloque, ni savoir si elle est légitime.
        foreach ($detail as $immatriculation => $courses) {
            $this->newLine();
            $this->line("Le véhicule <options=bold>{$immatriculation}</> est retenu par :");

            $this->table(
                ['Course', 'Provenance', 'Affaire', 'État', 'Acceptée', 'Affectée le'],
                $courses->map(fn ($c) => [
                    $c->numero,
                    $c->provenance,
                    $c->detail_livraison_id
                        ? ('demande ' . ($c->detailLivraison?->demandeLivraison?->numero ?? '?'))
                        : ($c->detail_commande_id ? ('commande ligne ' . $c->detail_commande_id) : '—'),
                    $c->etat_livraison,
                    match ((int) $c->accepte) {
                        1 => 'en attente',
                        2 => 'oui',
                        default => (string) $c->accepte,
                    },
                    (string) $c->created_at,
                ])->all()
            );
        }

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

    /**
     * TOUTES les courses d'un camion, brutes.
     *
     * Aucune modification : cette commande ne sert qu'à comprendre. Les valeurs
     * sont affichées telles quelles — un état écrit « 3 » au lieu de « LIVREE »,
     * un statut inattendu ou un refus mal enregistré se voient alors du premier
     * coup d'œil, là où le tableau de décision les aurait silencieusement
     * écartés ou retenus.
     */
    private function enqueter(string $reference): int
    {
        $vehicule = Vehicule::where('immatriculation', $reference)
            ->orWhere('id', (int) $reference)
            ->first();

        if (!$vehicule) {
            $this->error("Aucun véhicule ne correspond à « {$reference} ».");
            return self::FAILURE;
        }

        $this->newLine();
        $this->line(sprintf(
            'Véhicule <options=bold>%s</> (id %d) — disponible : <options=bold>%s</>',
            $vehicule->immatriculation,
            $vehicule->id,
            $vehicule->disponible ? 'OUI' : 'NON'
        ));

        $courses = Livraison::where('vehicule_id', $vehicule->id)->orderBy('id')->get();

        if ($courses->isEmpty()) {
            $this->newLine();
            $this->warn('Ce véhicule ne porte AUCUNE course. S\'il est indisponible, '
                . 'c\'est qu\'il a été mis ainsi à la main (panne, entretien).');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Course', 'Provenance', 'État livraison', 'Accepté', 'Statut', 'Retient le camion ?'],
            $courses->map(function ($c) {
                $retient = (int) $c->statut === (int) Help::$STATUT_ACTIF
                    && (int) $c->accepte !== Livraison::REFUSEE
                    && $c->etat_livraison !== Help::$LIVRAISON_LIVREE;

                return [
                    $c->numero,
                    $c->provenance,
                    $c->etat_livraison,
                    $c->accepte,
                    $c->statut,
                    $retient ? 'OUI' : 'non',
                ];
            })->all()
        );

        $bloquantes = $courses->filter(fn ($c) =>
            (int) $c->statut === (int) Help::$STATUT_ACTIF
            && (int) $c->accepte !== Livraison::REFUSEE
            && $c->etat_livraison !== Help::$LIVRAISON_LIVREE
        );

        $this->newLine();

        if ($bloquantes->isEmpty()) {
            $this->info('Aucune course ne retient ce véhicule : « vehicule:liberer --apply » le rendra disponible.');
        } else {
            $this->warn(sprintf(
                '%d course(s) retiennent ce véhicule. Il ne sera pas libéré tant qu\'elles ne seront pas '
                . 'clôturées ou refusées.',
                $bloquantes->count()
            ));
        }

        return self::SUCCESS;
    }
}
