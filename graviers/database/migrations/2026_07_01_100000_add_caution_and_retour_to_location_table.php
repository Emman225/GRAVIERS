<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle de vie de la location (phase c) :
 *  - caution           : montant de la caution demandée (fcfa)
 *  - caution_restituee : 1 quand la caution a été rendue au client (au retour)
 *  - date_retour       : date de retour effectif du matériel
 *  - livreur_id/vehicule_id : livreur et véhicule affectés à la location (traçabilité)
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location', function (Blueprint $table) {
            if (!Schema::hasColumn('location', 'caution')) {
                $table->double('caution')->default(0);
            }
            if (!Schema::hasColumn('location', 'caution_restituee')) {
                $table->boolean('caution_restituee')->default(false);
            }
            if (!Schema::hasColumn('location', 'date_retour')) {
                $table->date('date_retour')->nullable();
            }
            if (!Schema::hasColumn('location', 'livreur_id')) {
                $table->unsignedBigInteger('livreur_id')->nullable()->index();
            }
            if (!Schema::hasColumn('location', 'vehicule_id')) {
                $table->unsignedBigInteger('vehicule_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('location', function (Blueprint $table) {
            foreach (['caution', 'caution_restituee', 'date_retour', 'livreur_id', 'vehicule_id'] as $col) {
                if (Schema::hasColumn('location', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
