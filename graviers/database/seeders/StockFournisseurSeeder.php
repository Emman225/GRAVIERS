<?php

namespace Database\Seeders;

use Help;
use Illuminate\Database\Seeder;
use App\Models\Produit;
use App\Models\Fournisseur;
use App\Models\StockProduit;

/**
 * Rattache chaque produit actif (catalogue VENTE + matériel LOCATION) à un ou
 * plusieurs fournisseurs via la table stock_produit (quantité + prix fournisseur).
 *
 * Sans ce rattachement, un produit "n'appartient" à aucun fournisseur : il
 * s'affiche via son prix_moyen mais n'a ni stock ni prix fournisseur. Le
 * catalogue affiche désormais le prix fournisseur le plus bas.
 *
 * Répartition sur les 3 fournisseurs existants :
 *   - un fournisseur PRINCIPAL au prix conçu (= prix_moyen, qui reste le prix affiché) ;
 *   - un fournisseur SECONDAIRE à un prix légèrement supérieur (pour la logique
 *     "prix le plus bas" et avoir plusieurs sources).
 *
 * Idempotent : updateOrCreate par (fournisseur_id, produit_id).
 *
 *   php artisan db:seed --class=StockFournisseurSeeder
 */
class StockFournisseurSeeder extends Seeder
{
    public function run(): void
    {
        $actif = Help::$STATUT_ACTIF;

        $fournisseurs = Fournisseur::where('statut', $actif)->orderBy('id')->pluck('id')->values();
        if ($fournisseurs->count() === 0) {
            $this->command->error('Aucun fournisseur actif : rattachement impossible.');
            return;
        }
        $n = $fournisseurs->count();

        $produits = Produit::whereIn('type_affaire', [Help::$VENTE, Help::$LOCATION])
            ->where('statut', $actif)
            ->orderBy('id')
            ->get();

        $compteur = 0;
        foreach ($produits->values() as $i => $p) {
            $estLocation = ($p->type_affaire === Help::$LOCATION);
            $prixBase    = (float) $p->prix_moyen;

            // Fournisseur principal : prix = prix conçu (reste le prix affiché car le moins cher)
            $principal = $fournisseurs[$i % $n];
            StockProduit::updateOrCreate(
                ['fournisseur_id' => $principal, 'produit_id' => $p->id],
                [
                    'qte'         => $estLocation ? 5 : 500,
                    'prix'        => $prixBase,
                    'seuil_alert' => $estLocation ? 1 : 50,
                    'statut'      => $actif,
                ]
            );
            $compteur++;

            // Fournisseur secondaire (différent) : prix +7 % => le principal reste le moins cher
            if ($n > 1) {
                $secondaire = $fournisseurs[($i + 1) % $n];
                StockProduit::updateOrCreate(
                    ['fournisseur_id' => $secondaire, 'produit_id' => $p->id],
                    [
                        'qte'         => $estLocation ? 2 : 200,
                        'prix'        => round($prixBase * 1.07),
                        'seuil_alert' => $estLocation ? 1 : 50,
                        'statut'      => $actif,
                    ]
                );
                $compteur++;
            }
        }

        $this->command->info($produits->count() . ' produits rattachés à des fournisseurs (' . $compteur . ' lignes stock_produit) répartis sur ' . $n . ' fournisseur(s).');
    }
}
