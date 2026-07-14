<?php

namespace App\Models;

use App\Models\Livraison;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UniteProduit;

class DetailLivraison extends Model
{
    use HasFactory;

    protected $table = "detail_livraison";
    protected $fillable = [
        'nom_produit',
        'qte',
        'unite_produit_id' ,
        'demande_livraison_id',
        'etat_livraison',
        'statut',
        'cout_livraison_id',
        'description',

    ];

    public function coutLivraison(){
        return $this->belongsTo(CoutLivraison::class);
    }

    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function demandeLivraison(){
        return $this->belongsTo(DemandeLivraison::class);
    }

    public function uniteProduit(){
        return $this->belongsTo(UniteProduit::class);
    }
}
