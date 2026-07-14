<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlevement', function (Blueprint $table) {
            if (!Schema::hasColumn('enlevement', 'date_echeance')) {
                $table->date('date_echeance')->nullable();
            }
            if (!Schema::hasColumn('enlevement', 'statut_dette')) {
                $table->string('statut_dette', 30)->nullable();
            }
            if (!Schema::hasColumn('enlevement', 'observations')) {
                $table->text('observations')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('enlevement', function (Blueprint $table) {
            foreach (['date_echeance', 'statut_dette', 'observations'] as $col) {
                if (Schema::hasColumn('enlevement', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
