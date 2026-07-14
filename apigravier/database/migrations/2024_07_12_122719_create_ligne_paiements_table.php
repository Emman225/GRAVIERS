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
        Schema::create('ligne_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiement');
            $table->foreignId('mode_paiement_id')->constrained('mode_paiement'); // Cash / Banque / Mobile Money...
            $table->string('reference', 20)->index();
            $table->string('moyen_paiement', 30); //Espèce / Virement / Chèque / Wave / OM...
            $table->dateTime('date_paiement')->default(date('Y-m-d H:i:s'));
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
        Schema::dropIfExists('ligne_paiement');
    }
};
