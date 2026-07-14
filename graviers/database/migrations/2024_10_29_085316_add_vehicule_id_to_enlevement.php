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
            $table->dropColumn('matricule_vehicule');
            $table->unsignedBigInteger('vehicule_id')->index()->nullable();
        });
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
