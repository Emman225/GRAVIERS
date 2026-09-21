<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE NUMÉRO DE BON DE COMMANDE INTERNE, FIGÉ SUR LE DEVIS (09/09/2026).
 * Même migration que sur le site (base partagée) : gardée par un contrôle
 * d'existence, la seconde ne fait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('devis', 'numero_bon_commande')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->string('numero_bon_commande', 255)->nullable()->after('tva_transport');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devis', 'numero_bon_commande')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->dropColumn('numero_bon_commande');
            });
        }
    }
};
