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
        Schema::create('notifier_user', function (Blueprint $table) {
            $table->id();
            $table->mediumText('message');
            $table->foreignId('user_id')->constrained('users'); //celui qui doit recevoir
            $table->foreignId('user_envoyeur_id')->constrained('users'); //celui qui envoie
            $table->boolean('message_lu')->default(false);
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
        Schema::dropIfExists('notifier_user');
    }
};
