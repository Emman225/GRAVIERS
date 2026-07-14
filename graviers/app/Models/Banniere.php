<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banniere extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'banniere';

    protected $fillable = [
        'titre',
        'sous_titre',
        'image',
        'num_ordre',
        'type_banniere',
        'date_heure_decompte',
        'statut',
        'deleted_at'
    ];

    public static function lire($id)
    {
        $obj = Banniere::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Banniere();
    }

    public static function liste($typeBanniere = null)
    {
        return Banniere::orderBy('num_ordre', 'asc')
            ->when($typeBanniere, function ($query) use ($typeBanniere) {
                $query->where('type_banniere', $typeBanniere);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Banniere($arr);
        if ($obj->save()) return $obj;
        else return new Banniere();
    }

    public static function supprimer($id)
    {
        $obj = Banniere::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
