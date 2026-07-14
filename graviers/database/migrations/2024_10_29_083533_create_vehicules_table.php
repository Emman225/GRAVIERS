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
        Schema::create('vehicule', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation', 15)->index();
            $table->string('nom');
            $table->string('description')->nullable();
            $table->foreignId('type_vehicule_id')->constrained('type_vehicule'); //Utilisateur qui approuve
            $table->foreignId('livreur_id')->constrained('livreur'); //Utilisateur qui approuve
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
        Schema::dropIfExists('vehicule');
    }
};
