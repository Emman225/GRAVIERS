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
        Schema::create('demande_compte_client_a_terme', function (Blueprint $table) {
            $table->id();
            $table->string('objet', 50);
            $table->mediumText('description')->nullable();
            $table->foreignId('client_id')->constrained('client'); //Le client qui demande
            $table->boolean('approuve')->default(false);
            $table->foreignId('user_id')->constrained('users'); //Utilisateur qui approuve
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
        Schema::dropIfExists('demande_compte_client_a_terme');
    }
};
