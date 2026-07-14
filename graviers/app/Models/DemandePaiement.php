<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class DemandePaiement extends Model
{
    use HasFactory;

    protected $table = "demande_paiement";

    protected $fillable = [
        'montant',
        'numero',
        'mode_paiement_id',
        'user_id',
        'user_valide_id',
        'user_valide2_id',
        'date_validation',
        'paye',
        'numero_compte'
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function modePaiement(){
        return $this->belongsTo(ModePaiement::class);
    }

    public function userValide(){
        return $this->belongsTo(User::class);
    }
}
