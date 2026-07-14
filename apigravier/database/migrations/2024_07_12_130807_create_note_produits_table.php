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
        Schema::create('note_produit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produit');
            $table->foreignId('client_id')->constrained('client');
            $table->string('avis');
            $table->smallInteger('note')->default(5);
            $table->smallInteger('statut')->default(Help::$STATUT_INACTIF);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_produit');
    }
};
