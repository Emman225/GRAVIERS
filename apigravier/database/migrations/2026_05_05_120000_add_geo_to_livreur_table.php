<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            if (!Schema::hasColumn('livreur', 'longitude')) {
                $table->string('longitude', 30)->nullable();
            }
            if (!Schema::hasColumn('livreur', 'latitude')) {
                $table->string('latitude', 30)->nullable();
            }
            if (!Schema::hasColumn('livreur', 'derniere_position_at')) {
                $table->timestamp('derniere_position_at')->nullable();
            }
            if (!Schema::hasColumn('livreur', 'disponible')) {
                $table->boolean('disponible')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            foreach (['longitude', 'latitude', 'derniere_position_at', 'disponible'] as $col) {
                if (Schema::hasColumn('livreur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
