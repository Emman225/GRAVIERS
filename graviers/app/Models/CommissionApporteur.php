<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionApporteur extends Model
{
    use HasFactory;

    protected $table = 'commission_apporteur';
    protected $fillable = [
        'apporteur_id',
        'commande_id',
        'montant',
        'statut',
        'type_affaire',
        'numero',
        'date_echeance',
        'statut_commission',
        'observations',
    ];

    protected $casts = [
        'date_echeance' => 'date',
    ];

    public function paiements()
    {
        return $this->hasMany(PaiementApporteur::class, 'commission_id');
    }

    /**
     * Montant payé sur cette commission (somme des paiements_apporteur validés).
     */
    public function montantPayeCommission(): float
    {
        return (float) PaiementApporteur::where('commission_id', $this->id)
            ->where('statut', 1)->sum('montant');
    }

    public function resteAPayerCommission(): float
    {
        return max(0, (float) $this->montant - $this->montantPayeCommission());
    }

    /**
     * Statut de la commission selon les règles Excel :
     * - statut_commission manuel (Annulée) prioritaire
     * - sinon : Payée (paye>=total) / Partiellement due (paye>0) / Due (encaissé client>=total) / En attente paiement client
     */
    public function statutCommissionCalcule(): string
    {
        if (!empty($this->statut_commission)) {
            return $this->statut_commission;
        }
        $paye  = $this->montantPayeCommission();
        $total = (float) $this->montant;

        if ($total > 0 && $paye >= $total) {
            return 'Payée';
        }
        if ($paye > 0) {
            return 'Partiellement due';
        }

        // commande_id est POLYMORPHE : id de LOCATION quand type_affaire = LOCATION.
        // L'ancienne version cherchait toujours dans commande -> une commission de
        // location restait "En attente paiement client" pour toujours (impayable).
        if ($this->type_affaire === 'LOCATION') {
            $loc = \App\Models\Location::find($this->commande_id);
            if ($loc) {
                // Convention location.statut : 3 = paiement soldé, 2 = partiel.
                if ($loc->statut == 3) {
                    return 'Due';
                }
                $locPaye = (float) \App\Models\LignePaiement::where('service', 'LOCATION')
                    ->where('service_id', $loc->id)
                    ->where('statut', 1)
                    ->sum('montant');
                if ($locPaye > 0) {
                    return 'Partiellement due';
                }
            }
            return 'En attente paiement client';
        }

        // Vérifier si le client a payé sa commande
        $cmd = $this->commande;
        if ($cmd) {
            $cmdPaye   = $cmd->montantPayeComptant();
            $cmdTotal  = (float) ($cmd->montant_total ?? 0);
            if ($cmdTotal > 0 && $cmdPaye >= $cmdTotal) {
                return 'Due';
            }
            if ($cmdPaye > 0) {
                return 'Partiellement due';
            }
        }
        return 'En attente paiement client';
    }

    public static function liste($commande_id = null, $apporteur_id = null, $type_affaire = null)
    {
        return CommissionApporteur::selectRaw("commission_apporteur.*, users.nom_prenoms, apporteur.code")
        ->orderBy('id', 'desc')
        ->when($commande_id, function ($query) use ($commande_id) {
            $query->where('commande_id', $commande_id);
        })
        ->when($apporteur_id, function ($query) use ($apporteur_id) {
            $query->where('apporteur_id', $apporteur_id);
        })
        ->when($type_affaire, function ($query) use ($type_affaire) {
            $query->where('type_affaire', $type_affaire);
        })
        ->join('apporteur', 'apporteur.id', '=', 'commission_apporteur.apporteur_id')
        ->join('users', 'users.id', '=', 'apporteur.user_id')
        ->where('commission_apporteur.statut', Help::$STATUT_ACTIF)
        ->limit(3000)
        ->paginate(10);
    }

    public function commande()
    {
        return $this->belongsTo(Commande::class,'commande_id');
    }

    public function apporteur(){
        return $this->belongsTo(Apporteur::class);
    }


}
