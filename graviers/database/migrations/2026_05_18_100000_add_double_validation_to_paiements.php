<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double validation pour tous les paiements en agence.
 *
 * Workflow :
 *  - 1er validateur (gestionnaire) : crée le paiement avec user_valide_id renseigné.
 *    Le paiement est "en attente de validation" (user_valide2_id NULL).
 *  - 2e validateur (admin ≠ 1er) : finalise le paiement en renseignant
 *    user_valide2_id. Seul un paiement avec user_valide2_id NOT NULL est
 *    considéré comme actif/effectif côté métier.
 *
 * Pour la rétro-compatibilité, les enregistrements existants sont marqués
 * comme validés (user_valide_id = user_valide2_id = créateur connu).
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

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'user_valide_id')) {
                    $t->unsignedBigInteger('user_valide_id')->nullable()->after('statut');
                    $t->index('user_valide_id');
                }
                if (!Schema::hasColumn($table, 'user_valide2_id')) {
                    $t->unsignedBigInteger('user_valide2_id')->nullable()->after('user_valide_id');
                    $t->index('user_valide2_id');
                }
                if (!Schema::hasColumn($table, 'date_validation_1')) {
                    $t->timestamp('date_validation_1')->nullable()->after('user_valide2_id');
                }
                if (!Schema::hasColumn($table, 'date_validation_2')) {
                    $t->timestamp('date_validation_2')->nullable()->after('date_validation_1');
                }
            });

            // Marquer les enregistrements existants comme déjà validés
            // (1re et 2e validation = même utilisateur = créateur connu).
            $colonneCreateur = collect($createurCandidats)
                ->first(fn($col) => Schema::hasColumn($table, $col));

            if ($colonneCreateur) {
                DB::table($table)
                    ->whereNull('user_valide_id')
                    ->update([
                        'user_valide_id'    => DB::raw($colonneCreateur),
                        'user_valide2_id'   => DB::raw($colonneCreateur),
                        'date_validation_1' => DB::raw('created_at'),
                        'date_validation_2' => DB::raw('created_at'),
                    ]);
            } else {
                // Fallback : on ne peut pas identifier le créateur,
                // on marque quand même comme validés via user_id=1 (admin par défaut)
                DB::table($table)
                    ->whereNull('user_valide_id')
                    ->update([
                        'user_valide_id'    => 1,
                        'user_valide2_id'   => 1,
                        'date_validation_1' => DB::raw('created_at'),
                        'date_validation_2' => DB::raw('created_at'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $_) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['user_valide_id', 'user_valide2_id', 'date_validation_1', 'date_validation_2'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
