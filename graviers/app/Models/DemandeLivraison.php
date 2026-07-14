<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Livraison;
use App\Models\DetailLivraison;

class DemandeLivraison extends Model
{
    use HasFactory;

    protected $table = "demande_livraison";
    protected $fillable = [
        'numero',
        'libelle',
        'description',
        'montantTotal',
        'client_id',
        'adresse_livraison_pec_id',
        'adresse_livraison_dest_id',
        'date_livraison',
        'date_fin_livraison',
        'remise',
        'mode_paiement_id',
        'type_livraison_id',
        'etat_commande',
        'statut',

    ];

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function priseEnCharge(){
        return $this->belongsTo(AdresseLivraison::class,'adresse_livraison_pec_id');
    }
    public function destination(){
        return $this->belongsTo(AdresseLivraison::class,'adresse_livraison_dest_id');
    }

    public function detailLivraison(){
        return $this->hasMany(DetailLivraison::class);
    }

    public function livraisons(){
        return $this->hasManyThrough(Livraison::class,DetailLivraison::class);
    }

    public function TypeLivraison(){
        return $this->belongsTo(TypeLivraison::class);
    }
    
    public function ModeDePaiement(){
        return $this->belongsTo(ModePaiement::class,'mode_paiement_id');
    }



}
