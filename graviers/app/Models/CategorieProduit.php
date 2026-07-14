<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategorieProduit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'categorie_produit';
    protected $fillable = [ 
        'categorie_id',
        'produit_id',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = CategorieProduit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CategorieProduit();
    }

    public static function lireCle($categorie_id, $produit_id)
    {
        $obj = CategorieProduit::where('categorie_id', $categorie_id)
        ->where('produit_id', $produit_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new CategorieProduit();
    }

    public static function liste($categorie_id = null, $produit_id = null)
    {
        return CategorieProduit::orderBy('nom', 'asc')
            ->when($categorie_id, function ($query) use ($categorie_id) {
                $query->where('categorie_id', $categorie_id);
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('produit_id', $produit_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new CategorieProduit($arr);
        if ($obj->save()) return $obj;
        else return new CategorieProduit();
    }

    public static function supprimer($id)
    {
        $obj = CategorieProduit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
