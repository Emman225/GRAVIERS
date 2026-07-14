<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Ville;
use App\Models\Pays;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdresseLivraison extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'adresse_livraison';
    protected $fillable = [
        'client_id',
        'pays_id',
        'ville_id',
        'complement_adresse',
        'defaut',
        'longitude',
        'latitude',
        'affichage',
        'statut',
    ];

    public static function lire($id){
        $obj = AdresseLivraison::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new AdresseLivraison();
    }
    public function ville(){
        return $this->belongsTo(Ville::class)->withDefault(['nom'=>'']);
    }
    public function pays(){
        return $this->belongsTo(Pays::class)->withDefault(['nom'=>'']);
    }

    public static function lireDefautSurClient($idClient){
        $obj = AdresseLivraison::where('client_id', $idClient)
        ->where('defaut', true)
        ->where('statut', Help::$STATUT_ACTIF)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new AdresseLivraison();
    }

    public static function liste($idClient){
        return AdresseLivraison::where('client_id', $idClient)
        ->where('statut', Help::$STATUT_ACTIF)
        ->get();
    }

    public static function enregistrer(array $adresse){
        $obj = new AdresseLivraison($adresse);
        if ($obj->save()) return $obj;
        else return new AdresseLivraison();
    }

    public static function supprimer($id){
        $obj = AdresseLivraison::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public static function definirDefaut($idAdresse, $idClient){

        $adresses = AdresseLivraison::liste($idClient);
        $adresses->update(['defaut'=> false]);

        $obj = AdresseLivraison::lire($idAdresse);
        $obj->defaut = true;
        $obj->save();

        return $obj;
    }


}
