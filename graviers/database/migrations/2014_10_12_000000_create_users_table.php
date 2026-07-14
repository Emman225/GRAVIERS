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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom_prenoms');
            $table->string('email', 150)->unique()->index();
            $table->string('contact', 15);
            $table->string('login', 150)->unique()->index();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->string('adresse')->nullable();
            $table->unsignedBigInteger('pays_id')->default(0);
            $table->unsignedBigInteger('ville_id')->default(0);
            $table->foreignId('type_user_id')->constrained('type_user');
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
        Schema::dropIfExists('users');
    }
};
