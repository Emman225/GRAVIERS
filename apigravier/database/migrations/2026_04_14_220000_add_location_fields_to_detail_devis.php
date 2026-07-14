<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_devis', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_devis', 'debut_location')) {
                $table->date('debut_location')->nullable();
            }
            if (!Schema::hasColumn('detail_devis', 'fin_location')) {
                $table->date('fin_location')->nullable();
            }
            if (!Schema::hasColumn('detail_devis', 'nbre_jour_location')) {
                $table->integer('nbre_jour_location')->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_devis', function (Blueprint $table) {
            $table->dropColumn(['debut_location', 'fin_location', 'nbre_jour_location']);
        });
    }
};
