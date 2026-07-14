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
        Schema::create('contact', function (Blueprint $table) {
            $table->id();
            $table->string('nom_prenoms', 150);
            $table->string('email', 100)->index()->unique();
            $table->string('telephone', 15);
            $table->string('sujet', 50);
            $table->mediumText('message');
            $table->boolean('lu')->default(false);
            $table->smallInteger('statut')->default(Help::$STATUT_INACTIF);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact');
    }
};
