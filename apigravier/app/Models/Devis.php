<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Paiement;
use App\Models\Commande;
use App\Models\DetailDevis;
use App\models\LignePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'devis';
    protected $fillable = [
        "numero",
        "client_id",
        "montant",
        "statut",
        "libelle",
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

    public static function liste($client_id = null)
    {
        return Devis::selectRaw('devis.*, adresse_livraison.complement_adresse as adresse_livraison')
        ->when($client_id, function ($query) use ($client_id) {
            $query->where('devis.client_id', $client_id);
        })
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'devis.adresse_livraison_id')
            ->where('devis.statut', Help::$STATUT_ACTIF)
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

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
    public function commandes()
    {
        return $this->hasOne(Commande::class);
    }
    public function detailDevis()
    {
        return $this->hasMany(DetailDevis::class);
    }

    /*public function lignePaiement(): hasManyThrough
    {
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }*/
}
