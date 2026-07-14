<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adresse_livraison', function (Blueprint $table) {
            // Passe de VARCHAR(50) à VARCHAR(255) — la limite à 50 ch. tronquait
            // les adresses géocodées (OpenStreetMap) et provoquait une SQL "Data too long".
            $table->string('affichage', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('adresse_livraison', function (Blueprint $table) {
            $table->string('affichage', 50)->change();
        });
    }
};
