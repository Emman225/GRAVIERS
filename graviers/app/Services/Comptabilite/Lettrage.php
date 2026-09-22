<?php

namespace App\Services\Comptabilite;

use App\Models\EcritureComptable;
use App\Models\LigneEcritureComptable;
use Illuminate\Support\Facades\DB;

/**
 * LETTRAGE FACTURE / RÈGLEMENT, par affaire.
 *
 * Sur le compte du client, une affaire est soldée quand ses factures (débits)
 * égalent ses règlements, imputations d'avances et avoirs (crédits). Toutes
 * les lignes « client » de l'affaire reçoivent alors la même lettre. Une
 * nouvelle facture ou un nouveau règlement qui rompt l'égalité retire la
 * lettre : elle reviendra quand l'affaire sera de nouveau soldée.
 *
 * La lettre est une annotation de rapprochement : elle ne change ni un compte
 * ni un montant, et peut donc être posée sur une écriture déjà exportée.
 */
class Lettrage
{
    public static function lettrerAffaire(?string $service, $serviceId): ?string
    {
        if (!$service || !$serviceId) {
            return null;
        }

        // Les écritures annulées et leurs annulations se compensent : on les laisse hors du rapprochement.
        $lignes = LigneEcritureComptable::query()
            ->join('ecriture_comptable as e', 'e.id', '=', 'ligne_ecriture_comptable.ecriture_comptable_id')
            ->where('e.service', $service)->where('e.service_id', $serviceId)
            ->whereNull('e.annulee_par_id')->where('e.origine', '!=', EcritureComptable::ORIGINE_ANNULATION)
            ->where('e.etat', '!=', EcritureComptable::ETAT_ANOMALIE)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::CLIENT)
            ->select('ligne_ecriture_comptable.*')->get();

        $debit = (float) $lignes->sum('debit');
        $credit = (float) $lignes->sum('credit');
        $soldee = $lignes->count() >= 2 && $debit > 0 && abs($debit - $credit) < 1;

        $lettre = null;
        if ($soldee) {
            $lettre = $lignes->pluck('lettre')->filter()->first()
                ?: 'L' . strtoupper(base_convert((string) $lignes->min('id'), 10, 36));
        }

        // Aussi les lignes qui portaient une lettre et ne sont plus du rapprochement (écriture annulée, mise en anomalie).
        $ids = $lignes->pluck('id');
        DB::table('ligne_ecriture_comptable')
            ->whereIn('ecriture_comptable_id', EcritureComptable::where('service', $service)->where('service_id', $serviceId)->pluck('id'))
            ->where('rubrique', LigneEcritureComptable::CLIENT)->whereNotNull('lettre')
            ->when($soldee, fn ($q) => $q->whereNotIn('id', $ids))
            ->update(['lettre' => null, 'lettree_le' => null]);

        if ($soldee) {
            DB::table('ligne_ecriture_comptable')->whereIn('id', $ids)
                ->where(fn ($q) => $q->whereNull('lettre')->orWhere('lettre', '!=', $lettre))
                ->update(['lettre' => $lettre, 'lettree_le' => now()]);
        }

        return $lettre;
    }
}
