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
        Schema::create('commission_apporteur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apporteur_id')->constrained('apporteur');
            $table->foreignId('commande_id')->constrained('commande');
            $table->double('montant')->default(1);
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
        Schema::dropIfExists('commission_apporteur');
    }
};
