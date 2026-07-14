<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DemandePaiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'demande_paiement';
    protected $fillable = [
        'montant',
        'mode_paiement_id',
        'user_id',
        'user_valide_id',
        'date_validation',
        'paye',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = DemandePaiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DemandePaiement();
    }

    public static function liste($user_id = null, $mode_paiement_id = null)
    {
        return DemandePaiement::selectRaw("demande_paiement.*, mode_paiement.libelle as mode_paiement")
            ->orderBy('id', 'desc')
            ->join('mode_paiement', 'mode_paiement.id', '=', 'demande_paiement.mode_paiement_id')
            ->when($user_id, function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->when($mode_paiement_id, function ($query) use ($mode_paiement_id) {
                $query->where('demande_paiement.mode_paiement_id', $mode_paiement_id);
            })
            ->where('demande_paiement.statut', Help::$STATUT_ACTIF)
            ->limit(1000)
            ->get();
    }

    public static function listeEffectue($user_id = null)
    {
        return DemandePaiement::selectRaw("demande_paiement.*, mode_paiement.libelle as mode_paiement")
            ->orderBy('id', 'desc')
            ->join('mode_paiement', 'mode_paiement.id', '=', 'demande_paiement.mode_paiement_id')
            ->when($user_id, function ($query) use ($user_id) {
                $query->where('user_id', $user_id);
            })
            ->where('demande_paiement.paye', 1)
            ->where('demande_paiement.statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function verifierNonPaye($user_id)
    {
        $obj = DemandePaiement::where('user_id', $user_id)
            ->where('paye', 0)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DemandePaiement();
    }
}
