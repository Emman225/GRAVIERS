<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'frequence_paiement_livreur')) {
                $table->string('frequence_paiement_livreur', 30)->default('Hebdomadaire');
            }
            if (!Schema::hasColumn('configuration', 'jour_paiement_livreur')) {
                $table->string('jour_paiement_livreur', 30)->default('Vendredi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            foreach (['frequence_paiement_livreur', 'jour_paiement_livreur'] as $col) {
                if (Schema::hasColumn('configuration', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
