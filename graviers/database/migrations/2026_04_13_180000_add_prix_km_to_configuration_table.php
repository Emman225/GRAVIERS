<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'prixKm')) {
                $table->float('prixKm')->nullable()->default(0);
            }
            if (!Schema::hasColumn('configuration', 'cout_livraison_min')) {
                $table->float('cout_livraison_min')->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            $table->dropColumn(['prixKm', 'cout_livraison_min']);
        });
    }
};
