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
        Schema::create('produit', function (Blueprint $table) {
            $table->id()->index();
            $table->string('reference', 10)->nullable()->index();
            $table->string('nom', 155);
            $table->string('abreviation', 10)->nullable();
            $table->string('unite', 30)->nullable();
            $table->mediumText('description');
            $table->double('prix_moyen')->default(0);
            $table->double('prix_reduction')->default(0);
            $table->smallInteger('meilleur_note')->default(5);
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
        Schema::dropIfExists('produit');
    }
};
