<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'taux_commission_standard')) {
                $table->decimal('taux_commission_standard', 5, 2)->default(3.00);
            }
            if (!Schema::hasColumn('configuration', 'delai_paiement_commission')) {
                $table->integer('delai_paiement_commission')->default(15);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            foreach (['taux_commission_standard', 'delai_paiement_commission'] as $col) {
                if (Schema::hasColumn('configuration', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
