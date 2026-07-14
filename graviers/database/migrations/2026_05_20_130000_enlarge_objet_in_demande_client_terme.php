<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demande_compte_client_a_terme', function (Blueprint $table) {
            // Passe de VARCHAR(50) à VARCHAR(255) — la limite à 50 ch. tronquait
            // les libellés un peu longs et provoquait une SQL "Data too long".
            $table->string('objet', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('demande_compte_client_a_terme', function (Blueprint $table) {
            $table->string('objet', 50)->change();
        });
    }
};
