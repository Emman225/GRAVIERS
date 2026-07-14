<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailCommande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_commande';
    protected $fillable = [
        'produit_id',
        'commande_id',
        'qte',
        'prix',
        'etat_livraison',
        'statut',
        'qte_livree',
        'prix_fournisseur',
        'reference',
    ];

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = DetailCommande::selectRaw("detail_commande.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image")
            ->join('produit', 'produit.id', '=', 'detail_commande.produit_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->where('detail_commande.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailCommande();
    }

    public static function lireCle($produit_id, $commande_id)
    {
        $obj = DetailCommande::where('produit_id', $produit_id)
            ->where('commande_id', $commande_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailCommande();
    }

    public static function liste($produit_id = null, $commande_id = null, $client_id = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return DetailCommande::distinct()
            ->selectRaw("detail_commande.*,
        produit.reference,
        produit.nom,
        produit.unite,
        produit.description,
        produit.prix_moyen,
        produit.prix_reduction,
        concat('$url',image_produit.image) as image,
        bl_client.numero as numero_bl,
        bl_client.fichier as fichier_bl")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'produit.id', '=', 'detail_commande.produit_id')
            ->join('commande', 'commande.id', '=', 'detail_commande.commande_id')
            ->leftJoin('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('detail_commande.produit_id', $produit_id);
            })
            ->when($commande_id, function ($query) use ($commande_id) {
                $query->where('detail_commande.commande_id', $commande_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('commande.client_id', $client_id);
            })
            ->where('detail_commande.statut', Help::$STATUT_ACTIF)
            ->where('produit.type_affaire', Help::$VENTE)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new DetailCommande($arr);
        if ($obj->save()) return $obj;
        else return new DetailCommande();
    }

    public static function supprimer($id)
    {
        $obj = DetailCommande::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
