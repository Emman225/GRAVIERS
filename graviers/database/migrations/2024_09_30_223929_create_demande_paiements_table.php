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
        Schema::create('demande_paiement', function (Blueprint $table) {
            $table->id();
            $table->double('montant')->default(0);
            $table->foreignId('mode_paiement_id')->constrained('mode_paiement');
            $table->foreignId('user_id')->constrained('users'); //celui qui fais la demande
            $table->foreignId('user_valide_id')->constrained('users'); //celui qui valide la demande
            $table->dateTime('date_validation')->default(date('Y-m-d H:i:s'));
            $table->boolean('paye')->default(false);
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
        Schema::dropIfExists('demande_paiement');
    }
};
