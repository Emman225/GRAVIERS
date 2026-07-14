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
        Schema::create('reduction', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->index();
            $table->string('libelle', 100);
            $table->date('debut');
            $table->date('fin');
            $table->boolean('est_utilise')->default(false);
            $table->smallInteger('taux_reduction');
            $table->double('montant_reduction');
            $table->foreignId('devis_id')->nullable()->constrained('devis');
            $table->foreignId('client_id')->constrained('client');
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
        Schema::dropIfExists('reduction');
    }
};
