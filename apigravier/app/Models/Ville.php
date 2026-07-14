<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ville extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'ville';
    protected $fillable = [
        'nom',
        'pays_id',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = Ville::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Ville();
    }




    public static function liste($pays_id = null)
    {
        return Ville::orderBy('nom', 'asc')
            ->when($pays_id, function ($query) use ($pays_id) {
                $query->where('pays_id', $pays_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Ville($arr);
        if ($obj->save()) return $obj;
        else return new Ville();
    }

    public static function supprimer($id)
    {
        $obj = Ville::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    public function adresse(){
        return $this->hasOne(AdresseLivraison::class);
    }

    public function pays(){
        return $this->belongsTo(Pays::class);
    }
}
