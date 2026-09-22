<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables », phase 2b : les écritures de trésorerie (lot 118, 21/09/2026).
 *
 * Encaissements, avances, décaissements vers les fournisseurs, les livreurs et
 * les apporteurs, cautions des locations. Réponses du responsable du
 * 21/09/2026 : chaque moyen de paiement a son journal ; livreurs et apporteurs
 * ont leurs comptes fournisseurs dédiés, la contrepartie est une charge ; les
 * cautions se suivent dans un compte de dépôts.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['livreur', 'apporteur'] as $nomTable) {
            if (Schema::hasTable($nomTable) && !Schema::hasColumn($nomTable, 'compte_tiers')) {
                Schema::table($nomTable, function (Blueprint $table) {
                    $table->string('compte_tiers', 20)->nullable()->index();
                });
            }
        }

        if (Schema::hasTable('ecriture_comptable') && !Schema::hasColumn('ecriture_comptable', 'tiers_type')) {
            Schema::table('ecriture_comptable', function (Blueprint $table) {
                $table->string('tiers_type', 15)->nullable();          // client | fournisseur | livreur | apporteur
                $table->unsignedBigInteger('tiers_id')->nullable();
                $table->index(['tiers_type', 'tiers_id']);
            });
        }

        if (Schema::hasTable('ligne_ecriture_comptable') && !Schema::hasColumn('ligne_ecriture_comptable', 'lettre')) {
            Schema::table('ligne_ecriture_comptable', function (Blueprint $table) {
                $table->string('lettre', 12)->nullable()->index();    // lettrage facture / règlement
                $table->dateTime('lettree_le')->nullable();
            });
        }

        if (!Schema::hasColumn('configuration', 'journal_cautions_id')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->unsignedBigInteger('journal_cautions_id')->nullable();   // où les cautions sont reçues et rendues
            });
        }

        // L'imputation d'une avance ne touche aucune trésorerie : il lui faut un journal d'opérations diverses.
        if (Schema::hasTable('journal_comptable') && !DB::table('journal_comptable')->where('type', 'DIVERS')->exists()) {
            DB::table('journal_comptable')->insert([
                'code' => 'OD', 'libelle' => 'Journal des opérations diverses', 'type' => 'DIVERS', 'statut' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuration', 'journal_cautions_id')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->dropColumn('journal_cautions_id');
            });
        }
        if (Schema::hasTable('ligne_ecriture_comptable') && Schema::hasColumn('ligne_ecriture_comptable', 'lettre')) {
            Schema::table('ligne_ecriture_comptable', function (Blueprint $table) {
                $table->dropIndex('ligne_ecriture_comptable_lettre_index');
                $table->dropColumn(['lettre', 'lettree_le']);
            });
        }
        if (Schema::hasTable('ecriture_comptable') && Schema::hasColumn('ecriture_comptable', 'tiers_type')) {
            Schema::table('ecriture_comptable', function (Blueprint $table) {
                $table->dropIndex('ecriture_comptable_tiers_type_tiers_id_index');
                $table->dropColumn(['tiers_type', 'tiers_id']);
            });
        }
        foreach (['livreur', 'apporteur'] as $nomTable) {
            if (Schema::hasTable($nomTable) && Schema::hasColumn($nomTable, 'compte_tiers')) {
                Schema::table($nomTable, function (Blueprint $table) use ($nomTable) {
                    $table->dropIndex($nomTable . '_compte_tiers_index');
                    $table->dropColumn('compte_tiers');
                });
            }
        }
    }
};
