<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrixPersonnalise extends Model
{
    protected $table = 'prix_personnalises';

    protected $fillable = [
        'client_id',
        'produit_id',
        'prix',
    ];

    public static function listeSurClient($clientId)
    {
        return self::where('client_id', $clientId)->pluck('prix', 'produit_id')->toArray();
    }
}
