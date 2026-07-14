<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // livraison
        Schema::table('livraison', function (Blueprint $table) {
            $table->unsignedBigInteger('adresse_livraison_id')->nullable()->change();
            $table->unsignedBigInteger('livreur_id')->nullable()->change();
        });

        // commande
        Schema::table('commande', function (Blueprint $table) {
            $table->unsignedBigInteger('mode_paiement_id')->nullable()->change();
            $table->unsignedBigInteger('type_livraison_id')->nullable()->change();
        });

        // location
        Schema::table('location', function (Blueprint $table) {
            $table->unsignedBigInteger('adresse_livraison_id')->nullable()->change();
            $table->unsignedBigInteger('mode_paiement_id')->nullable()->change();
        });

        // adresse_livraison
        Schema::table('adresse_livraison', function (Blueprint $table) {
            $table->string('affichage', 50)->nullable()->change();
            $table->string('complement_adresse', 255)->nullable()->change();
        });

        // demande_livraison
        Schema::table('demande_livraison', function (Blueprint $table) {
            $table->unsignedBigInteger('adresse_livraison_pec_id')->nullable()->change();
            $table->unsignedBigInteger('adresse_livraison_dest_id')->nullable()->change();
            $table->unsignedBigInteger('mode_paiement_id')->nullable()->change();
            $table->unsignedBigInteger('type_livraison_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverting nullable changes is risky, skip
    }
};
