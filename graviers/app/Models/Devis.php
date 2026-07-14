<?php

namespace App\Models;

use Help;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\DetailDevis;
use App\Models\ModePaiement;
use App\Models\LignePaiement;
use App\Models\AdresseLivraison;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'devis';
    protected $fillable = [
        'numero',
        'client_id',
        'adresse_livraison_id',
        'libelle',
        'montant',
        'tva',
        'statut',
        'cout_livraison',
        'mode_paiement_id',
        'cout_reduction',
        'montant_ht',
        'service',
        'type_livraison_id',
        'date_livraison',
    ];

    public static function lire($id)
    {
        $obj = Devis::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Devis();
    }

    public static function lireNumero($numero)
    {
        $obj = Devis::where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Devis();
    }

    public function adresseLivraison(){
        return $this->belongsTo(AdresseLivraison::class, 'adresse_livraison_id');
    }

    public function modePaiement(){
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    public function typeLivraison(){
        return $this->belongsTo(TypeLivraison::class, 'type_livraison_id');
    }

    public static function liste($client_id = null, $adresse_livraison_id = null)
    {
        return Devis::when($client_id, function ($query) use ($client_id) {
            $query->where('client_id', $client_id);
        })
            ->when($adresse_livraison_id, function ($query) use ($adresse_livraison_id) {
                $query->where('adresse_livraison_id', $adresse_livraison_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Devis($arr);
        if ($obj->save()) return $obj;
        else return new Devis();
    }

    public static function supprimer($id)
    {
        $obj = Devis::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function paiements(){
        return $this->hasMany(Paiement::class);
    }

    public function lignes(){
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }
    public function commandes(){
        return $this->hasOne(Commande::class);
    }
    public function detailDevis(){
        return $this->hasMany(DetailDevis::class);
    }

    public function client (){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function produits(){
        return $this->belongsToMany(Produit::class,'detail_devis')->withPivot('id','qte','prix','statut','deleted_at');
    }

    public function reduction(){
        return $this->hasOne(Reduction::class );
    }

    /*public function lignePaiement(): hasManyThrough
    {
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }*/
}
