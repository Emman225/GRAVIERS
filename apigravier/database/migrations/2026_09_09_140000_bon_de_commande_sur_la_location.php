<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE BON DE COMMANDE INTERNE SUR LA LOCATION (09/09/2026).
 * Même migration que sur le site (base partagée) : gardée par un contrôle
 * d'existence, la seconde ne fait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('location', 'numero_bon_commande')) {
            Schema::table('location', function (Blueprint $table) {
                $table->string('numero_bon_commande', 255)->nullable()->after('tva_transport');
            });
        }
        if (!Schema::hasColumn('bl_client', 'location_id')) {
            Schema::table('bl_client', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id')->nullable()->after('demande_livraison_id');
                $table->index('location_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bl_client', 'location_id')) {
            Schema::table('bl_client', function (Blueprint $table) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            });
        }
        if (Schema::hasColumn('location', 'numero_bon_commande')) {
            Schema::table('location', function (Blueprint $table) {
                $table->dropColumn('numero_bon_commande');
            });
        }
    }
};
