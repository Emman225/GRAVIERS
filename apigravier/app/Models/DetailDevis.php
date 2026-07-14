<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailDevis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_devis';
    protected $fillable = [
        "produit_id",
        "devis_id",
        "qte",
        "prix",
        "statut",

    ];

    public static function lire($id)
    {
        $obj = DetailDevis::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailDevis();
    }

    public static function lireCle($produit_id, $devis_id)
    {
        $obj = DetailDevis::where('produit_id', $produit_id)
        ->where('devis_id', $devis_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailDevis();
    }

    public static function liste($client_id = null, $devis_id = null, $produit_id = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return DetailDevis::distinct()
            ->selectRaw("detail_devis.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'produit.id', '=', 'detail_devis.produit_id')
            ->join('devis', 'devis.id', '=', 'detail_devis.devis_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('detail_devis.produit_id', $produit_id);
            })
            ->when($devis_id, function ($query) use ($devis_id) {
                $query->where('detail_devis.devis_id', $devis_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('devis.client_id', $client_id);
            })
            ->where('detail_devis.statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new DetailDevis($arr);
        if ($obj->save()) return $obj;
        else return new DetailDevis();
    }

    public static function supprimer($id)
    {
        $obj = DetailDevis::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    public function devis(){
        return $this->belongsTo(Devis::class);
    }

    public function produit(){
        return $this->belongsTo(Produit::class);
    }



}
