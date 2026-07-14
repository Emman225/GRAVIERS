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
            $table->string('email_tresorier', 50)->index()->nullable();
            $table->string('email_directeur_marketing', 50)->index()->nullable();
            $table->unsignedBigInteger('gestionnaire1_id')->index()->nullable();
            $table->unsignedBigInteger('gestionnaire2_id')->index()->nullable();
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
