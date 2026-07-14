<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reduction extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'reduction';
    protected $fillable = [
        'code',
        'libelle',
        'debut',
        'fin',
        'est_utilise',
        'taux_reduction',
        'montant_reduction',
        'devis_id',
        'client_id',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = Reduction::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Reduction();
    }

    public static function lireCode($code)
    {
        $date = date('Y-m-d');
        $obj = Reduction::where('code', $code)
        // ->where('client_id', $client_id)
        ->where('statut', Help::$STATUT_ACTIF)
        ->where('debut', '<=', $date)
        ->where('fin', '>=', $date)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Reduction();
    }

    public static function liste($client_id = null)
    {
        return Reduction::orderBy('libelle', 'asc')
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Reduction($arr);
        if ($obj->save()) return $obj;
        else return new Reduction();
    }

    public static function supprimer($id)
    {
        $obj = Reduction::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
