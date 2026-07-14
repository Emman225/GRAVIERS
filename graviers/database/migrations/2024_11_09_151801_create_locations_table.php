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
        Schema::create('location', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->index();
            $table->foreignId('client_id')->constrained('client');
            $table->foreignId('mode_paiement_id')->constrained('mode_paiement');
            $table->foreignId('adresse_livraison_id')->constrained('adresse_livraison');
            $table->dateTime('date_location')->default(date('Y-m-d H:i:s'));
            $table->double('montant_total')->default(0);
            $table->enum('etat_location', Help::listeStatutLocation())->index();
            $table->string('note')->nullable();
            $table->double('remise')->default(0);
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
        Schema::dropIfExists('location');
    }
};
