<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'contact';
    protected $fillable = [
        'nom_prenoms',
        'email',
        'telephone',
        'sujet',
        'message',
        'lu',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = Contact::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Contact();
    }

    public static function liste($statut)
    {
        return Contact::orderBy('lu', 'desc')
            ->when($statut, function ($query) use ($statut) {
                $query->where('statut', $statut);
            })
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Contact($arr);
        if ($obj->save()) return $obj;
        else return new Contact();
    }

    public static function activerDesactiver($id)
    {
        $obj = Contact::lire($id);
        $obj->statut = $obj->statut == Help::$STATUT_INACTIF ? Help::$STATUT_ACTIF : Help::$STATUT_INACTIF;
        $obj->save();
        return $obj;
    }

    public static function marquerLu($id)
    {
        $obj = Contact::lire($id);
        $obj->lu = true;
        $obj->save();
        return $obj;
    }

    public static function supprimer($id)
    {
        $obj = Contact::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
