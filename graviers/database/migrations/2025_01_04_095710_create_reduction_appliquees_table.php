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
        Schema::create('reduction_appliquees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commande');
            $table->foreignId('user_id')->constrained('users');
            $table->integer('taux_reduction');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reduction_appliquees');
    }
};
