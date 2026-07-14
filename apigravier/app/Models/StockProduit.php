<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockProduit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'stock_produit';
    protected $fillable = [
        'fournisseur_id',
        'produit_id',
        'qte',
        'prix',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = StockProduit::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new StockProduit();
    }

    public static function liste($fournisseur_id = null, $produit_id = null)
    {
        return StockProduit::when($fournisseur_id, function ($query) use ($fournisseur_id) {
                $query->where('fournisseur_id', $fournisseur_id);
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('produit_id', $produit_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new StockProduit($arr);
        if ($obj->save()) return $obj;
        else return new StockProduit();
    }

    
    public static function supprimer($id)
    {
        $obj = StockProduit::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
