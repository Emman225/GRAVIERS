<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->index();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->double('montant')->default(0);
            $table->string('service')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->smallInteger('statut')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture');
    }
};