<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace chaque modification du « Prix de livraison » d'un livreur
 * (page Livreur → détail → modification de prix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_prix_livraison_livreur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('livreur_id');
            $table->double('ancien_prix')->default(0);
            $table->double('nouveau_prix')->default(0);
            $table->unsignedBigInteger('user_id')->nullable(); // utilisateur qui a fait la modif
            $table->string('motif')->nullable();               // commentaire facultatif
            $table->timestamps();

            $table->foreign('livreur_id')->references('id')->on('livreur')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('livreur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_prix_livraison_livreur');
    }
};
