<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailLocation extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_location';
    protected $fillable = [
        "produit_id",
        "location_id",
        "qte",
        "debut",
        "fin",
        "prix",
        "etat_location",
        "statut",
    ];

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = DetailLocation::selectRaw("detail_location.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->join('produit', 'produit.id', '=', 'detail_location.produit_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->where('detail_location.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailLocation();
    }

    public static function lireCle($produit_id, $location_id)
    {
        $obj = DetailLocation::where('produit_id', $produit_id)
            ->where('location_id', $location_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailLocation();
    }

    public static function liste($produit_id = null, $location_id = null, $client_id = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return DetailLocation::distinct()
            ->selectRaw("detail_location.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'produit.id', '=', 'detail_location.produit_id')
            ->join('location', 'location.id', '=', 'detail_location.location_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('detail_location.produit_id', $produit_id);
            })
            ->when($location_id, function ($query) use ($location_id) {
                $query->where('detail_location.location_id', $location_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('location.client_id', $client_id);
            })
            ->where('detail_location.statut', Help::$STATUT_ACTIF)
            ->where('produit.type_affaire', Help::$LOCATION)
            ->get();
    }

    public static function supprimer($id)
    {
        $obj = DetailLocation::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
