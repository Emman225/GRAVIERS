<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\TraceLesValidations;

class PaiementLivreur extends Model
{
    use \App\Traits\CircuitPreuveReglement;
    use SoftDeletes, TraceLesValidations;

    protected $table = 'paiement_livreur';

    protected $fillable = [
        'date_paiement',
        'livraison_id',
        // Renseigne quand le reglement decoule d'une demande de paiement
        // du livreur : ce lien evite de retrancher deux fois le meme
        // montant du solde (cf. Livreur::soldeCalcule).
        'demande_paiement_id',
        'livreur_id',
        'montant',
        'mode_paiement_id',
        'reference',
        'notes',
        'user_id',
        'agence_id',
        'statut',
        // Double validation (cf. trait DoubleValidationPaiement)
        'user_valide_id',
        'user_valide2_id',
        'date_validation_1',
        'date_validation_2',
        // Circuit après la 2e validation (point 20, 09/09/2026) : « À payer »,
        // preuve jointe, « Effectuée ». Suivi seulement : l'imputation reste à
        // la 2e validation.
        'etat_reglement',
        'preuve_paiement',
        'date_preuve',
        'user_preuve_id',
        'date_effectuee',
        'user_effectuee_id',
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function livreur()
    {
        return $this->belongsTo(Livreur::class, 'livreur_id');
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class, 'livraison_id');
    }

    public function modePaiement()
    {
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
