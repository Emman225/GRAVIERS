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
        Schema::create('client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('email', 150)->index()->unique();
            $table->string('contact1');
            $table->string('contact2')->nullable();
            $table->string('code_parrain', 20)->index()->nullable();
            $table->string('rccm_clt', 50)->nullable();
            $table->string('ncc_clt', 50)->nullable();
            $table->unsignedBigInteger('parrain_id')->nullable()->default(0)->index();
            $table->enum('type_client', [Help::$PARTICULIER, Help::$ENTREPRISE]);
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
        Schema::dropIfExists('client');
    }
};
