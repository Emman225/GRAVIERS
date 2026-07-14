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
        Schema::table('livraison', function (Blueprint $table) {
            $table->enum('provenance', Help::listeProvenance());
            $table->foreignId('type_livraison_id')->references('id')->on('type_livraison')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            //
        });
    }
};
