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
        'unite_min',
        'unite_max',
        'distance_min_km',
        'distance_max_km',
        'prix_km',
    ];

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
