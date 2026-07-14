<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réparation post-bug : les paiements créés après la migration
 * 2026_05_18_100000_add_double_validation_to_paiements mais avant l'ajout
 * des champs au $fillable des modèles Paiement* avaient leurs colonnes
 * de validation à NULL (Eloquent les a silencieusement ignorées).
 *
 * Cette migration corrige les enregistrements orphelins :
 *  - Si statut=1 (déjà "validés" par le bug) → on les flagge user_valide_id=créateur,
 *    user_valide2_id=créateur (on ne peut pas reconstituer la vraie double validation,
 *    mais au moins ils sont marqués comme finalisés).
 *  - Si statut=2 (en attente) → user_valide_id=créateur, user_valide2_id=null
 *    (workflow normal restauré).
 */
return new class extends Migration
{
    private array $tables = [
        'paiement'              => ['caissier_id', 'created_by', 'user_id'],
        'paiement_fournisseur'  => ['user_id', 'created_by'],
        'paiement_livreur'      => ['user_id', 'created_by'],
        'paiement_apporteur'    => ['user_id', 'created_by'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $createurCandidats) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $colonneCreateur = collect($createurCandidats)
                ->first(fn($col) => Schema::hasColumn($table, $col));

            if (!$colonneCreateur) {
                continue;
            }

            // statut=1 et user_valide_id NULL → bug : on met les 2 validateurs au créateur
            DB::table($table)
                ->where('statut', 1)
                ->whereNull('user_valide_id')
                ->update([
                    'user_valide_id'    => DB::raw($colonneCreateur),
                    'user_valide2_id'   => DB::raw($colonneCreateur),
                    'date_validation_1' => DB::raw('created_at'),
                    'date_validation_2' => DB::raw('updated_at'),
                ]);

            // statut=2 et user_valide_id NULL → restaure user_valide_id pour permettre
            // au workflow normal (autre admin valide) de reprendre
            DB::table($table)
                ->where('statut', 2)
                ->whereNull('user_valide_id')
                ->update([
                    'user_valide_id'    => DB::raw($colonneCreateur),
                    'date_validation_1' => DB::raw('created_at'),
                ]);
        }
    }

    public function down(): void
    {
        // Aucune action : on ne va pas re-créer le bug volontairement.
    }
};
