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
        'deleted_at'
    ];

    public static function lire($id)
    {
        $obj = Categorie::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Categorie();
    }
    public function produits(){
        return $this->belongsToMany(Produit::class,'categorie_produit')->withPivot('categorie_id','produit_id');
    }

    public static function liste($parent_id = null)
    {
        return Categorie::orderBy('nom', 'asc')
            ->when($parent_id, function ($query) use ($parent_id) {
                $query->where('parent_id', $parent_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
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

    // public function produits (){
    //     return $this->hasMany(Produit::class);
    // }
}
