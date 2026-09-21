<?php

namespace App\Traits;

use App\Models\DemandePaiement;
use App\Models\User;

/**
 * LE CIRCUIT « À PAYER → PREUVE JOINTE → EFFECTUÉE » D'UN RÈGLEMENT (point 20,
 * étendu aux règlements de dettes le 09/09/2026).
 *
 * Porté par PaiementFournisseur, PaiementLivreur et PaiementApporteur. Les
 * états sont ceux des demandes de paiement (DemandePaiement::A_PAYER…), pour
 * que les espaces des partenaires et leurs applications lisent la même chose.
 *
 *   statut 2                      → en attente de la 2e validation
 *   statut 1 + etat A_PAYER       → validé deux fois, virement à faire
 *   statut 1 + etat PREUVE_JOINTE → preuve téléversée, à finaliser
 *   statut 1 + etat EFFECTUEE     → opération effectuée, vue par le partenaire
 *   statut 1 + etat null          → règlement d'avant le circuit : « Payé »
 */
trait CircuitPreuveReglement
{
    public function estValideDeuxFois(): bool
    {
        return (int) $this->statut === 1 && !empty($this->user_valide2_id);
    }

    /** Validé deux fois, versement en cours (avec ou sans preuve). */
    public function estAPayer(): bool
    {
        return (int) $this->statut === 1
            && in_array($this->etat_reglement, [DemandePaiement::A_PAYER, DemandePaiement::PREUVE_JOINTE], true);
    }

    public function estEffectuee(): bool
    {
        return (int) $this->statut === 1 && $this->etat_reglement === DemandePaiement::EFFECTUEE;
    }

    /** La preuve peut être jointe (ou remplacée) tant que le règlement n'est pas effectué. */
    public function peutJoindrePreuve(): bool
    {
        return $this->estAPayer();
    }

    /** La finalisation exige une preuve jointe. */
    public function peutFinaliser(): bool
    {
        return (int) $this->statut === 1
            && $this->etat_reglement === DemandePaiement::PREUVE_JOINTE
            && !empty($this->preuve_paiement);
    }

    /** Libellé prêt à afficher, pour le back-office comme pour les partenaires. */
    public function libelleReglement(): string
    {
        if ((int) $this->statut === 2) {
            return 'En attente de validation';
        }
        if ((int) $this->statut !== 1) {
            return 'Annulé';
        }

        return match ($this->etat_reglement) {
            DemandePaiement::EFFECTUEE     => 'Effectuée',
            DemandePaiement::PREUVE_JOINTE => 'À payer — preuve jointe',
            DemandePaiement::A_PAYER       => 'À payer',
            // Règlements validés AVANT le circuit de preuve : rien ne change pour eux.
            default                        => 'Payé',
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
}
