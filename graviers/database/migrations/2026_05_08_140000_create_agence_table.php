<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agence')) {
            Schema::create('agence', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('nom', 150);
                $table->string('adresse', 255)->nullable();
                $table->string('telephone', 50)->nullable();
                $table->string('responsable', 150)->nullable();
                $table->smallInteger('statut')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agence');
    }
};
