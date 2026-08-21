<?php

namespace App\Models;

use Help;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    /**
     * Des coordonnées sont exploitables si elles ne sont pas « nulles » (0,0 — le
     * point de repli renvoyé par le téléphone quand le GPS est indisponible) et
     * qu'elles restent dans des bornes plausibles.
     */
    public static function coordonneesExploitables($longitude, $latitude): bool
    {
        $long = (float) $longitude;
        $lat  = (float) $latitude;

        // Le « point zéro » (golfe de Guinée) : ni une adresse ni une position réelle.
        if (abs($long) < 0.5 && abs($lat) < 0.5) {
            return false;
        }

        return abs($long) <= 180 && abs($lat) <= 90;
    }

    public static function calculer($longitude, $latitude, $regionID, $qte)
    {
        $region = DB::table('regions')->where('id', $regionID)->first();
        $conf = DB::table('configuration')->first();

        if (!$region || !$conf) {
            return 0;
        }

        $regionLong = isset($region->long) ? $region->long : ($region->longitude ?? 0);
        $regionLat = isset($region->lat) ? $region->lat : ($region->latitude ?? 0);

        // Région sans coordonnées : minimum (cohérent avec le web Help::coutLivraison).
        if ($regionLong == 0 && $regionLat == 0) {
            return (float) ($conf->cout_livraison_min ?? 0);
        }

        // GARDE-FOU COORDONNÉES : l'application envoie « position?.longitude ?? 0 ».
        // GPS coupé ou refusé -> (0,0), un point dans l'Atlantique. La distance
        // devenait alors de plusieurs centaines de kilomètres et le transport était
        // facturé plusieurs milliers de francs (739 km -> 3 695 F mesurés) au lieu du
        // coût réel. On retombe sur le coût minimum plutôt que sur une distance absurde.
        if (!self::coordonneesExploitables($longitude, $latitude)) {
            \Log::warning('Coût de livraison : coordonnées inexploitables, application du coût minimum', [
                'longitude' => $longitude,
                'latitude'  => $latitude,
                'region_id' => $regionID,
            ]);
            return (float) ($conf->cout_livraison_min ?? 0);
        }

        $km = Help::distance($longitude, $latitude, $regionLong, $regionLat); // Distance en km

        // MÊME formule que le web (Help::coutLivraison) : coût = km × prixKm,
        // plancher cout_livraison_min. On n'utilise plus cout_liv_fixe ni le nombre
        // de voyages (tonne_moyenne) — ils donnaient un résultat différent du web
        // (et cout_liv_fixe=0 rendait le coût toujours égal au minimum).
        // $qte est conservé pour la signature mais n'entre plus dans le calcul.
        $prixKm  = (float) ($conf->prixKm ?? 0);
        $coutMin = (float) ($conf->cout_livraison_min ?? 0);

        $prix = $km * $prixKm;
        if ($coutMin > 0 && $prix < $coutMin) {
            $prix = $coutMin;
        }

        return $prix;
    }
}
