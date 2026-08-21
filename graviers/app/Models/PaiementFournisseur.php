<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\TraceLesValidations;

class PaiementFournisseur extends Model
{
    use SoftDeletes, TraceLesValidations;

    protected $table = 'paiement_fournisseur';

    protected $fillable = [
        'date_paiement',
        'enlevement_id',
        // Renseigne quand le reglement decoule d'une demande de paiement
        // du fournisseur : ce lien evite de retrancher deux fois le meme
        // montant du solde (cf. Fournisseur::soldeCalcule).
        'demande_paiement_id',
        'fournisseur_id',
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
    ];

    protected $casts = [
        'date_paiement' => 'date',
    ];

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function enlevement()
    {
        return $this->belongsTo(Enlevement::class, 'enlevement_id');
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
