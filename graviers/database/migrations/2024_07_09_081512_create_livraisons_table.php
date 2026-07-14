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
        Schema::create('livraison', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->index();
            $table->foreignId('livreur_id')->constrained('livreur');
            $table->foreignId('client_id')->constrained('client');
            $table->unsignedBigInteger('detail_commande_id')->default(0); //Dans le cadre d'une commande
            $table->unsignedBigInteger('detail_livraison_id')->default(0); //Pour une livraison
            $table->foreignId('adresse_livraison_id')->constrained('adresse_livraison');
            $table->unsignedInteger('cout_livraison')->default(0);
            $table->date('date_livraison');
            $table->float('qte');
            $table->string('note_livreur')->nullable();
            $table->enum('etat_livraison', Help::listeStatutLivraison())->index();
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
        Schema::dropIfExists('livraison');
    }
};
