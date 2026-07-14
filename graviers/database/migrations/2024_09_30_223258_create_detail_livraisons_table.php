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
        Schema::create('detail_livraison', function (Blueprint $table) {
            $table->id();
            $table->string('nom_produit');
            $table->float('qte');
            $table->string('unite', 30);
            $table->mediumText('description');
            $table->float('poids_vehicule_souhaite');
            $table->unsignedInteger('nombre_voyage');
            $table->foreignId('demande_livraison_id')->constrained('demande_livraison');
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
        Schema::dropIfExists('detail_livraison');
    }
};
