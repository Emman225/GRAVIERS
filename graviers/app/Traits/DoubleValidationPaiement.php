<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Trait commun pour la double validation des paiements en agence.
 *
 * Workflow :
 *  - 1er validateur (généralement un gestionnaire) : crée le paiement.
 *    Le paiement est "en attente de validation" (user_valide2_id NULL).
 *  - 2e validateur (un admin ≠ 1er) : finalise le paiement.
 *    Seul un paiement avec user_valide2_id NOT NULL est considéré effectif.
 *
 * Utilisation côté controller :
 *  1. Au moment de la création du paiement :
 *       $data = array_merge($data, $this->initierValidation());
 *  2. Pour valider un paiement existant (2e validateur) :
 *       $result = $this->validerPaiement($paiement);
 *       if ($result['ok']) { ... } else { return back()->with('error', $result['message']); }
 */
trait DoubleValidationPaiement
{
    /**
     * Renvoie les champs à injecter à la création d'un paiement
     * pour marquer la 1re validation (créateur).
     */
    protected function initierValidation(): array
    {
        return [
            'user_valide_id'    => Auth::id(),
            'user_valide2_id'   => null,
            'date_validation_1' => now(),
            'date_validation_2' => null,
        ];
    }

    /**
     * Effectue la 2e validation sur un paiement existant.
     * Vérifie : utilisateur connecté = admin ≠ 1er validateur, et paiement
     * pas déjà entièrement validé.
     *
     * @return array{ok: bool, message: string}
     */
    protected function validerPaiement($paiement): array
    {
        if (!$paiement) {
            return ['ok' => false, 'message' => 'Paiement introuvable.'];
        }

        if (!empty($paiement->user_valide2_id)) {
            return ['ok' => false, 'message' => 'Ce paiement est déjà validé.'];
        }

        $user = Auth::user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Vous devez être connecté pour valider.'];
        }

        // Le 2e validateur DOIT être un admin (type 1=SA ou 2=Admin)
        $estAdmin = in_array((int) $user->type_user_id, [1, 2], true);
        if (!$estAdmin) {
            return ['ok' => false, 'message' => 'Seul un administrateur peut effectuer la 2e validation.'];
        }

        // Le 2e validateur doit être différent du 1er
        if ((int) $paiement->user_valide_id === (int) $user->id) {
            return ['ok' => false, 'message' => 'Vous ne pouvez pas valider votre propre paiement.'];
        }

        $paiement->update([
            'user_valide2_id'   => $user->id,
            'date_validation_2' => now(),
        ]);

        return ['ok' => true, 'message' => 'Paiement validé avec succès.'];
    }

    /**
     * Helper booléen : un paiement est-il effectivement actif (2 validations) ?
     */
    protected function estValide($paiement): bool
    {
        return !empty($paiement?->user_valide2_id);
    }
}
