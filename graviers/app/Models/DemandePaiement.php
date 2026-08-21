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
        'agence_id',
        'user_valide_id',
        'user_valide2_id',
        'date_validation',
        'paye',
        // 1 = le solde du tiers a été débité à l'initiation (réservation) ;
        // 0 = il sera débité à la 2e validation (reglerDette, apporteur mobile).
        // Sans lui dans fillable, create([...]) l'ignorerait silencieusement.
        'solde_debite_initiation',
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

    /**
     * Agence d'où le règlement est sorti — celle de l'utilisateur qui l'a
     * enregistré. Un décaissement sans agence rendait le rapprochement de
     * caisse faux par construction : il ne voyait que les entrées.
     */
    public function agence()
    {
        return $this->belongsTo(\App\Models\Agence::class, 'agence_id');
    }
}
