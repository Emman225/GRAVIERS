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
        Schema::table('configuration', function (Blueprint $table) {
            $table->dropColumn('taux_commission');
            $table->float('montant_point'); //Le client défini 1(devise) = ? Fcfa
            $table->string('devise', 5); //Le client choisi quel devise utilisé par l'appli
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            //
        });
    }
};
