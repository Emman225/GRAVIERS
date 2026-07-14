<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\Fournisseur;
use App\Models\Livreur;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enlevement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'enlevement';
    protected $fillable = [
        'fournisseur_id',
        'livraison_id',
        'qte',
        'vehicule_id',
        'produit_id',
        'livreur_id',
        'statut',
        'code_enleve',
        'fournisseur_validation',
        'livreur_validation',
        'prix_fournisseur',
        'fature_id',
        'qte_servi',
        'gestionnaire_id',
    ];

    public static function lire($id)
    {
        $obj = Enlevement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Enlevement();
    }

    public static function liste($fournisseur_id = null, $livraison_id = null, $produit_id = null, $vehicule_id = null)
    {
        return Enlevement::when($fournisseur_id, function ($query) use ($fournisseur_id) {
            $query->where('fournisseur_id', $fournisseur_id);
        })
            ->when($livraison_id, function ($query) use ($livraison_id) {
                $query->where('livraison_id', $livraison_id);
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('produit_id', $produit_id);
            })
            ->when($vehicule_id, function ($query) use ($vehicule_id) {
                $query->where('vehicule_id', $vehicule_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Enlevement($arr);
        if ($obj->save()) return $obj;
        else return new Enlevement();
    }

    public static function supprimer($id)
    {
        $obj = Enlevement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function livraison(){
        return $this->belongsTo(Livraison::class, 'livraison_id');
    }

    public function fournisseur(){
        return $this->belongsTo(Fournisseur::class,'fournisseur_id');
    }
    public function produit(){
        return $this->belongsTo(Produit::class);
    }
    public function livreur(){
        return $this->belongsTo(Livreur::class);
    }
}

