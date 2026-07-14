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
        Schema::create('demande_paiements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->index();
            $table->unsignedBigInteger('user_id')->index()->nullable();
            $table->double('montant')->default(0);
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
        Schema::dropIfExists('demande_paiements');
    }
};
