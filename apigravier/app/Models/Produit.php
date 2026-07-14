<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Produit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'produit';
    protected $fillable = [
        'reference',
        'nom',
        'abreviation',
        'unite',
        'description',
        'prix_moyen',
        'prix_reduction',
        'meilleur_note',
        'statut',
        'type_affaire',
    ];

    public static function lire($id)
    {
        $obj = Produit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public static function lireReference($reference)
    {
        $obj = Produit::where('reference', $reference)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Produit();
    }

    public static function liste($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return Produit::distinct()
            ->selectRaw("produit.*, concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->leftJoin('categorie_produit', function ($join) use ($categories) {
                    $join->where(function ($q) use ($categories) {
                        $q->on('categorie_produit.produit_id', '=', 'produit.id');
                        $q->WhereIn('categorie_produit.categorie_id', $categories);
                    });
                });
            })
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->orWhereIn('produit.id', $produits);
            })
            ->when((count($montants) > 0), function ($query) use ($montants) {
                $query->orWhereIn('produit.prix_moyen', $montants);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get();
    }

    public static function listeSurCategorie($search = null, $limit = 100, $categories = [], $produits = [], $montants = [], $typeAffaire = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return Produit::distinct()
            ->selectRaw("produit.*, concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('produit.reference', $search);
                    $q->orWhere('produit.nom', 'LIKE', "%$search%");
                    $q->orWhere('produit.abreviation', 'LIKE', "%$search%");
                    $q->orWhere('produit.description', 'LIKE', "%$search%");
                });
            })
            ->when((count($categories) > 0), function ($query) use ($categories) {
                $query->join('categorie_produit', function ($join) use ($categories) {
                    $join->where(function ($q) use ($categories) {
                        $q->on('categorie_produit.produit_id', '=', 'produit.id');
                        $q->WhereIn('categorie_produit.categorie_id', $categories);
                    });
                });
            })
            ->when((count($produits) > 0), function ($query) use ($produits) {
                $query->orWhereIn('produit.id', $produits);
            })
            ->when((count($montants) > 0), function ($query) use ($montants) {
                $query->orWhereIn('produit.prix_moyen', $montants);
            })
            ->when($typeAffaire, function ($query) use ($typeAffaire) {
                $query->where('produit.type_affaire', $typeAffaire);
            })
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->limit($limit)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Produit($arr);
        if ($obj->save()) return $obj;
        else return new Produit();
    }

    public static function supprimer($id)
    {
        $obj = Produit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
