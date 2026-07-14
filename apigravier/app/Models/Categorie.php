<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Categorie extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'categorie';
    protected $fillable = [
        'image',
        'icon',
        'nom',
        'description',
        'parent_id',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = Categorie::find($id);
        $obj->image = Help::urlFichier($obj->image);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Categorie();
    }
    public function produit()
    {
        return $this->hasMany(Produit::class);
    }

    public static function liste()
    {
        $arr = Categorie::orderBy('nom', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
        foreach ($arr as $c) {
            $c->image = Help::urlFichier($c->image);
        }
        return $arr;
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Categorie($arr);
        if ($obj->save()) return $obj;
        else return new Categorie();
    }

    public static function supprimer($id)
    {
        $obj = Categorie::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function produits()
    {
        return $this->hasMany(Produit::class);
    }
}
