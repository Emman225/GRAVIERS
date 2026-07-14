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
        Schema::create('banniere', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 20);
            $table->string('sous_titre', 80);
            $table->string('image');
            $table->unsignedSmallInteger('num_ordre')->default(0)->nullable();
            $table->enum('type_banniere', [Help::$BANNIERE_TOP, Help::$BANNIERE_FLASH, Help::$BANNIERE_BOTTOM]);
            $table->dateTime('date_heure_decompte')->nullable();
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
        Schema::dropIfExists('banniere');
    }
};
