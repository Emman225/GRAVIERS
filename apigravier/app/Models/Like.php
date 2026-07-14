<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;
    protected $table = 'likes';
    protected $fillable = [
        'client_id',
        'produit_id'
    ];

    public static function liste($client_id)
    {
        $url = Help::$URL_BASE_FICHIER;
        return Like::distinct()
            ->selectRaw("produit.*, concat('$url',image_produit.image) as image")
            ->orderBy('produit.nom', 'asc')
            ->join('produit', 'likes.produit_id', '=', 'produit.id')
            ->join('image_produit', function ($join) {
                $join->where(function ($q) {
                    $q->on('image_produit.produit_id', '=', 'produit.id');
                    $q->where('image_produit.defaut', true);
                    $q->where('image_produit.statut', Help::$STATUT_ACTIF);
                });
            })
            ->where('likes.client_id', $client_id)
            ->where('produit.statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function lireCle($client_id, $produit_id)
    {
        $obj = Like::where('client_id', $client_id)
            ->where('produit_id', $produit_id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Like();
    }
}
