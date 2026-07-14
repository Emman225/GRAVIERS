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
        Schema::create('cout_livraison', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_produit_id')->references('id')->on('unite_produit');
            $table->double('distance_min_km')->default(1);
            $table->double('distance_max_km')->default(1);
            $table->double('prix_km')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cout_livraison');
    }
};
