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
        Schema::create('detail_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produit');
            $table->foreignId('commande_id')->constrained('commande');
            $table->float('qte')->default(0);
            $table->double('prix')->default(0);
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
        Schema::dropIfExists('detail_commande');
    }
};
