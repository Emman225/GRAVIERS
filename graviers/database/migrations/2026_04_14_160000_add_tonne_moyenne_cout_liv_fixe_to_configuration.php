<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'tonne_moyenne')) {
                $table->float('tonne_moyenne')->nullable()->default(40);
            }
            if (!Schema::hasColumn('configuration', 'cout_liv_fixe')) {
                $table->float('cout_liv_fixe')->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            $table->dropColumn(['tonne_moyenne', 'cout_liv_fixe']);
        });
    }
};
