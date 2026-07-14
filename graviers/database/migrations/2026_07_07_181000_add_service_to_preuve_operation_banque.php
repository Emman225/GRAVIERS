<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'API mobile (apigravier Commande/LocationController, paiement par virement)
     * écrit preuve_operation_banque.service ('COMMANDE'/'LOCATION' — discriminant
     * polymorphe de commande_id) mais la colonne n'existait pas ->
     * "Unknown column 'service'" -> 500 à l'enregistrement d'une commande ou
     * location payée par virement depuis le mobile.
     */
    public function up(): void
    {
        Schema::table('preuve_operation_banque', function (Blueprint $table) {
            if (!Schema::hasColumn('preuve_operation_banque', 'service')) {
                $table->string('service', 20)->nullable()->after('date_operation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preuve_operation_banque', function (Blueprint $table) {
            if (Schema::hasColumn('preuve_operation_banque', 'service')) {
                $table->dropColumn('service');
            }
        });
    }
};
