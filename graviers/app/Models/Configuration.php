<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;

    protected $table = "configuration";

    protected $fillable = [
        'taux_commission',
        'tva',
        'montant_point',
        'email_tresorier',
        'email_directeur_marketing',
        'devise',
        'gestionnaire1_id',
        'gestionnaire2_id',
        'prixKm',
        'cout_livraison_min',
        'raison_sociale',
        'ncc',
        'regime_imposition',
        'centre_impots',
        'rccm',
        'ref_bancaires',
        'cnps',
        'capital_social',
        'adresse_siege',
        'telephone',
        'email_entreprise',
        'nom_etablissement',
        'nom_pdv',
        'tonne_moyenne',
        'cout_liv_fixe',
        'delai_relance_standard',
        'seuil_alerte_retard',
        'delai_max_paiement_agence',
        'delai_annulation_auto',
        'frequence_paiement_livreur',
        'jour_paiement_livreur',
        'forfait_base_livreur',
        'taux_commission_standard',
        'delai_paiement_commission',
        'termes_conditions',
    ];

    public function gestionnaire1(){
        return $this->belongsTo(User::class, 'gestionnaire1_id')->withTrashed();
    }

    public function gestionnaire2(){
        return $this->belongsTo(User::class, 'gestionnaire2_id')->withTrashed();
    }

    /**
     * Renvoie la prochaine date de paiement livreur en fonction des paramètres :
     * - frequence_paiement_livreur : Quotidien | Hebdomadaire | Bimensuel | Mensuel
     * - jour_paiement_livreur : Lundi..Dimanche (utilisé en hebdomadaire)
     */
    public function prochainePaiementLivreur(?\Carbon\Carbon $base = null): \Carbon\Carbon
    {
        $base = ($base ?? \Carbon\Carbon::today())->copy()->startOfDay();
        $frequence = $this->frequence_paiement_livreur ?: 'Hebdomadaire';
        $jour      = $this->jour_paiement_livreur ?: 'Vendredi';

        switch ($frequence) {
            case 'Quotidien':
                return $base;

            case 'Bimensuel':
                $jour15 = $base->copy()->day(15);
                $finMois = $base->copy()->endOfMonth()->startOfDay();
                if ($base->lessThanOrEqualTo($jour15)) return $jour15;
                if ($base->lessThanOrEqualTo($finMois)) return $finMois;
                return $base->copy()->addMonth()->day(15);

            case 'Mensuel':
                $finMois = $base->copy()->endOfMonth()->startOfDay();
                return $base->lessThanOrEqualTo($finMois)
                    ? $finMois
                    : $base->copy()->addMonth()->endOfMonth()->startOfDay();

            case 'Hebdomadaire':
            default:
                $mapJours = [
                    'Lundi' => \Carbon\Carbon::MONDAY,
                    'Mardi' => \Carbon\Carbon::TUESDAY,
                    'Mercredi' => \Carbon\Carbon::WEDNESDAY,
                    'Jeudi' => \Carbon\Carbon::THURSDAY,
                    'Vendredi' => \Carbon\Carbon::FRIDAY,
                    'Samedi' => \Carbon\Carbon::SATURDAY,
                    'Dimanche' => \Carbon\Carbon::SUNDAY,
                ];
                $cible = $mapJours[$jour] ?? \Carbon\Carbon::FRIDAY;
                if ($base->dayOfWeek === $cible) return $base;
                return $base->copy()->next($cible);
        }
    }
}
