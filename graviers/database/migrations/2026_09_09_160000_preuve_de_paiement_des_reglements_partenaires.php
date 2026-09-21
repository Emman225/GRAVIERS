<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POINT 20 SUR LES RÈGLEMENTS DE DETTES (09/09/2026).
 *
 * Le circuit « À payer → preuve jointe → Effectuée » n'existait que sur les
 * demandes de paiement (migration du 07/09). Les règlements enregistrés au
 * back-office (Dette » Paiements des fournisseurs, livreurs et apporteurs)
 * reçoivent les mêmes colonnes. Facultatives : rien ne change pour l'existant.
 * Site et API partagent la base : même migration des deux côtés, gardée.
 */
return new class extends Migration
{
    private const TABLES = ['paiement_fournisseur', 'paiement_livreur', 'paiement_apporteur'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'etat_reglement')) {
                    $t->string('etat_reglement', 20)->nullable()->after('date_validation_2');
                }
                if (!Schema::hasColumn($table, 'preuve_paiement')) {
                    $t->string('preuve_paiement')->nullable()->after('etat_reglement');
                }
                if (!Schema::hasColumn($table, 'date_preuve')) {
                    $t->dateTime('date_preuve')->nullable()->after('preuve_paiement');
                }
                if (!Schema::hasColumn($table, 'user_preuve_id')) {
                    $t->unsignedBigInteger('user_preuve_id')->nullable()->after('date_preuve');
                }
                if (!Schema::hasColumn($table, 'date_effectuee')) {
                    $t->dateTime('date_effectuee')->nullable()->after('user_preuve_id');
                }
                if (!Schema::hasColumn($table, 'user_effectuee_id')) {
                    $t->unsignedBigInteger('user_effectuee_id')->nullable()->after('date_effectuee');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['etat_reglement', 'preuve_paiement', 'date_preuve', 'user_preuve_id', 'date_effectuee', 'user_effectuee_id'] as $c) {
                    if (Schema::hasColumn($table, $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
    }
};
