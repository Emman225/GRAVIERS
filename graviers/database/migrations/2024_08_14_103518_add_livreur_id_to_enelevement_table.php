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
        Schema::table('enlevement', function (Blueprint $table) {
            $table->unsignedBigInteger('livreur_id')->after('produit_id')->nullable();
            // $table->foreign('livreur_id')->references('id')->on('livreur');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enlevement', function (Blueprint $table) {
            //
        });
    }
};
