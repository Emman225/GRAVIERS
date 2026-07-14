<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandeCompteClientATerme extends Model
{
    use HasFactory;
    protected $table = "demande_compte_client_a_terme";
    protected $fillable = [
        "objet",
        "description",
        "client_id",
        "approuve",
        "user_id",
        "statut",
    ];

    public static function lireSurClient($client_id){
        $obj = DemandeCompteClientATerme::where('client_id', $client_id)
        ->where('approuve', false)
        ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DemandeCompteClientATerme();
    }
}
