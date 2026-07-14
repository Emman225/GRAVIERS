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
        Schema::create('demande_livraison', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->index();
            $table->string('libelle')->nullable();
            $table->mediumText('description')->nullable();
            $table->foreignId('client_id')->constrained('client'); //Le client qui demande
            $table->foreignId('adresse_livraison_pec_id')->constrained('adresse_livraison'); //Adresse de prise en charge
            $table->foreignId('adresse_livraison_dest_id')->constrained('adresse_livraison'); //Adresse de destination
            $table->double('montantTotal')->default(0);
            $table->enum('etat_commande', Help::listeStatutCommande())->index();
            $table->dateTime('date_livraison')->default(date('Y-m-d H:i:s'));
            $table->dateTime('date_fin_livraison')->default(date('Y-m-d H:i:s'));
            $table->double('remise')->default(0);
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
        Schema::dropIfExists('demande_livraison');
    }
};
