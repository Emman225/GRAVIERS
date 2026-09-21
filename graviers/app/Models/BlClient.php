<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Commande;
class BlClient extends Model
{
    use HasFactory;

    protected $table = 'bl_client';
    protected $fillable = [
        'numero',
        'client_id',
        'montant',
        'fichier',
        'commande_id',
        // Un bon peut être rattaché à une COMMANDE ou à une DEMANDE DE
        // LIVRAISON. Deux colonnes distinctes, jamais une seule colonne
        // polymorphe : chaque lien garde sa contrainte d'intégrité.
        'demande_livraison_id',
        // ... ou à une LOCATION (09/09/2026), même règle : une colonne par lien.
        'location_id',
    ];

    public function commande(){
        return $this->belongsTo(Commande::class,'commande_id');
    }

    public function location(){
        return $this->belongsTo(Location::class,'location_id');
    }
    public function demandeLivraison(){
        return $this->belongsTo(DemandeLivraison::class,'demande_livraison_id');
    }
}
