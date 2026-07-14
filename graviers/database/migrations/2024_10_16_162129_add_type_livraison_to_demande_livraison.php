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
        Schema::table('demande_livraison', function (Blueprint $table) {
            $table->foreignId('mode_paiement_id')->references('id')->on('mode_paiement');
            $table->foreignId('type_livraison_id')->references('id')->on('type_livraison');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demande_livraison', function (Blueprint $table) {
            //
        });
    }
};
