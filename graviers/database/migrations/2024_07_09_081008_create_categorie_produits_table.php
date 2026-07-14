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
        Schema::create('categorie_produit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_id')->constrained('categorie');
            $table->foreignId('produit_id')->constrained('produit');
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
        Schema::dropIfExists('categorie_produit');
    }
};
