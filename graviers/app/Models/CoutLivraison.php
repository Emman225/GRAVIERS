<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoutLivraison extends Model
{
    use HasFactory;
    protected $table = 'cout_livraison';
    protected $fillable = [
        'unite_produit_id',
        // Tarif propre à une ville. NULL = tarif GÉNÉRIQUE, valable partout :
        // c'est ce que lireSurCle() retient. Colonne longtemps interrogée par le
        // modèle sans exister en base (cf. migration du 13/08/2026).
        'ville_id',
        'unite_min',
        'unite_max',
        'distance_min_km',
        'distance_max_km',
        'prix_km',
    ];

    public function uniteProduit()
    {
        return $this->belongsTo(UniteProduit::class, 'unite_produit_id');
    }

    public function ville()
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /**
     * Tranches qui en recouvrent une autre : même unité, même portée
     * géographique, et intervalles de quantité ET de distance qui se croisent.
     *
     * Deux tranches qui se recouvrent rendent le tarif indéterminé — la
     * recherche prend la première venue, sans règle. On refuse donc d'en créer,
     * et on signale celles qui existent déjà.
     *
     * $ignorerId sert à la modification : une tranche ne se recouvre pas
     * elle-même.
     */
    public static function tranchesEnConflit(array $valeurs, ?int $ignorerId = null)
    {
        return CoutLivraison::where('unite_produit_id', $valeurs['unite_produit_id'])
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->when(
                empty($valeurs['ville_id']),
                fn ($q) => $q->where(function ($qq) {
                    $qq->whereNull('ville_id')->orWhere('ville_id', '<=', 0);
                }),
                fn ($q) => $q->where('ville_id', $valeurs['ville_id'])
            )
            // Deux intervalles se croisent si chacun commence avant que l'autre
            // ne finisse.
            ->where('unite_min', '<=', $valeurs['unite_max'])
            ->where('unite_max', '>=', $valeurs['unite_min'])
            ->where('distance_min_km', '<=', $valeurs['distance_max_km'])
            ->where('distance_max_km', '>=', $valeurs['distance_min_km'])
            ->get();
    }

    public static function lireSurCle($unite_produit_id, $unite, $distance){
        $obj = CoutLivraison::where('unite_produit_id', $unite_produit_id)
        ->where(function($query) use($unite){
            $query->where('unite_min', '<=', $unite);
            $query->where('unite_max', '>=', $unite);
        })
        ->where(function($query) use($distance){
            $query->where('distance_min_km', '<=', $distance);
            $query->where('distance_max_km', '>=', $distance);
        })
        ->where(function($query){
            $query->whereNull('ville_id');
            $query->orWhere('ville_id', '<=', 0);
        })
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CoutLivraison();
    }

    public static function lireSurCleAvecVille($unite_produit_id, $unite, $ville){

        $obj = CoutLivraison::where('unite_produit_id', $unite_produit_id)

        ->where(function($query) use($unite){
            $query->where('unite_min', '<=', $unite);
            $query->where('unite_max', '>=', $unite);
        })
        ->where(function($query) use($ville){
            $query->where('ville_id', $ville);
        })
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CoutLivraison();
    }
}
