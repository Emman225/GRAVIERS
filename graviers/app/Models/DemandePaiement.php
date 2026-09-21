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
        'numero_compte',
        // Circuit après la 2e validation (point 20) : « À payer », preuve
        // jointe, « Effectuée ». Suivi seulement : l'imputation reste à la
        // 2e validation.
        'etat_reglement',
        'preuve_paiement',
        'date_preuve',
        'user_preuve_id',
        'date_effectuee',
        'user_effectuee_id',
    ];

    public const A_PAYER        = 'A_PAYER';
    public const PREUVE_JOINTE  = 'PREUVE_JOINTE';
    public const EFFECTUEE      = 'EFFECTUEE';

    /** La demande est validée, le versement est en cours (preuve ou non). */
    public function estAPayer(): bool
    {
        return (int) $this->paye === 1
            && in_array($this->etat_reglement, [self::A_PAYER, self::PREUVE_JOINTE], true);
    }

    public function estEffectuee(): bool
    {
        return (int) $this->paye === 1 && $this->etat_reglement === self::EFFECTUEE;
    }

    /** Libellé prêt à afficher, pour le back-office comme pour les tiers. */
    public function libelleReglement(): string
    {
        if ((int) $this->paye === 2) {
            return 'Refusée';
        }
        if ((int) $this->paye !== 1) {
            return 'En attente';
        }

        return match ($this->etat_reglement) {
            self::EFFECTUEE     => 'Effectuée',
            self::PREUVE_JOINTE => 'À payer — preuve jointe',
            self::A_PAYER       => 'À payer',
            // Demandes validées AVANT le circuit de preuve : rien ne change pour elles.
            default             => 'Payée',
        };
    }

    /**
     * UN TROISIÈME ADMINISTRATEUR (sécurité, 09/09/2026) : la preuve et la
     * finalisation reviennent à un administrateur qui n'est ni le 1er ni le 2e
     * validateur. Quatre yeux valident, un cinquième et un sixième paient.
     */
    public function troisiemeAdministrateur(?User $user): bool
    {
        if (!$user || !in_array((int) $user->type_user_id, [1, 2], true)) {
            return false;
        }

        return (int) $user->id !== (int) ($this->user_valide_id ?? 0)
            && (int) $user->id !== (int) ($this->user_valide2_id ?? 0);
    }

    public function agentPreuve()
    {
        return $this->belongsTo(User::class, 'user_preuve_id');
    }

    public function agentEffectuee()
    {
        return $this->belongsTo(User::class, 'user_effectuee_id');
    }

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
