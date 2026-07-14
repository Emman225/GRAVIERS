<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_apporteur', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_apporteur', 'numero')) {
                $table->string('numero', 30)->nullable();
                $table->index('numero');
            }
            if (!Schema::hasColumn('commission_apporteur', 'date_echeance')) {
                $table->date('date_echeance')->nullable();
            }
            if (!Schema::hasColumn('commission_apporteur', 'statut_commission')) {
                $table->string('statut_commission', 30)->nullable();
            }
            if (!Schema::hasColumn('commission_apporteur', 'observations')) {
                $table->text('observations')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('commission_apporteur', function (Blueprint $table) {
            foreach (['numero', 'date_echeance', 'statut_commission', 'observations'] as $col) {
                if (Schema::hasColumn('commission_apporteur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
