<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'delai_max_paiement_agence')) {
                $table->integer('delai_max_paiement_agence')->default(3);
            }
            if (!Schema::hasColumn('configuration', 'delai_annulation_auto')) {
                $table->integer('delai_annulation_auto')->default(7);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            foreach (['delai_max_paiement_agence', 'delai_annulation_auto'] as $col) {
                if (Schema::hasColumn('configuration', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
