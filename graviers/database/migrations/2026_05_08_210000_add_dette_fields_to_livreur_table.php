<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            if (!Schema::hasColumn('livreur', 'code')) {
                $table->string('code', 30)->nullable()->after('id');
                $table->index('code');
            }
            if (!Schema::hasColumn('livreur', 'zone_intervention')) {
                $table->string('zone_intervention', 150)->nullable();
            }
            if (!Schema::hasColumn('livreur', 'tarif_km')) {
                $table->integer('tarif_km')->default(0);
            }
            if (!Schema::hasColumn('livreur', 'tarif_forfait_base')) {
                $table->integer('tarif_forfait_base')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            foreach (['code', 'zone_intervention', 'tarif_km', 'tarif_forfait_base'] as $col) {
                if (Schema::hasColumn('livreur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
