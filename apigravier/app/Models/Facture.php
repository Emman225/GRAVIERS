<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facture extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'facture';
    protected $fillable = [
        "numero",
        "numero_fne",
        "user_id",
        "montant",
        "statut",
        "service_id",
        "service",
    ];

    public static function lire($id)
    {
        $obj = Facture::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Facture();
    }


    public static function lireSurGestionnaire($idUser)
    {
        $obj = Facture::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Facture();
    }

    public static function liste($service_id = null, $service = null, $client_id = null, $statut = null)
    {
        return Facture::orderBy('id', "desc")
        ->when($service_id, function ($query) use ($service_id) {
            $query->where('service_id', $service_id);
        })
        ->when($service, function ($query) use ($service) {
            $query->where('service', $service);
        })
        ->when($client_id, function ($query) use ($client_id) {
            $query->where('client_id', $client_id);
        })
        ->whereIn('statut', $statut)
        ->get();
    }

    public static function lireSurIds($ids)
    {
        return Facture::orderBy('id', "desc")
            ->where('statut', Help::$STATUT_ACTIF)
            ->whereIn('id', $ids)
            ->get();
    }
}
