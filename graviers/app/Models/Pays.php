<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pays extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'pays';
    protected $fillable = [
        'nom',
        'code',
        'indicatif',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = Pays::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Pays();
    }

    public static function lireCode($code)
    {
        $obj = Pays::where('code', $code)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Pays();
    }

    public static function liste()
    {
        return Pays::orderBy('nom', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Pays($arr);
        if ($obj->save()) return $obj;
        else return new Pays();
    }

    public static function supprimer($id)
    {
        $obj = Pays::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
