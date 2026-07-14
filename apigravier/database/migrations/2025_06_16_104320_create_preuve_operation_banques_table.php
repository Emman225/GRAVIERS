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
        Schema::create('preuve_operation_banque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('client');
            $table->foreignId('commande_id')->constrained('commande');
            $table->string('reference', 20)->index();
            $table->string('num_compte', 20)->index();
            $table->string('banque', 100);
            $table->date('date_operation');
            $table->text('note_supp')->nullable();
            $table->string('fichier', 50);
            $table->tinyInteger('est_valide')->default(0);
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
        Schema::dropIfExists('preuve_operation_banques');
    }
};
