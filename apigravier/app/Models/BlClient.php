<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlClient extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'bl_client';
    protected $fillable = [
        "numero",
        "client_id",
        "commande_id",
        "fichier",
        "montant",
        "statut",
    ];

    public static function lire($id)
    {
        $obj = BlClient::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new BlClient();
    }


    public static function lireSurCommande($idCommande)
    {
        $obj = BlClient::where('commande_id', $idCommande)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new BlClient();
    }

    public static function liste($client_id = null, $commande_id = null)
    {
        return BlClient::orderBy('id', 'desc')
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->when($commande_id, function ($query) use ($commande_id) {
                $query->where('commande_id', $commande_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }
}
