<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agence extends Model
{
    use SoftDeletes;

    protected $table = 'agence';

    protected $fillable = [
        'code',
        'nom',
        'adresse',
        'telephone',
        'responsable',
        'statut',
    ];

    public function commandes()
    {
        return $this->hasMany(Commande::class, 'agence_id');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'agence_id');
    }
}
