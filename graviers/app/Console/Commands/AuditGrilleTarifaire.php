<?php

namespace App\Console\Commands;

use App\Models\CoutLivraison;
use App\Models\Produit;
use App\Models\UniteProduit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit de la grille tarifaire des livraisons, AVANT de facturer dessus.
 *
 * La grille associe un forfait à un triplet (unité, tranche de quantité,
 * tranche de distance). Trois défauts la rendent inexploitable sans qu'on s'en
 * aperçoive :
 *
 *   · une UNITÉ non tarifée — ses produits n'ont aucun prix de transport ;
 *   · un CHEVAUCHEMENT — deux tranches correspondent au même cas, et le tarif
 *     retenu dépend de l'ordre de lecture de la base ;
 *   · un TROU — une distance ou une quantité qu'aucune tranche ne couvre.
 *
 * Aucun de ces défauts ne provoque d'erreur : la commande se chiffre à zéro, ou
 * au hasard. D'où cette commande, à relancer jusqu'à ce qu'elle soit muette.
 *
 * Lecture seule.
 *
 *   php artisan grille:auditer
 */
class AuditGrilleTarifaire extends Command
{
    protected $signature = 'grille:auditer';
    protected $description = "Vérifie la grille tarifaire : unités non tarifées, chevauchements, trous de couverture.";

    public function handle()
    {
        $problemes = 0;

        // ------------------------------------------------ 1. unités non tarifées
        $this->newLine();
        $this->line('1. COUVERTURE DES UNITÉS');

        $lignes = [];

        foreach (UniteProduit::orderBy('libelle')->get() as $unite) {
            // produit.unite stocke l'ABRÉVIATION, pas l'identifiant.
            $nbProduits = Produit::where('unite', $unite->abreviation)->count();
            $nbTranches = CoutLivraison::where('unite_produit_id', $unite->id)
                ->whereNull('ville_id')
                ->count();

            if ($nbProduits === 0 && $nbTranches === 0) {
                continue; // unité inutilisée : ne pas encombrer le rapport
            }

            $etat = $nbTranches > 0
                ? "{$nbTranches} tranche(s)"
                : ($nbProduits > 0 ? 'AUCUN TARIF' : 'aucun produit');

            if ($nbProduits > 0 && $nbTranches === 0) {
                $problemes++;
            }

            $lignes[] = [$unite->libelle, $unite->abreviation, $nbProduits, $etat];
        }

        $this->table(['Unité', 'Abrév.', 'Produits', 'Tarification'], $lignes);

        $sansUnite = Produit::whereNull('unite')->orWhere('unite', '')->count();
        if ($sansUnite > 0) {
            $problemes++;
            $this->warn("{$sansUnite} produit(s) n'ont aucune unité : ils ne pourront jamais être tarifés.");
        }

        // --------------------------------------------------- 2. chevauchements
        $this->newLine();
        $this->line('2. CHEVAUCHEMENTS');

        $tranches = CoutLivraison::whereNull('ville_id')->orderBy('unite_produit_id')->get();
        $chevauchements = [];

        foreach ($tranches as $i => $a) {
            foreach ($tranches as $j => $b) {
                if ($j <= $i || $a->unite_produit_id != $b->unite_produit_id) {
                    continue;
                }

                $qteSeCroise = $a->unite_min <= $b->unite_max && $b->unite_min <= $a->unite_max;
                $kmSeCroise  = $a->distance_min_km <= $b->distance_max_km && $b->distance_min_km <= $a->distance_max_km;

                if ($qteSeCroise && $kmSeCroise) {
                    $chevauchements[] = [
                        $this->nomUnite($a->unite_produit_id),
                        "#{$a->id} : {$a->unite_min}-{$a->unite_max} / {$a->distance_min_km}-{$a->distance_max_km} km -> " . number_format($a->prix_km, 0, ',', ' '),
                        "#{$b->id} : {$b->unite_min}-{$b->unite_max} / {$b->distance_min_km}-{$b->distance_max_km} km -> " . number_format($b->prix_km, 0, ',', ' '),
                    ];
                }
            }
        }

        if (empty($chevauchements)) {
            $this->info('Aucun chevauchement.');
        } else {
            $problemes += count($chevauchements);
            $this->table(['Unité', 'Tranche A', 'Tranche B'], $chevauchements);
            $this->warn("Deux tranches se disputent le même cas : le tarif appliqué dépend de l'ordre de lecture de la base.");
        }

        // ------------------------------------------------------------ 3. trous
        $this->newLine();
        $this->line('3. TROUS DE COUVERTURE');

        $trous        = [];
        $informations = [];

        foreach ($tranches->groupBy('unite_produit_id') as $uniteId => $duGroupe) {
            $nom = $this->nomUnite($uniteId);

            // Distances : on parcourt les bornes triées et on cherche les vides.
            $parDistance = $duGroupe->sortBy('distance_min_km')->values();
            $couvertJusqua = null;

            // Deux tranches qui se touchent laissent volontairement un centième
            // entre elles : lireSurCle() compare de façon inclusive des deux
            // côtés, des bornes identiques se disputeraient le cas. Ce n'est
            // donc pas un trou.
            $tolerance = 0.05;

            foreach ($parDistance as $t) {
                if ($couvertJusqua === null) {
                    if ((float) $t->distance_min_km > $tolerance) {
                        $trous[] = [$nom, 'distance', "de 0 à {$t->distance_min_km} km"];
                    }
                    $couvertJusqua = (float) $t->distance_max_km;
                    continue;
                }

                if ((float) $t->distance_min_km - $couvertJusqua > $tolerance) {
                    $trous[] = [$nom, 'distance', "de {$couvertJusqua} à {$t->distance_min_km} km"];
                }

                $couvertJusqua = max($couvertJusqua, (float) $t->distance_max_km);
            }

            // Au-delà de la borne haute il n'y a plus rien. On ne le compte comme
            // un défaut que si la couverture s'arrête tôt : une grille qui monte
            // à 500 km couvre la réalité du terrain, l'annoncer comme un problème
            // rendrait l'audit impossible à faire taire.
            if ($couvertJusqua !== null) {
                if ($couvertJusqua < 200) {
                    $trous[] = [$nom, 'distance', "au-delà de {$couvertJusqua} km : aucun tarif"];
                } else {
                    $informations[] = [$nom, 'portée', "tarifée jusqu'à {$couvertJusqua} km"];
                }
            }

            // Une tranche dont la distance min égale la distance max ne s'applique
            // qu'à cette valeur EXACTE : presque toujours une saisie interrompue.
            foreach ($duGroupe as $t) {
                if ((float) $t->distance_min_km == (float) $t->distance_max_km) {
                    $trous[] = [$nom, 'saisie', "tranche #{$t->id} : distance {$t->distance_min_km} à {$t->distance_max_km} km — ne s'applique qu'à cette distance exacte"];
                }
                if ((float) $t->unite_min == (float) $t->unite_max) {
                    $trous[] = [$nom, 'saisie', "tranche #{$t->id} : quantité {$t->unite_min} à {$t->unite_max} — ne s'applique qu'à cette quantité exacte"];
                }
            }
        }

        if (empty($trous)) {
            $this->info('Aucun trou détecté.');
        } else {
            $problemes += count($trous);
            $this->table(['Unité', 'Type', 'Description'], $trous);
        }

        if (!empty($informations)) {
            $this->newLine();
            $this->line('Pour information (aucune action requise) :');
            $this->table(['Unité', 'Type', 'Description'], $informations);
        }

        // ------------------------------------------------------------ verdict
        $this->newLine();

        if ($problemes === 0) {
            $this->info('Grille saine : elle peut servir de base de facturation.');
        } else {
            $this->warn("{$problemes} point(s) à reprendre dans « Demandes de livraison → Grille tarifaire livraisons ».");
            $this->line('Relancez cette commande jusqu\'à ce qu\'elle ne signale plus rien.');
        }

        return self::SUCCESS;
    }

    private function nomUnite($id): string
    {
        return UniteProduit::find($id)?->libelle ?? "unité #{$id}";
    }
}
