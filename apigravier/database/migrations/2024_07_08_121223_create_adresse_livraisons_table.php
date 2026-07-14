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
        Schema::create('adresse_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('client');
            $table->foreignId('pays_id')->constrained('pays');
            $table->foreignId('ville_id')->constrained('ville');
            $table->string('affichage', 50);
            $table->string('complement_adresse');
            $table->boolean('defaut')->default(false);
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
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
        Schema::dropIfExists('adresse_livraison');
    }
};
