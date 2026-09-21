<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CIRCUIT D'UNE DEMANDE DE PAIEMENT NE S'ARRÊTE PLUS À LA 2e VALIDATION.
 *
 * Point 20 du 07/09/2026. Après le second validateur, la demande passe
 * « À payer » ; l'agent joint ensuite la preuve du virement ; puis il déclare
 * l'opération « Effectuée » — ce que voient le livreur, l'apporteur et le
 * fournisseur. Chaque étape est datée et signée.
 *
 * L'imputation comptable (solde du tiers, bons soldés) reste à la 2e
 * validation, comme avant : ces colonnes ajoutent un SUIVI, elles ne
 * déplacent aucun mouvement d'argent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demande_paiement', function (Blueprint $table) {
            if (!Schema::hasColumn('demande_paiement', 'etat_reglement')) {
                $table->string('etat_reglement', 20)->nullable()->after('paye');
            }
            if (!Schema::hasColumn('demande_paiement', 'preuve_paiement')) {
                $table->string('preuve_paiement')->nullable()->after('etat_reglement');
            }
            if (!Schema::hasColumn('demande_paiement', 'date_preuve')) {
                $table->dateTime('date_preuve')->nullable()->after('preuve_paiement');
            }
            if (!Schema::hasColumn('demande_paiement', 'user_preuve_id')) {
                $table->unsignedBigInteger('user_preuve_id')->nullable()->after('date_preuve');
            }
            if (!Schema::hasColumn('demande_paiement', 'date_effectuee')) {
                $table->dateTime('date_effectuee')->nullable()->after('user_preuve_id');
            }
            if (!Schema::hasColumn('demande_paiement', 'user_effectuee_id')) {
                $table->unsignedBigInteger('user_effectuee_id')->nullable()->after('date_effectuee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demande_paiement', function (Blueprint $table) {
            foreach (['etat_reglement', 'preuve_paiement', 'date_preuve', 'user_preuve_id', 'date_effectuee', 'user_effectuee_id'] as $c) {
                if (Schema::hasColumn('demande_paiement', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
