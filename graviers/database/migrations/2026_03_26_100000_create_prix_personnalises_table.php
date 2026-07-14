<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prix_personnalises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('produit_id');
            $table->index('client_id');
            $table->index('produit_id');
            $table->double('prix');
            $table->timestamps();

            $table->unique(['client_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prix_personnalises');
    }
};
