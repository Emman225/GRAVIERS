<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DemandeAnnulationCommande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'demande_annulation_commande';
    protected $fillable = [
        'client_id',
        'user_id',
        'commande_id',
        'motif',
        'est_traite',
        'note',
        'statut',
    ];

    public static function lireSurCle($client_id, $commande_id){
        $obj = DemandeAnnulationCommande::where('client_id', $client_id)
        ->where('commande_id', $commande_id)
        ->where('statut', Help::$STATUT_ACTIF)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DemandeAnnulationCommande();
    }
}
