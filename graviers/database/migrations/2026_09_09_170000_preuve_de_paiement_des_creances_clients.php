<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POINT 20 SUR LES RÈGLEMENTS DES CRÉANCES CLIENTS (09/09/2026).
 *
 * Les encaissements du guichet « Créance » Paiements » (table paiement)
 * reçoivent les colonnes du circuit « À payer → preuve jointe → Effectuée »,
 * comme les demandes de paiement et les règlements des partenaires.
 * Facultatives : rien ne change pour l'existant. Base partagée avec l'API :
 * même migration des deux côtés, gardée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiement', function (Blueprint $t) {
            if (!Schema::hasColumn('paiement', 'etat_reglement')) {
                $t->string('etat_reglement', 20)->nullable()->after('date_validation_2');
            }
            if (!Schema::hasColumn('paiement', 'preuve_paiement')) {
                $t->string('preuve_paiement')->nullable()->after('etat_reglement');
            }
            if (!Schema::hasColumn('paiement', 'date_preuve')) {
                $t->dateTime('date_preuve')->nullable()->after('preuve_paiement');
            }
            if (!Schema::hasColumn('paiement', 'user_preuve_id')) {
                $t->unsignedBigInteger('user_preuve_id')->nullable()->after('date_preuve');
            }
            if (!Schema::hasColumn('paiement', 'date_effectuee')) {
                $t->dateTime('date_effectuee')->nullable()->after('user_preuve_id');
            }
            if (!Schema::hasColumn('paiement', 'user_effectuee_id')) {
                $t->unsignedBigInteger('user_effectuee_id')->nullable()->after('date_effectuee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiement', function (Blueprint $t) {
            foreach (['etat_reglement', 'preuve_paiement', 'date_preuve', 'user_preuve_id', 'date_effectuee', 'user_effectuee_id'] as $c) {
                if (Schema::hasColumn('paiement', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
