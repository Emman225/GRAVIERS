<?php

namespace App\Console\Commands;

use App\Models\CoutLivraison;
use App\Models\UniteProduit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remplit la grille tarifaire de TRANSPORT avec des montants de recette.
 *
 * Livré en COMMANDE et non en seeder : les classes de database/seeders ne sont
 * pas toujours résolues sur l'hébergement mutualisé — un autoloader optimisé
 * n'y voit pas un fichier ajouté après coup, et « composer dump-autoload » n'y
 * est pas toujours disponible. Les commandes, elles, fonctionnent.
 *
 * Les montants sont des valeurs D'ESSAI, à revoir avant le lancement. Ils sont
 * construits sur le coût réel connu :
 *
 *   forfait = nombre de voyages × coût d'un voyage
 *
 *   · le coût d'un voyage part de 4 000 FCFA à courte distance. Le livreur
 *     touche un forfait de 2 000 FCFA par course, quelle que soit la distance :
 *     en dessous, l'entreprise perd de l'argent sur chaque livraison ;
 *   · le nombre de voyages découle de la capacité d'un camion pour l'unité
 *     considérée (20 tonnes, 10 m³, 200 sacs...).
 *
 * Les bornes hautes s'arrêtent un centième avant la borne basse suivante :
 * CoutLivraison::lireSurCle() compare de façon inclusive des deux côtés, et
 * deux tranches qui se touchent exactement se disputeraient le même cas.
 *
 * Simulation par défaut, aucune écriture. Utiliser --apply pour appliquer.
 *
 *   php artisan grille:remplir
 *   php artisan grille:remplir --apply
 */
class RemplirGrilleTarifaire extends Command
{
    protected $signature = 'grille:remplir {--apply : Écrire réellement la grille (sinon simulation)}';
    protected $description = "Remplit la grille tarifaire des livraisons avec des montants de recette.";

    /** Coût d'un voyage selon la distance : [min, max, coût]. */
    private const VOYAGES = [
        [0,      20,   4000],
        [20.01,  50,   7000],
        [50.01,  100, 12000],
        [100.01, 500, 30000],
    ];

    /**
     * Bornes de quantité par unité, et nombre de voyages correspondant.
     * [abréviation => [[qte_min, qte_max, voyages], ...]]
     */
    private const TRANCHES = [
        // 20 tonnes par camion.
        'T' => [
            [0, 20, 1], [20.01, 60, 3], [60.01, 140, 7], [140.01, 1000, 25],
        ],
        // 10 m³ par camion.
        'M3' => [
            [0, 10, 1], [10.01, 30, 3], [30.01, 70, 7], [70.01, 500, 25],
        ],
        // 200 sacs par camion.
        'SAC' => [
            [0, 200, 1], [200.01, 600, 3], [600.01, 1400, 7], [1400.01, 10000, 25],
        ],
        // 400 barres par camion.
        'BAR' => [
            [0, 400, 1], [400.01, 1200, 3], [1200.01, 2800, 7], [2800.01, 20000, 25],
        ],
        // 50 pièces par camion.
        'U' => [
            [0, 50, 1], [50.01, 150, 3], [150.01, 350, 7], [350.01, 2000, 25],
        ],
        // Location : la quantité est le nombre d'engins, un par voyage.
        'J' => [
            [0, 1, 1], [1.01, 3, 3], [3.01, 7, 7], [7.01, 50, 50],
        ],
    ];

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        $unites = UniteProduit::whereIn('abreviation', array_keys(self::TRANCHES))
            ->get()
            ->keyBy('abreviation');

        $manquantes = array_diff(array_keys(self::TRANCHES), $unites->keys()->all());

        if (!empty($manquantes)) {
            $this->warn('Unités absentes de la table unite_produit, ignorées : ' . implode(', ', $manquantes));
        }

        if ($unites->isEmpty()) {
            $this->error("Aucune des unités attendues n'existe : rien à faire.");
            return self::FAILURE;
        }

        $aRemplacer = CoutLivraison::whereIn('unite_produit_id', $unites->pluck('id'))
            ->whereNull('ville_id')
            ->count();

        $apercu = [];
        $aCreer = 0;

        foreach (self::TRANCHES as $abreviation => $tranchesQte) {
            if (!$unites->get($abreviation)) {
                continue;
            }

            foreach ($tranchesQte as [$qteMin, $qteMax, $voyages]) {
                foreach (self::VOYAGES as [$kmMin, $kmMax, $coutVoyage]) {
                    $aCreer++;

                    // On ne montre qu'un échantillon : 96 lignes à l'écran
                    // seraient illisibles.
                    if ($kmMin == 20.01) {
                        $apercu[] = [
                            $unites->get($abreviation)->libelle,
                            "{$qteMin} à {$qteMax}",
                            "{$kmMin} à {$kmMax} km",
                            $voyages,
                            number_format($voyages * $coutVoyage, 0, ',', ' ') . ' F',
                        ];
                    }
                }
            }
        }

        $this->newLine();
        $this->line('Échantillon (tranche de distance 20 à 50 km) :');
        $this->table(['Unité', 'Quantité', 'Distance', 'Voyages', 'Forfait'], $apercu);

        $this->newLine();
        $this->line("{$aRemplacer} tranche(s) générique(s) existante(s) seraient remplacées par {$aCreer} nouvelle(s).");

        if (!$apply) {
            $this->newLine();
            $this->warn('SIMULATION — rien n\'a été modifié. Relancer avec --apply pour appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($unites) {
            // On repart d'une grille propre pour ces unités : compléter une
            // grille déjà remplie recréerait les chevauchements qu'on corrige.
            CoutLivraison::whereIn('unite_produit_id', $unites->pluck('id'))
                ->whereNull('ville_id')
                ->delete();

            foreach (self::TRANCHES as $abreviation => $tranchesQte) {
                $unite = $unites->get($abreviation);

                if (!$unite) {
                    continue;
                }

                foreach ($tranchesQte as [$qteMin, $qteMax, $voyages]) {
                    foreach (self::VOYAGES as [$kmMin, $kmMax, $coutVoyage]) {
                        CoutLivraison::create([
                            'unite_produit_id' => $unite->id,
                            'ville_id'         => null,
                            'unite_min'        => $qteMin,
                            'unite_max'        => $qteMax,
                            'distance_min_km'  => $kmMin,
                            'distance_max_km'  => $kmMax,
                            'prix_km'          => $voyages * $coutVoyage,
                        ]);
                    }
                }
            }
        });

        $this->newLine();
        $this->info("Grille écrite : {$aCreer} tranche(s), " . $unites->count() . ' unité(s) couverte(s).');
        $this->line("Montants d'ESSAI. À revoir dans « Demandes de livraison → Grille tarifaire livraisons ».");
        $this->line('Contrôle : php artisan grille:auditer');

        return self::SUCCESS;
    }
}
