<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RetourProduit extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'retour_produit';
    protected $fillable = [
        "motif",
        "client_id",
        "detail_commande_id",
        "statut",
        "user_id",
        "user_paie_id",
        "observation_reception",
        "rembourse",
        "date_retour",
        "date_reception",
        "date_rembourssement",
    ];

    public static function lireSurDetailCommande($idDetail, $client_id)
    {
        $obj = RetourProduit::where('detail_commande_id', $idDetail)
        ->where('client_id', $client_id)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new RetourProduit();
    }

    public static function listeSurIdsDetailCommande($ids, $client_id)
    {
        return RetourProduit::whereIn('detail_commande_id', $ids)
        ->where('client_id', $client_id)
        ->get();
    }
}
