<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicule extends Model
{
    use HasFactory;
    protected $table = 'vehicule';
    protected $fillable = [
        "immatriculation",
        "nom",
        "description",
        "type_vehicule_id",
        "livreur_id",
        "statut",
        "disponible",
        "capacite",
        "marque",
        "modele",
        'deleted_at'
    ];

    public static function lire($id)
    {
        $obj = Vehicule::find($id);
        //$obj = Vehicule::where('id',$id)->where('deleted_at',null)->get();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Vehicule();
    }

    public static function liste($livreur_id = null, $type_vehicule_id = null)
    {
        return Vehicule::orderBy('vehicule.nom')
        ->selectRaw('vehicule.*, type_vehicule.libelle as type_vehicule')
        ->join('type_vehicule', 'type_vehicule.id', '=', 'vehicule.type_vehicule_id')
            ->when($livreur_id, function ($query) use ($livreur_id) {
                $query->where('vehicule.livreur_id', $livreur_id);
            })
            ->when($type_vehicule_id, function ($query) use ($type_vehicule_id) {
                $query->where('vehicule.type_vehicule_id', $type_vehicule_id);
            })
            ->where('vehicule.statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public function type(){
        return $this->belongsTo(TypeVehicule::class, 'type_vehicule_id')->withDefault(['libelle'=>'']);
    }

   public function livreur()
   {
       return $this->belongsTo(Livreur::class, 'livreur_id', 'id')->withDefault([]);
   }
}
