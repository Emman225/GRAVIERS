<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paiement_livreur')) {
            Schema::create('paiement_livreur', function (Blueprint $table) {
                $table->id();
                $table->date('date_paiement');
                $table->unsignedBigInteger('livraison_id');
                $table->unsignedBigInteger('livreur_id');
                $table->bigInteger('montant')->default(0);
                $table->unsignedBigInteger('mode_paiement_id')->nullable();
                $table->string('reference', 80)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->smallInteger('statut')->default(1);
                $table->timestamps();
                $table->softDeletes();
                $table->index('livraison_id');
                $table->index('livreur_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_livreur');
    }
};
