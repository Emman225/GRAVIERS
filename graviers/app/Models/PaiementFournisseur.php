<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaiementFournisseur extends Model
{
    use SoftDeletes;

    protected $table = 'paiement_fournisseur';

    protected $fillable = [
        'date_paiement',
        'enlevement_id',
        'fournisseur_id',
        'montant',
        'mode_paiement_id',
        'reference',
        'notes',
        'user_id',
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
}
