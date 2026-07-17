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

    // $type_affaire (VENTE / LOCATION) : commande_id porte l'id d'une COMMANDE ou d'une
    // LOCATION selon le type — deux tables aux compteurs indépendants. Sans ce filtre,
    // une demande sur la commande n°5 bloquait toute demande sur la location n°5 du
    // même client (et inversement). Optionnel pour rester rétro-compatible.
    public static function lireSurCle($client_id, $commande_id, $type_affaire = null){
        $obj = DemandeAnnulationCommande::where('client_id', $client_id)
        ->where('commande_id', $commande_id)
        ->when($type_affaire, function ($query) use ($type_affaire) {
            $query->where('type_affaire', $type_affaire);
        })
        ->where('statut', Help::$STATUT_ACTIF)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DemandeAnnulationCommande();
    }
}
