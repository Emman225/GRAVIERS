<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bl_client', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->index();
            $table->foreignId('client_id')->constrained('client');
            $table->string('fichier', 200);
            $table->double('montant')->default(0);
            $table->smallInteger('statut')->default(Help::$STATUT_ACTIF);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bl_client');
    }
};
