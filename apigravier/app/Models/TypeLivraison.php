<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeLivraison extends Model
{
    use HasFactory;
    protected $table = 'type_livraison';
    public static function liste()
    {
        return TypeLivraison::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }
    public static function lire($id)
    {
        $obj = TypeLivraison::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new TypeLivraison();
    }
}
