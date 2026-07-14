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
        Schema::create('stock_produit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fournisseur_id')->constrained('fournisseur', 'id');
            $table->foreignId('produit_id')->constrained('produit', 'id');
            $table->float('qte')->nullable();
            $table->double('prix')->nullable();
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
        Schema::dropIfExists('stock_produit');
    }
};
