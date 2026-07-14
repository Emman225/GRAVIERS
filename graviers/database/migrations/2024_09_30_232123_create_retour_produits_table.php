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
        Schema::create('retour_produit', function (Blueprint $table) {
            $table->id();
            $table->mediumText('motif');
            $table->foreignId('client_id')->constrained('client'); //Le client qui demande
            $table->foreignId('detail_commande_id')->constrained('detail_commande');
            $table->smallInteger('statut')->default(Help::$STATUT_ACTIF);
            $table->foreignId('user_id')->constrained('users'); //celui qui receptionne le retour
            $table->foreignId('user_paie_id')->constrained('users'); //celui qui fais le remboursement
            $table->mediumText('observation_reception');
            $table->boolean('rembourse')->default(false); //Indique si le client a été remboursé ou pas
            $table->dateTime('date_retour')->default(date('Y-m-d H:i:s'));
            $table->dateTime('date_reception')->nullable();
            $table->dateTime('date_rembourssement')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retour_produit');
    }
};
