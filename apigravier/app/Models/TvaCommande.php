<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TvaCommande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'tva_commande';
    protected $fillable = [
        'client_id',
        'commande_id',
        'montant',
        'statut',
        'type_affaire',
    ];

    public static function lireSurCommande($idCommande, $type_affaire)
    {
        $obj = TvaCommande::where('type_affaire', $type_affaire)
        ->where('commande_id', $idCommande)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new TvaCommande();
    }
}
