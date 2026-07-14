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
        Schema::create('projet', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('titre');
            $table->string('sous_titre');
            $table->mediumText('description');
            $table->smallInteger('statut')->default(Help::$STATUT_ACTIF);
            $table->dateTime('date_debut')->default(date('Y-m-d H:i:s'));
            $table->dateTime('date_fin')->default(date('Y-m-d H:i:s'));
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projet');
    }
};
