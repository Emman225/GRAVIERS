<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * L'HISTORIQUE D'UNE AVANCE (point 19, 07/09/2026) : DEPOT ou DEDUCTION.
 * L'API n'écrit que des DEDUCTION, quand une commande mobile « en agence »
 * s'impute sur une avance.
 */
class MouvementAvance extends Model
{
    public const DEPOT     = 'DEPOT';
    public const DEDUCTION = 'DEDUCTION';

    protected $table = 'mouvement_avance';

    protected $fillable = [
        'avance_client_id', 'client_id', 'type', 'montant', 'commande_id',
        'paiement_id', 'user_id', 'libelle',
    ];
}
