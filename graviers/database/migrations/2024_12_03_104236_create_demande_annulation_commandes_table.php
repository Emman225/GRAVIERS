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
        Schema::create('demande_annulation_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('client');
            $table->unsignedBigInteger('user_id'); //user traitant
            $table->foreignId('commande_id')->constrained('commande');
            $table->mediumText('motif');
            $table->boolean('est_traite')->default(false);
            $table->mediumText('note')->nullable();
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
        Schema::dropIfExists('demande_annulation_commande');
    }
};
