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
        Schema::create('detail_devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produit');
            $table->foreignId('devis_id')->constrained('devis');
            $table->float('qte');
            $table->double('prix');
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
        Schema::dropIfExists('detail_devis');
    }
};
