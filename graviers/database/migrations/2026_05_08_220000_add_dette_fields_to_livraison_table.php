<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            if (!Schema::hasColumn('livraison', 'distance_km')) {
                $table->decimal('distance_km', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('livraison', 'forfait_base')) {
                $table->integer('forfait_base')->default(0);
            }
            if (!Schema::hasColumn('livraison', 'frais_km')) {
                $table->integer('frais_km')->default(0);
            }
            if (!Schema::hasColumn('livraison', 'statut_paiement_livreur')) {
                $table->string('statut_paiement_livreur', 30)->nullable();
            }
            if (!Schema::hasColumn('livraison', 'date_paiement_livreur')) {
                $table->date('date_paiement_livreur')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            foreach (['distance_km', 'forfait_base', 'frais_km', 'statut_paiement_livreur', 'date_paiement_livreur'] as $col) {
                if (Schema::hasColumn('livraison', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
