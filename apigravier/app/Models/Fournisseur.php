<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Models\Enlevement;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Produit;
use Cviebrock\EloquentSluggable\Sluggable;

class Fournisseur extends Model
{
    use HasFactory, SoftDeletes ;
    protected $table = 'fournisseur';
    protected $fillable = [
        'user_id',
        'nom_prenoms',
        'nom',
        'prenom',
        'email',
        'contact1',
        'contact2',
        'contact',
        'adresse_geo',
        'adresse_postale',
        'adresse',
        'longitude',
        'latitude',
        'statut',
        'solde'
    ];



    public static function lire($id)
    {
        $obj = Fournisseur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Fournisseur();
    }
    public function produits(){
        return $this->belongsToMany(Produit::class,'stock_produit')->withPivot('qte','prix');
    }
    public function enlevements(){
        return $this->hasMany(Enlevement::class,'fournisseur_id');
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public static function lireSurUser($idUser)
    {
        $obj = Fournisseur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Fournisseur();
    }

    public static function liste()
    {
        return Fournisseur::orderBy('nom_prenoms', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Fournisseur($arr);
        if ($obj->save()) return $obj;
        else return new Fournisseur();
    }

    public static function supprimer($id)
    {
        $obj = Fournisseur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

}
